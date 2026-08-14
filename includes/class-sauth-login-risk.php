<?php

defined( 'ABSPATH' ) || exit;

/**
 * Password-login risk evaluation.
 *
 * File 00 owns membership/identity prerequisites. File 02 owns authentication
 * factors. The retired File 00 Authenticator/recovery-code challenge is never
 * used: elevated password logins either continue under bounded medium-risk
 * policy or require a separate File 02 passkey sign-in.
 */
final class SAUTH_Login_Risk {
	const TRUST_TTL           = 90 * DAY_IN_SECONDS;
	const ATTEMPT_RETENTION   = 30 * DAY_IN_SECONDS;
	const CHALLENGE_THRESHOLD = 45;
	const HIGH_RISK_THRESHOLD = 80;
	const MAX_TOKEN_BYTES     = 256;

	public static function init() {
		/* Keep old route only as a fail-closed tombstone for historical links. */
		add_action( 'admin_post_nopriv_sauth_login_risk_verify', array( __CLASS__, 'handle_challenge' ) );
		add_action( 'admin_post_sauth_login_risk_verify', array( __CLASS__, 'handle_challenge' ) );
		add_action( 'sauth_login_risk_cleanup', array( __CLASS__, 'cleanup' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'sauth_login_risk_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sauth_login_risk_cleanup' );
		}
	}

	/** Finish a successful password check or require a separate passkey sign-in. */
	public static function complete_password_login( WP_User $user, $remember, $requested_destination, array $completion_state ) {
		$requested_destination = SA_Security::safe_redirect( $requested_destination, home_url( '/' ) );
		$risk = self::evaluate( $user->ID, $completion_state );
		if ( 'challenge' === $risk['action'] ) {
			self::record_attempt( $user->ID, 'challenged', 'passkey_step_up_required', (int) $risk['score'] );
			SAUTH_Event_Outbox::emit( 'AccountAuthenticationFailed.v1', $user->ID, $user->ID, array( 'method' => 'password', 'reason' => 'passkey_step_up_required', 'risk' => self::risk_band( (int) $risk['score'] ) ), 'security' );
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'This password sign-in needs stronger verification. Use your registered passkey to sign in.' ) );
			exit;
		}
		if ( 'deny' === $risk['action'] ) {
			self::record_attempt( $user->ID, 'denied', (string) $risk['reason_code'], (int) $risk['score'] );
			SAUTH_Event_Outbox::emit( 'AccountAuthenticationFailed.v1', $user->ID, $user->ID, array( 'method' => 'password', 'reason' => (string) $risk['reason_code'], 'risk' => self::risk_band( (int) $risk['score'] ) ), 'security' );
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'This sign-in was not accepted safely. Use a registered passkey or contact support.' ) );
			exit;
		}
		self::establish_session( $user, (bool) $remember, $requested_destination, $completion_state, $risk );
	}

	/** Historical File 00 code challenge UI is deliberately retired. */
	public static function render() {
		return '<main class="sa-auth-shell"><section class="sa-auth-card" aria-labelledby="sauth-risk-title"><div class="sa-brand-mark">SH</div><span class="sa-kicker">Additional verification</span><h1 id="sauth-risk-title">Use Your Passkey</h1><div class="sa-notice sa-notice-error" role="status">The older Membership Core Authenticator/recovery-code challenge has been retired. Start sign-in again and use a File 02 passkey when stronger verification is required.</div><a class="sa-primary-button" href="' . esc_url( SA_Security::page_url( 'login' ) ) . '">Return to Log In</a></section></main>';
	}

	public static function handle_challenge() {
		$token = isset( $_REQUEST['challenge'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['challenge'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( strlen( $token ) <= self::MAX_TOKEN_BYTES && '' !== $token ) {
			self::invalidate_challenge( $token, 'retired' );
		}
		wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'This older verification step has been retired. Start sign-in again and use a passkey if stronger authentication is required.' ) );
		exit;
	}

	/** Record a provider-authenticated login after its own strong authentication. */
	public static function record_successful_login( $user_id, $method = 'provider', $score = 0 ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) { return false; }
		self::upsert_device( $user_id, max( 0, absint( $score ) ), 'trusted' );
		self::record_attempt( $user_id, 'success', sanitize_key( $method ), absint( $score ) );
		return true;
	}

	public static function record_failure( $user_id, $reason, $score = 0 ) {
		self::record_attempt( absint( $user_id ), 'failure', sanitize_key( (string) $reason ), absint( $score ) );
	}

	/** @return array{action:string,score:int,reason_code:string} */
	public static function evaluate( $user_id, array $completion_state = array() ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$score   = 0;
		$reasons = array();
		if ( ! $user_id ) { return array( 'action' => 'deny', 'score' => 100, 'reason_code' => 'subject_invalid' ); }
		$device = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::device_table() . " WHERE user_id=%d AND fingerprint_hash=%s AND status='trusted'", $user_id, SA_Security::client_fingerprint() ), ARRAY_A );
		if ( '' !== (string) $wpdb->last_error ) { return array( 'action' => 'deny', 'score' => 100, 'reason_code' => 'risk_storage_unavailable' ); }
		if ( ! is_array( $device ) || empty( $device['last_seen_at'] ) || strtotime( (string) $device['last_seen_at'] ) < time() - self::TRUST_TTL ) { $score += 50; $reasons[] = 'new_device'; }
		$network_hash = self::network_hash();
		$known_network = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::device_table() . " WHERE user_id=%d AND network_hash=%s AND status='trusted'", $user_id, $network_hash ) );
		if ( null === $known_network && '' !== (string) $wpdb->last_error ) { return array( 'action' => 'deny', 'score' => 100, 'reason_code' => 'risk_storage_unavailable' ); }
		if ( 0 === (int) $known_network ) { $score += 20; $reasons[] = 'new_network'; }
		$recent_failures = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::attempt_table() . " WHERE user_id=%d AND result IN ('failure','denied') AND created_at >= %s", $user_id, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );
		if ( null === $recent_failures && '' !== (string) $wpdb->last_error ) { return array( 'action' => 'deny', 'score' => 100, 'reason_code' => 'risk_storage_unavailable' ); }
		if ( (int) $recent_failures >= 5 ) { $score += 35; $reasons[] = 'recent_failures'; }
		if ( ! SAUTH_Provider_Health::allow_request( 'membership' ) ) { $score += 30; $reasons[] = 'membership_provider_degraded'; }
		$score = min( 100, $score );
		$reason = empty( $reasons ) ? 'known_context' : implode( '_', $reasons );
		if ( $score < self::CHALLENGE_THRESHOLD ) { return array( 'action' => 'allow', 'score' => $score, 'reason_code' => $reason ); }
		$passkey_ready = class_exists( 'SAUTH_Passkeys' )
			&& is_callable( array( 'SAUTH_Passkeys', 'authentication_ready' ) )
			&& SAUTH_Passkeys::authentication_ready();
		if ( ! $passkey_ready ) {
			return array( 'action' => 'deny', 'score' => max( self::HIGH_RISK_THRESHOLD, $score ), 'reason_code' => 'strong_authentication_unavailable' );
		}
		$has_active_passkey = self::has_active_passkey( $user_id );
		if ( null === $has_active_passkey ) { return array( 'action' => 'deny', 'score' => 100, 'reason_code' => 'passkey_status_unavailable' ); }
		if ( $has_active_passkey ) { return array( 'action' => 'challenge', 'score' => $score, 'reason_code' => $reason ); }
		/* A first/new network alone may produce medium risk before a user has ever
		 * enrolled a passkey. Keep that path usable but never allow high risk. */
		if ( $score < self::HIGH_RISK_THRESHOLD ) { return array( 'action' => 'allow', 'score' => $score, 'reason_code' => 'bounded_medium_risk_no_passkey' ); }
		return array( 'action' => 'deny', 'score' => $score, 'reason_code' => 'high_risk_passkey_unavailable' );
	}

	public static function cleanup() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::challenge_table() . " WHERE expires_at < %s OR (status <> 'pending' AND updated_at < %s)", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::attempt_table() . ' WHERE created_at < %s', gmdate( 'Y-m-d H:i:s', time() - self::ATTEMPT_RETENTION ) ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::device_table() . " SET status='expired', updated_at=%s WHERE status='trusted' AND last_seen_at < %s", current_time( 'mysql', true ), gmdate( 'Y-m-d H:i:s', time() - self::TRUST_TTL ) ) );
	}

	private static function establish_session( WP_User $user, $remember, $requested_destination, array $completion_state, array $risk ) {
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, (bool) $remember, is_ssl() );
		try { do_action( 'wp_login', $user->user_login, $user ); } catch ( Throwable $error ) { SA_Membership_Adapter::audit( 'authentication_observer_failed', $user->ID, array( 'observer' => 'wp_login' ) ); }
		self::upsert_device( $user->ID, absint( $risk['score'] ?? 0 ), 'trusted' );
		self::record_attempt( $user->ID, 'success', sanitize_key( (string) ( $risk['reason_code'] ?? 'authenticated' ) ), absint( $risk['score'] ?? 0 ) );
		$resolution = SAUTH_Completion_Resolver::resolve( $user->ID, $requested_destination, $completion_state );
		$destination = 'allow' === ( $resolution['result'] ?? '' ) ? (string) $resolution['destination'] : SA_Membership_Adapter::profile_url();
		SAUTH_Event_Outbox::emit( 'AccountAuthenticationSucceeded.v1', $user->ID, $user->ID, array( 'method' => 'password', 'risk' => self::risk_band( absint( $risk['score'] ?? 0 ) ), 'step_up' => false, 'completion_required' => ! empty( $resolution['missing_steps'] ) ), 'security' );
		SA_Membership_Adapter::audit( 'password_authentication_succeeded', $user->ID, array( 'risk' => self::risk_band( absint( $risk['score'] ?? 0 ) ) ) );
		wp_safe_redirect( SA_Security::safe_redirect( $destination, home_url( '/' ) ) );
		exit;
	}

	private static function has_active_passkey( $user_id ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sauth_passkeys WHERE user_id=%d AND status='active'", absint( $user_id ) ) );
		if ( null === $count && '' !== (string) $wpdb->last_error ) { return null; }
		return 0 < (int) $count;
	}

	private static function invalidate_challenge( $token, $reason ) {
		global $wpdb;
		if ( '' === (string) $token || strlen( (string) $token ) > self::MAX_TOKEN_BYTES ) { return; }
		$wpdb->update( self::challenge_table(), array( 'status' => sanitize_key( (string) $reason ), 'updated_at' => current_time( 'mysql', true ), 'token_hash' => str_repeat( '0', 64 ) ), array( 'token_hash' => self::token_hash( $token ) ), array( '%s', '%s', '%s' ), array( '%s' ) );
	}

	private static function upsert_device( $user_id, $risk_score, $status ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$table = self::device_table();
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT public_id FROM {$table} WHERE user_id=%d AND fingerprint_hash=%s", absint( $user_id ), SA_Security::client_fingerprint() ) );
		$data = array( 'user_id' => absint( $user_id ), 'fingerprint_hash' => SA_Security::client_fingerprint(), 'network_hash' => self::network_hash(), 'device_label' => self::device_label(), 'network_label' => self::network_label(), 'status' => sanitize_key( $status ), 'risk_score' => min( 100, absint( $risk_score ) ), 'last_seen_at' => $now, 'last_login_at' => $now, 'updated_at' => $now );
		if ( $existing ) { $wpdb->update( $table, $data, array( 'public_id' => (string) $existing ), array( '%d','%s','%s','%s','%s','%s','%d','%s','%s','%s' ), array( '%s' ) ); return; }
		$data['public_id'] = strtolower( wp_generate_uuid4() ); $data['first_seen_at'] = $now;
		$wpdb->insert( $table, $data, array( '%d','%s','%s','%s','%s','%s','%d','%s','%s','%s','%s','%s' ) );
	}

	private static function record_attempt( $user_id, $result, $reason, $risk_score ) {
		global $wpdb;
		$wpdb->insert( self::attempt_table(), array( 'public_id' => strtolower( wp_generate_uuid4() ), 'user_id' => absint( $user_id ), 'fingerprint_hash' => SA_Security::client_fingerprint(), 'network_hash' => self::network_hash(), 'result' => sanitize_key( (string) $result ), 'reason_code' => sanitize_key( (string) $reason ), 'risk_score' => min( 100, absint( $risk_score ) ), 'created_at' => current_time( 'mysql', true ) ), array( '%s','%d','%s','%s','%s','%s','%d','%s' ) );
	}

	private static function risk_band( $score ) { $score = absint( $score ); return $score >= self::HIGH_RISK_THRESHOLD ? 'high' : ( $score >= self::CHALLENGE_THRESHOLD ? 'medium' : 'low' ); }
	private static function device_label() { $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) ) : ''; $browser = false !== strpos( $ua, 'firefox' ) ? 'Firefox' : ( false !== strpos( $ua, 'edg' ) ? 'Edge' : ( false !== strpos( $ua, 'chrome' ) ? 'Chrome' : ( false !== strpos( $ua, 'safari' ) ? 'Safari' : 'Web browser' ) ) ); return $browser . ' on a ' . ( false !== strpos( $ua, 'mobile' ) ? 'mobile device' : 'computer' ); }
	private static function network_label() { $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), 0, 64 ) : ''; if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) { $p = explode( '.', $ip ); return $p[0] . '.' . $p[1] . '.x.x'; } if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) { $p = explode( ':', $ip ); return implode( ':', array_slice( $p, 0, 2 ) ) . ':…'; } return 'Private'; }
	private static function network_hash() { return hash_hmac( 'sha256', self::network_label(), wp_salt( 'secure_auth' ) ); }
	private static function token_hash( $token ) { return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) ); }
	private static function device_table() { return SAUTH_Activator::table( 'auth_devices' ); }
	private static function challenge_table() { return SAUTH_Activator::table( 'risk_challenges' ); }
	private static function attempt_table() { return SAUTH_Activator::table( 'auth_attempts' ); }
}
