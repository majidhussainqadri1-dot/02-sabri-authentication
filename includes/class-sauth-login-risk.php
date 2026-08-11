<?php

defined( 'ABSPATH' ) || exit;

/**
 * Password-login risk evaluation and File 00 step-up orchestration.
 *
 * Risk signals are deliberately coarse and privacy-minimized. A risk score is
 * never an authorization grant. File 00 remains the MFA and membership owner.
 */
final class SAUTH_Login_Risk {
	const CHALLENGE_TTL       = 300;
	const TRUST_TTL           = 90 * DAY_IN_SECONDS;
	const ATTEMPT_RETENTION   = 30 * DAY_IN_SECONDS;
	const CHALLENGE_THRESHOLD = 45;
	const HIGH_RISK_THRESHOLD = 80;

	public static function init() {
		add_action( 'admin_post_nopriv_sauth_login_risk_verify', array( __CLASS__, 'handle_challenge' ) );
		add_action( 'admin_post_sauth_login_risk_verify', array( __CLASS__, 'handle_challenge' ) );
		add_action( 'sauth_login_risk_cleanup', array( __CLASS__, 'cleanup' ) );
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'sauth_login_risk_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sauth_login_risk_cleanup' );
		}
	}

	/**
	 * Finish a successful password check or route to File 00 step-up.
	 * This method always redirects and exits.
	 */
	public static function complete_password_login( WP_User $user, $remember, $requested_destination, array $completion_state ) {
		$requested_destination = SA_Security::safe_redirect( $requested_destination, home_url( '/' ) );
		$risk = self::evaluate( $user->ID, $completion_state );

		if ( 'challenge' === $risk['action'] ) {
			$token = self::create_challenge( $user->ID, (bool) $remember, $requested_destination, $completion_state, $risk );
			if ( is_wp_error( $token ) ) {
				self::record_attempt( $user->ID, 'denied', 'challenge_store_failed', (int) $risk['score'] );
				wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'Additional verification is required, but the challenge could not be prepared. Please retry.' ) );
				exit;
			}
			wp_safe_redirect( add_query_arg( 'challenge', rawurlencode( $token ), SA_Security::page_url( 'risk_challenge' ) ) );
			exit;
		}

		if ( 'deny' === $risk['action'] ) {
			self::record_attempt( $user->ID, 'denied', (string) $risk['reason_code'], (int) $risk['score'] );
			SAUTH_Event_Outbox::emit(
				'AccountAuthenticationFailed.v1',
				$user->ID,
				$user->ID,
				array( 'method' => 'password', 'reason' => (string) $risk['reason_code'], 'risk' => self::risk_band( (int) $risk['score'] ) ),
				'security'
			);
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'This sign-in requires stronger account verification. Complete two-factor setup or contact support.' ) );
			exit;
		}

		self::establish_session( $user, (bool) $remember, $requested_destination, $completion_state, $risk );
	}

	/**
	 * Render the privacy-safe step-up page.
	 */
	public static function render() {
		$token = isset( $_GET['challenge'] ) ? sanitize_text_field( wp_unslash( $_GET['challenge'] ) ) : '';
		$valid = '' !== $token && self::challenge_exists( $token );
		ob_start();
		?>
		<main class="sa-auth-shell">
			<section class="sa-auth-card" aria-labelledby="sauth-risk-title">
				<div class="sa-brand-mark">SH</div><span class="sa-kicker">Additional verification</span>
				<h1 id="sauth-risk-title">Confirm This Sign-In</h1>
				<p class="sa-intro">A new device or unusual sign-in pattern requires the second factor owned by Membership Core. No precise location or raw device identifier is displayed.</p>
				<?php include SA_DIR . 'templates/partials/notice.php'; ?>
				<?php if ( $valid ) : ?>
					<form class="sa-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="sauth_login_risk_verify">
						<input type="hidden" name="challenge" value="<?php echo esc_attr( $token ); ?>">
						<?php wp_nonce_field( 'sauth_login_risk_verify_' . $token, 'sauth_nonce' ); ?>
						<label for="sauth-risk-code">Authenticator or recovery code</label>
						<input id="sauth-risk-code" type="text" name="second_factor" inputmode="numeric" autocomplete="one-time-code" minlength="6" maxlength="32" required>
						<button class="sa-primary-button" type="submit">Verify and Continue</button>
					</form>
				<?php else : ?>
					<div class="sa-notice sa-notice-error" role="status">This sign-in challenge is invalid, expired or already used. Start sign-in again.</div>
					<a class="sa-primary-button" href="<?php echo esc_url( SA_Security::page_url( 'login' ) ); ?>">Return to Log In</a>
				<?php endif; ?>
			</section>
		</main>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle_challenge() {
		global $wpdb;
		$token = isset( $_POST['challenge'] ) ? sanitize_text_field( wp_unslash( $_POST['challenge'] ) ) : '';
		check_admin_referer( 'sauth_login_risk_verify_' . $token, 'sauth_nonce' );

		if ( SA_Security::rate_limited( 'login_risk_challenge', 6, 900, self::token_hash( $token ) ) ) {
			self::invalidate_challenge( $token, 'rate_limited' );
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'Too many verification attempts. Start sign-in again.' ) );
			exit;
		}

		$table = self::challenge_table();
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE token_hash = %s AND status = 'pending'", self::token_hash( $token ) ),
			ARRAY_A
		);
		if ( ! is_array( $row ) || empty( $row['expires_at'] ) || strtotime( (string) $row['expires_at'] ) <= time() ) {
			self::invalidate_challenge( $token, 'expired' );
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'This verification challenge expired. Start sign-in again.' ) );
			exit;
		}
		if ( ! hash_equals( (string) $row['fingerprint_hash'], SA_Security::client_fingerprint() ) ) {
			self::invalidate_challenge( $token, 'fingerprint_changed' );
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'The sign-in context changed. Start sign-in again.' ) );
			exit;
		}

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET attempts = attempts + 1, updated_at = %s WHERE id = %d AND status = 'pending'",
				current_time( 'mysql', true ),
				absint( $row['id'] )
			)
		);
		if ( absint( $row['attempts'] ) >= 7 ) {
			self::invalidate_challenge( $token, 'attempts_exhausted' );
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'This verification challenge can no longer be used.' ) );
			exit;
		}

		$user = get_userdata( absint( $row['user_id'] ) );
		$code = isset( $_POST['second_factor'] ) ? sanitize_text_field( wp_unslash( $_POST['second_factor'] ) ) : '';
		if ( ! $user instanceof WP_User || ! SA_Membership_Adapter::two_factor_enabled( $user->ID ) ) {
			self::invalidate_challenge( $token, 'subject_ineligible' );
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'The account is not ready for this verification method.' ) );
			exit;
		}

		$assurance = SA_Authentication_Assurance::verify_and_record(
			$user->ID,
			$code,
			array(
				'purpose' => 'clinical_sign_in',
				'scope'   => 'password-login-risk|' . (string) $row['public_id'],
				'trace_id'=> (string) $row['public_id'],
			)
		);
		if ( 'valid' !== ( $assurance['result'] ?? '' ) ) {
			self::record_attempt( $user->ID, 'challenged', 'second_factor_invalid', absint( $row['risk_score'] ) );
			$url = SA_Security::message_url( 'risk_challenge', 'error', 'The Authenticator or recovery code was not accepted.', array( 'challenge' => $token ) );
			wp_safe_redirect( $url );
			exit;
		}

		$claimed = $wpdb->update(
			$table,
			array( 'status' => 'consumed', 'consumed_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ), 'token_hash' => str_repeat( '0', 64 ) ),
			array( 'id' => absint( $row['id'] ), 'status' => 'pending' ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);
		if ( 1 !== (int) $claimed ) {
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'This verification challenge was already completed or revoked.' ) );
			exit;
		}

		$completion = json_decode( (string) $row['completion_json'], true );
		$completion = is_array( $completion ) ? $completion : array();
		$risk = array( 'action' => 'allow', 'score' => absint( $row['risk_score'] ), 'reason_code' => 'step_up_verified' );
		self::establish_session( $user, ! empty( $row['remember_session'] ), (string) $row['destination'], $completion, $risk );
	}

	/**
	 * Record a provider-authenticated login after its own step-up has succeeded.
	 */
	public static function record_successful_login( $user_id, $method = 'provider', $score = 0 ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}
		self::upsert_device( $user_id, max( 0, absint( $score ) ), 'trusted' );
		self::record_attempt( $user_id, 'success', sanitize_key( $method ), absint( $score ) );
		return true;
	}

	public static function record_failure( $user_id, $reason, $score = 0 ) {
		self::record_attempt( absint( $user_id ), 'failure', sanitize_key( (string) $reason ), absint( $score ) );
	}

	/**
	 * @return array{action:string,score:int,reason_code:string}
	 */
	public static function evaluate( $user_id, array $completion_state = array() ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$score   = 0;
		$reasons = array();
		if ( ! $user_id ) {
			return array( 'action' => 'deny', 'score' => 100, 'reason_code' => 'subject_invalid' );
		}

		$device = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::device_table() . " WHERE user_id = %d AND fingerprint_hash = %s AND status = 'trusted'",
				$user_id,
				SA_Security::client_fingerprint()
			),
			ARRAY_A
		);
		if ( ! is_array( $device ) || empty( $device['last_seen_at'] ) || strtotime( (string) $device['last_seen_at'] ) < time() - self::TRUST_TTL ) {
			$score += 50;
			$reasons[] = 'new_device';
		}

		$network_hash = self::network_hash();
		$known_network = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . self::device_table() . " WHERE user_id = %d AND network_hash = %s AND status = 'trusted'",
				$user_id,
				$network_hash
			)
		);
		if ( 0 === (int) $known_network ) {
			$score += 20;
			$reasons[] = 'new_network';
		}

		$recent_failures = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . self::attempt_table() . " WHERE user_id = %d AND result IN ('failure','denied') AND created_at >= %s",
				$user_id,
				gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS )
			)
		);
		if ( (int) $recent_failures >= 5 ) {
			$score += 35;
			$reasons[] = 'recent_failures';
		}

		if ( ! SAUTH_Provider_Health::allow_request( 'membership' ) ) {
			$score += 30;
			$reasons[] = 'assurance_provider_degraded';
		}
		$score = min( 100, $score );
		$reason = empty( $reasons ) ? 'known_context' : implode( '_', $reasons );
		if ( $score < self::CHALLENGE_THRESHOLD ) {
			return array( 'action' => 'allow', 'score' => $score, 'reason_code' => $reason );
		}

		if ( SA_Membership_Adapter::two_factor_enabled( $user_id ) && SA_Authentication_Assurance::provider_available() && SAUTH_Provider_Health::allow_request( 'membership' ) ) {
			return array( 'action' => 'challenge', 'score' => $score, 'reason_code' => $reason );
		}

		$missing = array_map( 'sanitize_key', (array) ( $completion_state['missing_steps'] ?? array() ) );
		if ( $score < self::HIGH_RISK_THRESHOLD && ( in_array( 'two_factor', $missing, true ) || in_array( 'mfa', $missing, true ) ) ) {
			return array( 'action' => 'allow', 'score' => $score, 'reason_code' => 'restricted_completion_session' );
		}
		return array( 'action' => 'deny', 'score' => $score, 'reason_code' => 'step_up_unavailable' );
	}

	public static function cleanup() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . self::challenge_table() . " WHERE expires_at < %s OR (status <> 'pending' AND updated_at < %s)", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM " . self::attempt_table() . " WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - self::ATTEMPT_RETENTION ) ) );
		$wpdb->query( $wpdb->prepare( "UPDATE " . self::device_table() . " SET status = 'expired', updated_at = %s WHERE status = 'trusted' AND last_seen_at < %s", current_time( 'mysql', true ), gmdate( 'Y-m-d H:i:s', time() - self::TRUST_TTL ) ) );
	}

	private static function establish_session( WP_User $user, $remember, $requested_destination, array $completion_state, array $risk ) {
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, (bool) $remember, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
		self::upsert_device( $user->ID, absint( $risk['score'] ?? 0 ), 'trusted' );
		self::record_attempt( $user->ID, 'success', sanitize_key( (string) ( $risk['reason_code'] ?? 'authenticated' ) ), absint( $risk['score'] ?? 0 ) );

		$resolution = SAUTH_Completion_Resolver::resolve( $user->ID, $requested_destination, $completion_state );
		$destination = 'allow' === ( $resolution['result'] ?? '' ) ? (string) $resolution['destination'] : SA_Membership_Adapter::profile_url();
		SAUTH_Event_Outbox::emit(
			'AccountAuthenticationSucceeded.v1',
			$user->ID,
			$user->ID,
			array(
				'method'              => 'password',
				'risk'                => self::risk_band( absint( $risk['score'] ?? 0 ) ),
				'step_up'             => 'step_up_verified' === ( $risk['reason_code'] ?? '' ),
				'completion_required' => ! empty( $resolution['missing_steps'] ),
			),
			'security'
		);
		SA_Membership_Adapter::audit( 'password_authentication_succeeded', $user->ID, array( 'risk' => self::risk_band( absint( $risk['score'] ?? 0 ) ) ) );
		wp_safe_redirect( SA_Security::safe_redirect( $destination, home_url( '/' ) ) );
		exit;
	}

	/**
	 * @return string|WP_Error
	 */
	private static function create_challenge( $user_id, $remember, $destination, array $completion, array $risk ) {
		global $wpdb;
		$token = SA_Security::random_token( 32 );
		if ( strlen( $token ) < 64 ) {
			return new WP_Error( 'sauth_risk_token_failed', 'Challenge token generation failed.' );
		}
		$public_id = strtolower( wp_generate_uuid4() );
		$now = current_time( 'mysql', true );
		$encoded = wp_json_encode( self::sanitize_completion( $completion ) );
		if ( false === $encoded ) {
			return new WP_Error( 'sauth_risk_state_failed', 'Challenge state encoding failed.' );
		}
		$stored = $wpdb->insert(
			self::challenge_table(),
			array(
				'public_id'         => $public_id,
				'token_hash'        => self::token_hash( $token ),
				'user_id'           => absint( $user_id ),
				'fingerprint_hash'  => SA_Security::client_fingerprint(),
				'risk_score'        => absint( $risk['score'] ?? 0 ),
				'reason_code'       => sanitize_key( (string) ( $risk['reason_code'] ?? 'risk_challenge' ) ),
				'remember_session'  => $remember ? 1 : 0,
				'destination'       => SA_Security::safe_redirect( $destination, home_url( '/' ) ),
				'completion_json'   => $encoded,
				'status'            => 'pending',
				'attempts'          => 0,
				'expires_at'        => gmdate( 'Y-m-d H:i:s', time() + self::CHALLENGE_TTL ),
				'consumed_at'       => null,
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
		if ( false === $stored ) {
			return new WP_Error( 'sauth_risk_store_failed', 'Challenge storage failed.' );
		}
		self::record_attempt( $user_id, 'challenged', (string) ( $risk['reason_code'] ?? 'risk_challenge' ), absint( $risk['score'] ?? 0 ) );
		return $token;
	}

	private static function challenge_exists( $token ) {
		global $wpdb;
		if ( strlen( (string) $token ) < 64 ) {
			return false;
		}
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM " . self::challenge_table() . " WHERE token_hash = %s AND status = 'pending' AND expires_at > %s",
				self::token_hash( $token ),
				current_time( 'mysql', true )
			)
		);
		return 1 === (int) $count;
	}

	private static function invalidate_challenge( $token, $reason ) {
		global $wpdb;
		if ( '' === (string) $token ) {
			return;
		}
		$wpdb->update(
			self::challenge_table(),
			array( 'status' => sanitize_key( (string) $reason ), 'updated_at' => current_time( 'mysql', true ), 'token_hash' => str_repeat( '0', 64 ) ),
			array( 'token_hash' => self::token_hash( $token ) ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);
	}

	private static function upsert_device( $user_id, $risk_score, $status ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$table = self::device_table();
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT public_id FROM {$table} WHERE user_id = %d AND fingerprint_hash = %s", absint( $user_id ), SA_Security::client_fingerprint() )
		);
		$data = array(
			'user_id'          => absint( $user_id ),
			'fingerprint_hash' => SA_Security::client_fingerprint(),
			'network_hash'     => self::network_hash(),
			'device_label'     => self::device_label(),
			'network_label'    => self::network_label(),
			'status'           => sanitize_key( $status ),
			'risk_score'       => min( 100, absint( $risk_score ) ),
			'last_seen_at'     => $now,
			'last_login_at'    => $now,
			'updated_at'       => $now,
		);
		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'public_id' => (string) $existing ), array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ), array( '%s' ) );
			return;
		}
		$data['public_id'] = strtolower( wp_generate_uuid4() );
		$data['first_seen_at'] = $now;
		$wpdb->insert( $table, $data, array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ) );
	}

	private static function record_attempt( $user_id, $result, $reason, $risk_score ) {
		global $wpdb;
		$wpdb->insert(
			self::attempt_table(),
			array(
				'public_id'        => strtolower( wp_generate_uuid4() ),
				'user_id'          => absint( $user_id ),
				'fingerprint_hash' => SA_Security::client_fingerprint(),
				'network_hash'     => self::network_hash(),
				'result'           => sanitize_key( (string) $result ),
				'reason_code'      => sanitize_key( (string) $reason ),
				'risk_score'       => min( 100, absint( $risk_score ) ),
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	private static function sanitize_completion( array $completion ) {
		return array(
			'result'        => sanitize_key( (string) ( $completion['result'] ?? 'unknown' ) ),
			'reason_code'   => sanitize_key( (string) ( $completion['reason_code'] ?? '' ) ),
			'missing_steps' => array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $completion['missing_steps'] ?? array() ) ) ) ) ),
			'next_route'    => SA_Security::safe_redirect( (string) ( $completion['next_route'] ?? '' ), '' ),
		);
	}

	private static function risk_band( $score ) {
		$score = absint( $score );
		return $score >= self::HIGH_RISK_THRESHOLD ? 'high' : ( $score >= self::CHALLENGE_THRESHOLD ? 'medium' : 'low' );
	}

	private static function device_label() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
		$browser = false !== strpos( $ua, 'firefox' ) ? 'Firefox' : ( false !== strpos( $ua, 'edg' ) ? 'Edge' : ( false !== strpos( $ua, 'chrome' ) ? 'Chrome' : ( false !== strpos( $ua, 'safari' ) ? 'Safari' : 'Web browser' ) ) );
		$device = false !== strpos( $ua, 'mobile' ) ? 'mobile device' : 'computer';
		return $browser . ' on a ' . $device;
	}

	private static function network_label() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );
			return $parts[0] . '.' . $parts[1] . '.x.x';
		}
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$parts = explode( ':', $ip );
			return implode( ':', array_slice( $parts, 0, 2 ) ) . ':…';
		}
		return 'Private';
	}

	private static function network_hash() {
		return hash_hmac( 'sha256', self::network_label(), wp_salt( 'secure_auth' ) );
	}

	private static function token_hash( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	private static function device_table() {
		global $wpdb;
		return $wpdb->prefix . 'sa_auth_devices';
	}

	private static function challenge_table() {
		global $wpdb;
		return $wpdb->prefix . 'sa_auth_risk_challenges';
	}

	private static function attempt_table() {
		global $wpdb;
		return $wpdb->prefix . 'sa_auth_attempts';
	}
}
