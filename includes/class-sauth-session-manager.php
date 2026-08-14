<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02 session-entry projection and revocation controls.
 *
 * WordPress remains the credential-session implementation. File 02 stores only
 * an HMAC binding and opaque public identifier, never a raw session token.
 */
final class SAUTH_Session_Manager {
	const CLEANUP_HOOK      = 'sauth_session_registry_cleanup';
	const HISTORY_RETENTION = 30 * DAY_IN_SECONDS;
	const MAX_LIST          = 50;

	public static function init() {
		add_action( 'admin_post_sauth_revoke_session', array( __CLASS__, 'revoke_one' ) );
		add_action( 'admin_post_sauth_revoke_other_sessions', array( __CLASS__, 'revoke_others' ) );
		add_action( 'admin_post_sauth_revoke_all_sessions', array( __CLASS__, 'revoke_all' ) );
		add_action( 'set_logged_in_cookie', array( __CLASS__, 'register_cookie' ), 30, 6 );
		add_filter( 'determine_current_user', array( __CLASS__, 'deny_revoked_session' ), 99 );
		add_action( 'init', array( __CLASS__, 'ensure_authenticated_projection' ), 1 );
		add_action( 'init', array( __CLASS__, 'touch_current' ), 30 );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public static function render() {
		if ( ! is_user_logged_in() ) {
			return '<div class="sa-auth-shell"><section class="sa-auth-card"><h1>Account access required</h1><p>Sign in to review active sessions.</p><a class="sa-primary-button" href="' . esc_url( SA_Membership_Adapter::login_url( SA_Security::page_url( 'sessions' ) ) ) . '">Log In</a></section></div>';
		}
		$user_id = get_current_user_id();
		self::ensure_current_registered( $user_id );
		$sessions = self::list_for_user( $user_id );
		$session_list_unavailable = is_wp_error( $sessions );
		if ( $session_list_unavailable ) { $sessions = array(); }
		$current_hash = self::current_token_hash();
		$active_count = 0;
		foreach ( $sessions as $session ) { if ( 'active' === $session['status'] ) { $active_count++; } }
		ob_start();
		?>
		<main class="sa-auth-shell">
			<section class="sa-auth-card sa-auth-wide" aria-labelledby="sauth-sessions-title">
				<span class="sa-kicker">Account security</span><h1 id="sauth-sessions-title">Active Sessions</h1>
				<p class="sa-intro">Review your own sessions and revoke an unfamiliar sign-in. Raw tokens, full IP addresses and precise location are never displayed.</p>
				<?php include SA_DIR . 'templates/partials/notice.php'; ?>
				<div class="sa-session-summary" role="status"><strong>Total active sessions:</strong> <?php echo esc_html( $session_list_unavailable ? 'Unavailable' : (string) $active_count ); ?></div>
				<div class="sa-session-list">
				<?php if ( $session_list_unavailable ) : ?><div class="sa-notice sa-notice-error" role="alert">Session evidence is temporarily unavailable. No zero-session claim is being made; reload before taking security action.</div><?php elseif ( empty( $sessions ) ) : ?><p>No session projections are available. The current session remains governed by WordPress.</p><?php endif; ?>
				<?php foreach ( $sessions as $session ) : $is_current = '' !== $current_hash && hash_equals( $current_hash, (string) $session['token_hash'] ); ?>
					<article class="sa-session-card" aria-label="<?php echo esc_attr( $is_current ? 'Current session' : 'Account session' ); ?>">
						<h2><?php echo $is_current ? 'Current session' : 'Other session'; ?></h2>
						<dl class="sa-definition">
							<div><dt>Device</dt><dd><?php echo esc_html( $session['device_label'] ); ?></dd></div>
							<div><dt>Last activity</dt><dd><?php echo esc_html( self::display_time( $session['last_seen_at'] ) ); ?></dd></div>
							<div><dt>Network</dt><dd><?php echo esc_html( $session['network_label'] ); ?></dd></div>
							<div><dt>Risk projection</dt><dd><?php echo esc_html( ucfirst( $session['risk_level'] ) ); ?></dd></div>
							<div><dt>Status</dt><dd><?php echo esc_html( ucfirst( $session['status'] ) ); ?></dd></div>
						</dl>
						<?php if ( ! $is_current && 'active' === $session['status'] ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="sauth_revoke_session"><input type="hidden" name="session_id" value="<?php echo esc_attr( $session['public_id'] ); ?>"><?php wp_nonce_field( 'sauth_revoke_session_' . $session['public_id'], 'sauth_nonce' ); ?><button class="sa-secondary-button" type="submit">Revoke This Session</button></form>
						<?php elseif ( $is_current ) : ?><p><strong>Protected current session:</strong> use “Sign out everywhere” to revoke it.</p><?php endif; ?>
					</article>
				<?php endforeach; ?>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="sauth_revoke_other_sessions"><?php wp_nonce_field( 'sauth_revoke_other_sessions', 'sauth_nonce' ); ?><button class="sa-secondary-button" type="submit">Revoke All Other Sessions</button></form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="sauth_revoke_all_sessions"><?php wp_nonce_field( 'sauth_revoke_all_sessions', 'sauth_nonce' ); ?><button class="sa-text-link" type="submit">Sign Out Everywhere</button></form>
			</section>
		</main>
		<?php
		return (string) ob_get_clean();
	}

	/** Register the newly issued WordPress session and destroy it if evidence cannot persist. */
	public static function register_cookie( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$token   = (string) $token;
		if ( ! $user_id || '' === $token || 'logged_in' !== (string) $scheme ) { return; }
		$token_hash = self::token_hash( $token );
		$now = current_time( 'mysql', true );
		$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT public_id FROM ' . self::table() . ' WHERE user_id=%d AND token_hash=%s', $user_id, $token_hash ) );
		$data = array(
			'user_id' => $user_id,
			'token_hash' => $token_hash,
			'device_hash' => SA_Security::client_fingerprint(),
			'device_label' => self::generalize_user_agent( self::request_user_agent() ),
			'network_label' => self::generalize_ip( self::request_ip() ),
			'risk_level' => self::current_risk_level( $user_id ),
			'status' => 'active',
			'last_seen_at' => $now,
			'expires_at' => gmdate( 'Y-m-d H:i:s', max( time() + HOUR_IN_SECONDS, absint( $expiration ) ) ),
			'revoked_at' => null,
			'updated_at' => $now,
		);
		$result = false;
		if ( $existing ) {
			$result = $wpdb->update( self::table(), $data, array( 'public_id' => (string) $existing, 'user_id' => $user_id ), array( '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ), array( '%s','%d' ) );
			$public_id = (string) $existing;
		} else {
			$public_id = strtolower( wp_generate_uuid4() );
			$data['public_id'] = $public_id; $data['created_at'] = $now;
			$result = $wpdb->insert( self::table(), $data, array( '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s' ) );
		}
		$post = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id,token_hash,status FROM ' . self::table() . ' WHERE public_id=%s', $public_id ), ARRAY_A );
		if ( false === $result || ! is_array( $post ) || absint( $post['user_id'] ?? 0 ) !== $user_id || 'active' !== (string) ( $post['status'] ?? '' ) || ! hash_equals( $token_hash, (string) ( $post['token_hash'] ?? '' ) ) ) {
			if ( class_exists( 'WP_Session_Tokens' ) ) { WP_Session_Tokens::get_instance( $user_id )->destroy( $token ); }
			SA_Membership_Adapter::audit( 'session_projection_store_failed', $user_id );
		}
	}

	/** Deny a registry-revoked token after WordPress resolved the candidate user. */
	public static function deny_revoked_session( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) { return $user_id; }
		$token = (string) wp_get_session_token();
		if ( '' === $token ) { return $user_id; }
		$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . self::table() . ' WHERE user_id=%d AND token_hash=%s', $user_id, self::token_hash( $token ) ) );
		if ( '' !== (string) $wpdb->last_error ) {
			SA_Membership_Adapter::audit( 'session_registry_read_failed', $user_id );
			return 0;
		}
		if ( null === $status ) {
			return $user_id; // Legitimate pre-registry/upgrade session; reconciled on init.
		}
		return 'active' === (string) $status ? $user_id : 0;
	}

	/** Lazily reconcile a legitimate pre-registry/upgrade session. */
	public static function ensure_authenticated_projection() {
		if ( is_user_logged_in() ) { self::ensure_current_registered( get_current_user_id() ); }
	}

	public static function touch_current() {
		global $wpdb;
		if ( ! is_user_logged_in() || 1 !== wp_rand( 1, 10 ) ) { return; }
		$user_id = get_current_user_id(); $hash = self::current_token_hash();
		if ( '' === $hash ) { return; }
		$wpdb->update( self::table(), array( 'last_seen_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ), array( 'user_id' => $user_id, 'token_hash' => $hash, 'status' => 'active' ), array( '%s','%s' ), array( '%d','%s','%s' ) );
	}

	public static function revoke_one() {
		global $wpdb;
		if ( ! is_user_logged_in() ) { auth_redirect(); }
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		if ( strlen( $session_id ) > 64 || ! self::valid_uuid( $session_id ) ) { self::redirect( 'error', 'The selected session was not found.' ); }
		check_admin_referer( 'sauth_revoke_session_' . $session_id, 'sauth_nonce' );
		$user_id = get_current_user_id();
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE public_id=%s AND user_id=%d', $session_id, $user_id ), ARRAY_A );
		if ( ! is_array( $row ) ) { self::redirect( 'error', 'The selected session was not found.' ); }
		$current = self::current_token_hash();
		if ( '' !== $current && hash_equals( $current, (string) $row['token_hash'] ) ) { self::redirect( 'error', 'The current session is protected. Use Sign Out Everywhere to revoke it.' ); }
		if ( 'active' !== (string) $row['status'] ) { self::redirect( 'success', 'The selected session was already inactive.' ); }
		$changed = self::mark_revoked( $user_id, array( (string) $row['public_id'] ), 'user_selected_session' );
		$status = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . self::table() . ' WHERE public_id=%s AND user_id=%d', $session_id, $user_id ) );
		if ( $changed < 1 || 'revoked' !== $status ) { self::redirect( 'error', 'The selected session could not be revoked.' ); }
		SAUTH_Event_Outbox::emit( 'AuthSessionRevoked.v1', $user_id, $user_id, array( 'scope' => 'single_session', 'reason' => 'user_request', 'session_id' => $session_id ), 'security' );
		SA_Membership_Adapter::audit( 'authentication_session_revoked', $user_id, array( 'scope' => 'single_session' ) );
		self::redirect( 'success', 'The selected session was revoked.' );
	}

	public static function revoke_others() {
		global $wpdb;
		if ( ! is_user_logged_in() ) { auth_redirect(); }
		check_admin_referer( 'sauth_revoke_other_sessions', 'sauth_nonce' );
		$user_id = get_current_user_id(); $token = (string) wp_get_session_token(); $current = self::current_token_hash();
		if ( '' === $token || '' === $current || ! class_exists( 'WP_Session_Tokens' ) ) { self::redirect( 'error', 'The current session could not be verified.' ); }
		$now = current_time( 'mysql', true );
		$db_result = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . " SET status='revoked', revoked_at=%s, revocation_reason=%s, updated_at=%s WHERE user_id=%d AND status='active' AND token_hash<>%s", $now, 'user_revoke_others', $now, $user_id, $current ) );
		WP_Session_Tokens::get_instance( $user_id )->destroy_others( $token );
		$remaining = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE user_id=%d AND status='active' AND token_hash<>%s", $user_id, $current ) );
		if ( false === $db_result || '' !== (string) $wpdb->last_error || $remaining > 0 ) { self::redirect( 'error', 'Other WordPress sessions were revoked, but session evidence could not be reconciled completely. Reload and review again.' ); }
		SAUTH_Event_Outbox::emit( 'AuthSessionRevoked.v1', $user_id, $user_id, array( 'scope' => 'other_sessions', 'reason' => 'user_request' ), 'security' );
		SA_Membership_Adapter::audit( 'authentication_sessions_revoked', $user_id, array( 'scope' => 'others' ) );
		self::redirect( 'success', 'All other sessions were revoked.' );
	}

	public static function revoke_all() {
		if ( ! is_user_logged_in() ) { auth_redirect(); }
		check_admin_referer( 'sauth_revoke_all_sessions', 'sauth_nonce' );
		$user_id = get_current_user_id();
		$revoked = self::revoke_user_sessions( $user_id, 'user_request' );
		if ( ! $revoked ) { self::redirect( 'error', 'All WordPress sessions could not be revoked safely.' ); }
		SAUTH_Event_Outbox::emit( 'AuthSessionRevoked.v1', $user_id, $user_id, array( 'scope' => 'all_sessions', 'reason' => 'user_request' ), 'security' );
		SA_Membership_Adapter::audit( 'authentication_sessions_revoked', $user_id, array( 'scope' => 'all' ) );
		wp_clear_auth_cookie();
		wp_safe_redirect( SA_Security::message_url( 'login', 'success', 'You have been signed out on all devices.' ) );
		exit;
	}

	/** Revoke every session after a password/security event. */
	public static function revoke_user_sessions( $user_id, $reason = 'security_policy' ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id || ! class_exists( 'WP_Session_Tokens' ) ) { return false; }
		$now = current_time( 'mysql', true );
		$db_result = $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . " SET status='revoked', revoked_at=%s, revocation_reason=%s, updated_at=%s WHERE user_id=%d AND status='active'", $now, sanitize_key( (string) $reason ), $now, $user_id ) );
		WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		$remaining_raw = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . " WHERE user_id=%d AND status='active'", $user_id ) );
		if ( null === $remaining_raw || '' !== (string) $wpdb->last_error ) { return false; }
		return false !== $db_result && 0 === (int) $remaining_raw;
	}

	public static function cleanup() {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . " SET status='expired', updated_at=%s WHERE status='active' AND expires_at<%s", $now, $now ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . " WHERE status IN ('revoked','expired') AND updated_at<%s", gmdate( 'Y-m-d H:i:s', time() - self::HISTORY_RETENTION ) ) );
	}

	private static function list_for_user( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT public_id,token_hash,device_label,network_label,risk_level,status,last_seen_at,expires_at FROM ' . self::table() . " WHERE user_id=%d AND (status='active' OR updated_at>=%s) ORDER BY status='active' DESC,last_seen_at DESC LIMIT %d", absint( $user_id ), gmdate( 'Y-m-d H:i:s', time() - self::HISTORY_RETENTION ), self::MAX_LIST ), ARRAY_A );
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return new WP_Error( 'session_registry_unavailable', 'Session evidence could not be read safely.' );
		}
		return $rows;
	}

	private static function ensure_current_registered( $user_id ) {
		global $wpdb;
		$token = (string) wp_get_session_token();
		if ( '' === $token ) { return; }
		$hash = self::token_hash( $token );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id=%d AND token_hash=%s', absint( $user_id ), $hash ) );
		if ( '' !== (string) $wpdb->last_error ) { return; }
		if ( 0 === (int) $exists ) {
			$expiration = time() + YEAR_IN_SECONDS;
			if ( class_exists( 'WP_Session_Tokens' ) ) {
				$session = WP_Session_Tokens::get_instance( absint( $user_id ) )->get( $token );
				if ( is_array( $session ) && absint( $session['expiration'] ?? 0 ) > time() ) {
					$expiration = absint( $session['expiration'] );
				}
			}
			self::register_cookie( '', $expiration, $expiration, $user_id, 'logged_in', $token );
		}
	}

	private static function mark_revoked( $user_id, array $public_ids, $reason ) {
		global $wpdb; $changed = 0;
		foreach ( array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $public_ids ) ) ) ), 0, self::MAX_LIST ) as $public_id ) {
			if ( ! self::valid_uuid( $public_id ) ) { continue; }
			$result = $wpdb->update( self::table(), array( 'status' => 'revoked', 'revoked_at' => current_time( 'mysql', true ), 'revocation_reason' => sanitize_key( (string) $reason ), 'updated_at' => current_time( 'mysql', true ) ), array( 'user_id' => absint( $user_id ), 'public_id' => $public_id, 'status' => 'active' ), array( '%s','%s','%s','%s' ), array( '%d','%s','%s' ) );
			if ( 1 === (int) $result ) { $changed++; }
		}
		return $changed;
	}

	private static function current_risk_level( $user_id ) {
		global $wpdb;
		$score = $wpdb->get_var( $wpdb->prepare( 'SELECT risk_score FROM ' . SAUTH_Activator::table( 'auth_devices' ) . ' WHERE user_id=%d AND fingerprint_hash=%s ORDER BY last_seen_at DESC LIMIT 1', absint( $user_id ), SA_Security::client_fingerprint() ) );
		if ( null === $score || '' !== (string) $wpdb->last_error ) { return 'unknown'; }
		$score = absint( $score ); return $score >= 80 ? 'high' : ( $score >= 45 ? 'medium' : 'low' );
	}
	private static function current_token_hash() { $token = (string) wp_get_session_token(); return '' === $token ? '' : self::token_hash( $token ); }
	private static function token_hash( $token ) { return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) ); }
	private static function request_user_agent() { return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 ) : ''; }
	private static function request_ip() { return isset( $_SERVER['REMOTE_ADDR'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), 0, 64 ) : ''; }
	private static function generalize_user_agent( $ua ) { $ua = strtolower( sanitize_text_field( (string) $ua ) ); $browser = false !== strpos( $ua, 'firefox' ) ? 'Firefox' : ( false !== strpos( $ua, 'edg' ) ? 'Edge' : ( false !== strpos( $ua, 'chrome' ) ? 'Chrome' : ( false !== strpos( $ua, 'safari' ) ? 'Safari' : 'Web browser' ) ) ); return $browser . ' on a ' . ( false !== strpos( $ua, 'mobile' ) ? 'mobile device' : 'computer' ); }
	private static function generalize_ip( $ip ) { if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) { $p = explode( '.', $ip ); return $p[0] . '.' . $p[1] . '.x.x'; } if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) { $p = explode( ':', $ip ); return implode( ':', array_slice( $p, 0, 2 ) ) . ':…'; } return 'Private'; }
	private static function display_time( $value ) { $t = strtotime( (string) $value ); return false === $t ? 'Unknown' : wp_date( 'Y-m-d H:i', $t ); }
	private static function redirect( $type, $message ) { wp_safe_redirect( SA_Security::message_url( 'sessions', $type, $message ) ); exit; }
	private static function valid_uuid( $value ) { return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value ); }
	private static function table() { return SAUTH_Activator::table( 'auth_sessions' ); }
}
