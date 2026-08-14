<?php

defined( 'ABSPATH' ) || exit;

/**
 * Google OIDC authentication and explicit account-link orchestration.
 *
 * Google OIDC is an authentication factor owned by File 02. File 00 supplies
 * membership/identity eligibility only and no longer supplies TOTP/recovery
 * codes. Sensitive link/unlink mutations require a fresh File 02 passkey in
 * the current authenticated session.
 */
final class SA_Google_OAuth {
	const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
	const TOKENINFO_URL  = 'https://oauth2.googleapis.com/tokeninfo';
	const COOKIE_NAME    = 'sa_google_state_v2';
	const STATE_TTL      = 600;
	const MAX_INPUT      = 4096;

	public function hooks() {
		add_action( 'admin_post_nopriv_sa_google_start', array( $this, 'start' ) );
		add_action( 'admin_post_sa_google_start', array( $this, 'start' ) );
		add_action( 'admin_post_nopriv_sa_google_callback', array( $this, 'callback' ) );
		add_action( 'admin_post_sa_google_callback', array( $this, 'callback' ) );
		/* Historical verification endpoint remains fail-closed so old deep links do
		 * not fatal or silently reuse the retired File 00 MFA ceremony. */
		add_action( 'admin_post_nopriv_sa_google_verify', array( $this, 'verify_challenge' ) );
		add_action( 'admin_post_sa_google_verify', array( $this, 'verify_challenge' ) );
		add_action( 'admin_post_sa_google_unlink', array( $this, 'unlink' ) );
	}

	public static function callback_url() {
		return admin_url( 'admin-post.php?action=sa_google_callback', 'https' );
	}

	public static function configured() {
		return SA_Membership_Adapter::available()
			&& SAUTH_Account_Contract::provider_available()
			&& '1' === (string) get_option( 'sauth_google_enabled', get_option( 'sa_google_enabled', '0' ) )
			&& '' !== self::client_id()
			&& SA_Security::current_cipher_ready( (string) get_option( 'sauth_google_client_secret', get_option( 'sa_google_client_secret', '' ) ) )
			&& '' !== self::client_secret();
	}

	private static function client_id() {
		return trim( (string) get_option( 'sauth_google_client_id', get_option( 'sa_google_client_id', '' ) ) );
	}

	private static function client_secret() {
		return SA_Security::decrypt( (string) get_option( 'sauth_google_client_secret', get_option( 'sa_google_client_secret', '' ) ) );
	}

	public function start() {
		if ( SAUTH_Operations::safe_mode() || ! self::configured() || ! SAUTH_Provider_Health::available_for_ui( 'google' ) ) {
			$this->fail( 'Google authentication is temporarily unavailable.' );
		}
		if ( ! is_ssl() ) {
			$this->fail( 'Google authentication requires HTTPS.' );
		}

		$flow = isset( $_GET['flow'] ) ? sanitize_key( wp_unslash( $_GET['flow'] ) ) : 'login';
		$flow = in_array( $flow, array( 'login', 'link' ), true ) ? $flow : 'login';
		if ( 'link' === $flow ) {
			check_admin_referer( 'sa_google_link_start' );
			if ( ! is_user_logged_in() || ! SA_Membership_Adapter::can_use_google( get_current_user_id() ) ) {
				$this->fail( 'A current approved membership session is required before linking Google.', 'google_account' );
			}
			if ( ! self::fresh_passkey( get_current_user_id() ) ) {
				$this->fail( 'Verify a passkey in this session before linking Google.', 'google_account' );
			}
		} elseif ( is_user_logged_in() ) {
			wp_safe_redirect( SA_Security::page_url( 'google_account', SA_Membership_Adapter::profile_url() ) );
			exit;
		}

		$actor_id = get_current_user_id();
		if ( SA_Security::rate_limited( 'google_start', 10, 900, $flow . '|' . $actor_id ) ) {
			$this->fail( 'Please wait before trying Google authentication again.', 'link' === $flow ? 'google_account' : 'login' );
		}
		$state    = SA_Security::random_token( 32 );
		$nonce    = SA_Security::random_token( 32 );
		$verifier = SA_Security::random_token( 48 );
		if ( '' === $state || '' === $nonce || '' === $verifier ) {
			$this->fail( 'A secure Google authentication session could not be created.' );
		}
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
		$redirect  = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/' );
		$data = array(
			'flow'        => $flow,
			'actor_id'    => $actor_id,
			'nonce'       => $nonce,
			'verifier'    => $verifier,
			'redirect'    => SA_Security::safe_redirect( $redirect ),
			'fingerprint' => SA_Security::client_fingerprint(),
			'created'     => time(),
		);
		$key = $this->state_key( $state );
		set_transient( $key, $data, self::STATE_TTL );
		if ( get_transient( $key ) !== $data ) {
			$this->fail( 'The Google authentication session could not be stored safely.' );
		}
		if ( ! $this->state_cookie( $state, time() + self::STATE_TTL ) ) {
			delete_transient( $key );
			$this->fail( 'The secure Google state cookie could not be established.' );
		}

		$url = add_query_arg(
			array(
				'client_id'             => self::client_id(),
				'redirect_uri'          => self::callback_url(),
				'response_type'         => 'code',
				'scope'                 => 'openid email profile',
				'state'                 => $state,
				'nonce'                 => $nonce,
				'code_challenge'        => $challenge,
				'code_challenge_method' => 'S256',
				'prompt'                => 'select_account',
			),
			self::AUTH_ENDPOINT
		);
		wp_redirect( esc_url_raw( $url ) );
		exit;
	}

	public function callback() {
		$state  = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$cookie = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : '';
		if ( '' === $state || strlen( $state ) > 256 || '' === $cookie || strlen( $cookie ) > 256 ) {
			$this->fail( 'The Google authentication session could not be verified.' );
		}
		$key  = $this->state_key( $state );
		$data = get_transient( $key );
		if ( ! hash_equals( $cookie, $state ) || ! is_array( $data ) ) {
			$this->fail( 'The Google authentication session could not be verified.' );
		}
		delete_transient( $key );
		$this->state_cookie( '', time() - HOUR_IN_SECONDS );

		$created = absint( $data['created'] ?? 0 );
		if ( ! $created || $created > time() + 60 || $created < time() - self::STATE_TTL - 60 ) {
			$this->fail( 'The Google authentication session expired.' );
		}
		if ( empty( $data['fingerprint'] ) || ! hash_equals( (string) $data['fingerprint'], SA_Security::client_fingerprint() ) ) {
			$this->fail( 'The Google authentication session changed unexpectedly.' );
		}
		if ( isset( $_GET['error'] ) ) {
			$this->fail( 'Google authentication was cancelled or denied.' );
		}
		if ( SAUTH_Operations::safe_mode() || ! self::configured() ) {
			$this->fail( 'Google authentication is temporarily unavailable.' );
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( '' === $code || strlen( $code ) > self::MAX_INPUT ) {
			$this->fail( 'Google did not return a valid authorization code.' );
		}
		$secret = self::client_secret();
		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			array(
				'timeout'             => 15,
				'limit_response_size' => 1048576,
				'body' => array(
					'code'          => $code,
					'client_id'     => self::client_id(),
					'client_secret' => $secret,
					'redirect_uri'  => self::callback_url(),
					'grant_type'    => 'authorization_code',
					'code_verifier' => (string) ( $data['verifier'] ?? '' ),
				),
			)
		);
		$code = '';
		$secret = '';
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->fail( 'Google authentication could not be completed.' );
		}
		$token = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $token ) || empty( $token['id_token'] ) || strlen( (string) $token['id_token'] ) > 16384 ) {
			$this->fail( 'Google did not return a valid identity token.' );
		}
		$id_token = (string) $token['id_token'];
		$token = array();
		$verify = wp_remote_get(
			add_query_arg( 'id_token', $id_token, self::TOKENINFO_URL ),
			array( 'timeout' => 15, 'limit_response_size' => 1048576 )
		);
		$id_token = '';
		if ( is_wp_error( $verify ) || 200 !== wp_remote_retrieve_response_code( $verify ) ) {
			$this->fail( 'The Google identity token could not be validated.' );
		}
		$claims = json_decode( wp_remote_retrieve_body( $verify ), true );
		if ( ! $this->valid_claims( $claims, $data ) ) {
			$this->fail( 'The Google identity token failed validation.' );
		}

		$flow = isset( $data['flow'] ) && 'link' === $data['flow'] ? 'link' : 'login';
		if ( 'link' === $flow ) {
			$user = $this->link_target( $claims, $data );
			if ( is_wp_error( $user ) ) {
				$this->fail( $user->get_error_message(), 'google_account' );
			}
			if ( ! self::fresh_passkey( $user->ID ) ) {
				$this->fail( 'The passkey assurance expired while Google linking was in progress. Verify the passkey and start linking again.', 'google_account' );
			}
			$lock = $this->acquire_link_lock( (string) $claims['sub'] );
			if ( ! $lock ) {
				$this->fail( 'This Google account is being linked in another request. Wait briefly and try again.', 'google_account' );
			}
			try {
				$existing = $this->linked_user( (string) $claims['sub'], false );
				if ( is_wp_error( $existing ) && 'sa_google_unlinked' !== $existing->get_error_code() ) {
					$this->fail( 'This Google account link requires administrative review.', 'google_account' );
				}
				if ( $existing instanceof WP_User && $existing->ID !== $user->ID ) {
					$this->fail( 'This Google account is already linked to another membership.', 'google_account' );
				}
				if ( ! $this->store_link( $user->ID, $claims, true ) ) {
					$this->fail( 'The Google account link could not be stored safely.', 'google_account' );
				}
			} finally {
				$this->release_link_lock( $lock );
			}
			SAUTH_Event_Outbox::emit( 'GoogleAccountLinked.v1', $user->ID, $user->ID, array( 'method' => 'google_oidc' ), 'security' );
			SA_Membership_Adapter::audit( 'google_account_linked', $user->ID );
			wp_safe_redirect( SA_Security::message_url( 'google_account', 'success', 'Google has been linked to your account.' ) );
			exit;
		}

		$user = $this->linked_user( (string) $claims['sub'], true );
		if ( is_wp_error( $user ) ) {
			$this->fail( $user->get_error_message(), 'login' );
		}
		if ( ! $user instanceof WP_User
			|| 0 !== strcasecmp( (string) $user->user_email, (string) $claims['email'] )
			|| ! SA_Membership_Adapter::can_use_google( $user->ID ) ) {
			$this->fail( 'The linked membership is not eligible for Google sign-in.', 'login' );
		}
		$completion = SAUTH_Account_Contract::completion_state( $user->ID, array( 'purpose' => 'google_sign_in' ) );
		if ( ! is_array( $completion ) || 'allow' !== ( $completion['result'] ?? '' ) ) {
			$this->fail( 'Account completion status could not be verified for Google sign-in.', 'login' );
		}
		$risk = SAUTH_Login_Risk::evaluate( $user->ID, $completion );
		if ( 'challenge' === ( $risk['action'] ?? '' ) || 'deny' === ( $risk['action'] ?? '' ) ) {
			SAUTH_Login_Risk::record_failure( $user->ID, 'google_' . sanitize_key( (string) ( $risk['reason_code'] ?? 'risk_rejected' ) ), absint( $risk['score'] ?? 100 ) );
			SAUTH_Event_Outbox::emit( 'AccountAuthenticationFailed.v1', $user->ID, $user->ID, array( 'method' => 'google_oidc', 'reason' => 'passkey_step_up_required', 'risk_score' => absint( $risk['score'] ?? 100 ) ), 'security' );
			$this->fail( 'This Google sign-in needs stronger verification for the current device or network. Use your registered File 02 passkey to sign in.', 'login' );
		}
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		self::safe_observer_action( 'wp_login', array( $user->user_login, $user ) );
		SAUTH_Login_Risk::record_successful_login( $user->ID, 'google', absint( $risk['score'] ?? 0 ) );
		SAUTH_Event_Outbox::emit( 'AccountAuthenticationSucceeded.v1', $user->ID, $user->ID, array( 'method' => 'google_oidc', 'risk_score' => absint( $risk['score'] ?? 0 ) ), 'security' );
		update_user_meta( $user->ID, '_sauth_google_last_login_at', current_time( 'mysql', true ) );
		update_user_meta( $user->ID, '_sa_google_last_login_at', current_time( 'mysql', true ) );
		SA_Membership_Adapter::audit( 'google_login_success', $user->ID );
		$requested = isset( $data['redirect'] ) ? SA_Security::safe_redirect( $data['redirect'], SA_Membership_Adapter::profile_url() ) : SA_Membership_Adapter::profile_url();
		$resolution = SAUTH_Completion_Resolver::resolve( $user->ID, $requested, $completion );
		$destination = SA_Membership_Adapter::profile_url();
		if ( 'allow' === ( $resolution['result'] ?? '' ) || 'completion_loop_prevented' === ( $resolution['reason_code'] ?? '' ) ) {
			$destination = SA_Security::safe_redirect( (string) ( $resolution['destination'] ?? '' ), $destination );
		}
		wp_safe_redirect( $destination );
		exit;
	}

	/** Retired File 00 MFA challenge endpoint. */
	public function verify_challenge() {
		$token = isset( $_REQUEST['challenge'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['challenge'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $token && strlen( $token ) <= 256 ) {
			delete_transient( $this->challenge_key( $token ) );
		}
		$this->fail( 'This older verification step has been retired. Start Google authentication again.' );
	}

	public function unlink() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'sa_google_unlink', 'sa_nonce' );
		$user_id = get_current_user_id();
		if ( SA_Security::rate_limited( 'google_unlink', 5, 900, (string) $user_id ) || ! self::fresh_passkey( $user_id ) ) {
			wp_safe_redirect( SA_Security::message_url( 'google_account', 'error', 'Verify a passkey in this session before unlinking Google.' ) );
			exit;
		}
		foreach ( self::google_meta_keys() as $key ) {
			delete_user_meta( $user_id, $key );
		}
		$remaining = array();
		foreach ( self::google_meta_keys() as $key ) {
			if ( metadata_exists( 'user', $user_id, $key ) ) { $remaining[] = $key; }
		}
		if ( ! empty( $remaining ) ) {
			/* Fail closed even if the underlying store partially rejected deletion. */
			self::contain_linkage_failure( $user_id, 'google_account_unlink_incomplete' );
			SA_Membership_Adapter::audit( 'google_account_unlink_incomplete', $user_id, array( 'remaining_count' => count( $remaining ) ) );
			wp_safe_redirect( SA_Security::message_url( 'google_account', 'error', 'Google unlinking could not be completed safely. All sessions were revoked; sign in again and contact support.' ) );
			exit;
		}
		if ( class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $user_id )->destroy_others( wp_get_session_token() );
		}
		SA_Security::clear_rate_limit( 'google_unlink', (string) $user_id );
		SAUTH_Event_Outbox::emit( 'GoogleAccountUnlinked.v1', $user_id, $user_id, array( 'method' => 'google_oidc' ), 'security' );
		SA_Membership_Adapter::audit( 'google_account_unlinked', $user_id );
		wp_safe_redirect( SA_Security::message_url( 'google_account', 'success', 'Google has been unlinked. Your password and passkey sign-in methods remain under File 02.' ) );
		exit;
	}

	public static function explicitly_linked( $user_id ) {
		$user_id = absint( $user_id );
		$account = (string) get_user_meta( $user_id, '_sauth_google_account', true );
		$verified = (string) get_user_meta( $user_id, '_sauth_google_email_verified', true );
		$version = (string) get_user_meta( $user_id, '_sauth_google_link_version', true );
		$sub = (string) get_user_meta( $user_id, '_sauth_google_sub', true );
		$email = (string) get_user_meta( $user_id, '_sauth_google_email', true );
		if ( '' === $account && '' === $sub ) {
			$account = (string) get_user_meta( $user_id, '_sa_google_account', true );
			$verified = (string) get_user_meta( $user_id, '_sa_google_email_verified', true );
			$version = (string) get_user_meta( $user_id, '_sa_google_link_version', true );
			$sub = (string) get_user_meta( $user_id, '_sa_google_sub', true );
			$email = (string) get_user_meta( $user_id, '_sa_google_email', true );
		}
		return $user_id > 0 && '1' === $account && '1' === $verified && in_array( $version, array( '2', '4' ), true ) && '' !== $sub && is_email( $email );
	}

	/** Compatibility read for any old challenge URL; new flow does not create it. */
	public static function challenge( $token ) {
		$token = sanitize_text_field( (string) $token );
		if ( '' === $token || strlen( $token ) > 256 ) {
			return array();
		}
		$data = get_transient( 'sa_google_ch_' . hash( 'sha256', $token ) );
		return is_array( $data ) ? $data : array();
	}

	public static function google_meta_keys() {
		$suffixes = array( 'sub', 'email', 'picture', 'email_verified', 'account', 'linked_at', 'last_login_at', 'link_version' );
		$keys = array();
		foreach ( $suffixes as $suffix ) {
			$keys[] = '_sauth_google_' . $suffix;
			$keys[] = '_sa_google_' . $suffix;
		}
		return $keys;
	}

	private function valid_claims( $claims, array $data ) {
		if ( ! is_array( $claims ) || empty( $claims['sub'] ) || empty( $claims['email'] ) || ! isset( $claims['iat'], $claims['exp'] ) ) {
			return false;
		}
		$sub = (string) $claims['sub'];
		if ( strlen( $sub ) > 255 || strlen( (string) $claims['email'] ) > 320 ) {
			return false;
		}
		$client_id      = self::client_id();
		$valid_issuer   = isset( $claims['iss'] ) && in_array( $claims['iss'], array( 'https://accounts.google.com', 'accounts.google.com' ), true );
		$valid_nonce    = isset( $claims['nonce'], $data['nonce'] ) && hash_equals( (string) $data['nonce'], (string) $claims['nonce'] );
		$email_verified = isset( $claims['email_verified'] ) && in_array( $claims['email_verified'], array( true, 'true', '1', 1 ), true );
		$audience       = $claims['aud'] ?? '';
		$valid_audience = is_array( $audience ) ? count( $audience ) <= 8 && in_array( $client_id, $audience, true ) : hash_equals( $client_id, (string) $audience );
		if ( is_array( $audience ) && count( $audience ) > 1 ) {
			$valid_audience = $valid_audience && isset( $claims['azp'] ) && hash_equals( $client_id, (string) $claims['azp'] );
		}
		$now = time();
		$iat = (int) $claims['iat'];
		$exp = (int) $claims['exp'];
		$valid_time = $iat > 0 && $iat <= $now + 60 && $iat >= $now - 600 && $exp > $now && $exp > $iat && $exp <= $iat + 7200;
		return $valid_issuer && $valid_nonce && $email_verified && $valid_audience && $valid_time && is_email( $claims['email'] );
	}

	private function link_target( array $claims, array $data ) {
		if ( ! is_user_logged_in() || empty( $data['actor_id'] ) || get_current_user_id() !== (int) $data['actor_id'] ) {
			return new WP_Error( 'sa_link_session', 'Your signed-in session is required to link Google.' );
		}
		$user = wp_get_current_user();
		if ( 0 !== strcasecmp( (string) $user->user_email, (string) $claims['email'] ) ) {
			return new WP_Error( 'sa_link_email', 'The Google email must exactly match the account email.' );
		}
		if ( ! SA_Membership_Adapter::can_use_google( $user->ID ) ) {
			return new WP_Error( 'sa_link_membership', 'The membership is not eligible for Google linking.' );
		}
		return $user;
	}

	private function linked_user( $sub, $require_explicit = true ) {
		$sub = sanitize_text_field( (string) $sub );
		if ( '' === $sub ) {
			return new WP_Error( 'sa_google_unlinked', 'This Google account is not linked.' );
		}
		$found = array();
		foreach ( array( '_sauth_google_sub', '_sa_google_sub' ) as $meta_key ) {
			$users = get_users( array( 'meta_key' => $meta_key, 'meta_value' => $sub, 'number' => 3, 'count_total' => false ) );
			foreach ( $users as $user ) {
				$found[ (int) $user->ID ] = $user;
			}
		}
		if ( count( $found ) > 1 ) {
			return new WP_Error( 'sa_google_duplicate', 'A duplicate Google account link requires administrative review.' );
		}
		if ( empty( $found ) ) {
			return new WP_Error( 'sa_google_unlinked', 'This Google account is not linked. Sign in with another approved method and link Google first.' );
		}
		$user = reset( $found );
		if ( $require_explicit && ! self::explicitly_linked( $user->ID ) ) {
			return new WP_Error( 'sa_google_legacy_link', 'This incomplete Google association must be explicitly re-linked before use.' );
		}
		return $user;
	}

	private function store_link( $user_id, array $claims, $explicit_link = false ) {
		$user_id = absint( $user_id );
		$sub = sanitize_text_field( (string) ( $claims['sub'] ?? '' ) );
		$email = sanitize_email( (string) ( $claims['email'] ?? '' ) );
		if ( ! $user_id || '' === $sub || ! is_email( $email ) ) {
			return false;
		}
		$values = array(
			'sub'            => $sub,
			'email'          => $email,
			'email_verified' => '1',
			'account'        => '1',
			'linked_at'      => current_time( 'mysql', true ),
			'link_version'   => '4',
			'picture'        => empty( $claims['picture'] ) ? '' : esc_url_raw( $claims['picture'] ),
		);
		$before = array();
		foreach ( array( '_sauth_google_', '_sa_google_' ) as $prefix ) {
			foreach ( $values as $suffix => $value ) {
				$key = $prefix . $suffix;
				$before[ $key ] = get_user_meta( $user_id, $key, true );
				if ( '' === $value && 'picture' === $suffix ) {
					delete_user_meta( $user_id, $key );
				} else {
					update_user_meta( $user_id, $key, $value );
				}
			}
		}
		foreach ( array( '_sauth_google_', '_sa_google_' ) as $prefix ) {
			foreach ( $values as $suffix => $value ) {
				$key = $prefix . $suffix;
				$stored = (string) get_user_meta( $user_id, $key, true );
				$expected = ( '' === $value && 'picture' === $suffix ) ? '' : (string) $value;
				if ( ! hash_equals( $expected, $stored ) ) {
					foreach ( $before as $restore_key => $restore_value ) {
						if ( '' === (string) $restore_value ) { delete_user_meta( $user_id, $restore_key ); }
						else { update_user_meta( $user_id, $restore_key, $restore_value ); }
					}
					$restored = true;
					foreach ( $before as $restore_key => $restore_value ) {
						$restored = $restored && hash_equals( (string) $restore_value, (string) get_user_meta( $user_id, $restore_key, true ) );
					}
					if ( ! $restored ) { self::contain_linkage_failure( $user_id, 'google_link_rollback_failed' ); }
					return false;
				}
			}
		}
		return true;
	}


	/**
	 * Disable Google authentication after an uncertain link/unlink mutation. If
	 * the disable markers themselves cannot be proven durable, Safe Mode becomes
	 * the higher-level containment barrier. All sessions are revoked either way.
	 */
	public static function contain_linkage_failure( $user_id, $reason = 'google_linkage_uncertain' ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) { return false; }
		update_user_meta( $user_id, '_sauth_google_account', '0' );
		update_user_meta( $user_id, '_sa_google_account', '0' );
		$disabled = '0' === (string) get_user_meta( $user_id, '_sauth_google_account', true )
			&& '0' === (string) get_user_meta( $user_id, '_sa_google_account', true );
		$revoked = SAUTH_Session_Manager::revoke_user_sessions( $user_id, 'google_linkage_containment' );
		if ( ! $disabled || ! $revoked ) {
			SAUTH_Operations::enter_safe_mode();
		}
		SA_Membership_Adapter::audit( sanitize_key( (string) $reason ), $user_id, array( 'disabled_marker_verified' => $disabled, 'sessions_revoked_verified' => $revoked ) );
		return $disabled && $revoked;
	}

	private static function fresh_passkey( $user_id ) {
		if ( ! class_exists( 'SAUTH_Passkeys' ) || ! is_callable( array( 'SAUTH_Passkeys', 'file00_assurance' ) ) ) {
			return false;
		}
		try {
			$assurance = SAUTH_Passkeys::file00_assurance( array(), absint( $user_id ) );
			return is_array( $assurance ) && 'file02' === ( $assurance['owner'] ?? '' ) && ! empty( $assurance['passkey_asserted'] ) && 'webauthn_passkey' === ( $assurance['method'] ?? '' );
		} catch ( Throwable $error ) {
			return false;
		}
	}

	private function acquire_link_lock( $sub ) {
		$name   = 'sa_google_link_lock_' . substr( hash( 'sha256', (string) $sub ), 0, 40 );
		$token  = SA_Security::random_token( 16 );
		$expiry = time() + 30;
		if ( '' === $token ) { return array(); }
		if ( add_option( $name, array( 'token' => $token, 'expires' => $expiry ), '', false ) ) {
			return array( 'name' => $name, 'token' => $token );
		}
		$current = get_option( $name, array() );
		if ( is_array( $current ) && ! empty( $current['expires'] ) && (int) $current['expires'] < time() ) {
			delete_option( $name );
			if ( add_option( $name, array( 'token' => $token, 'expires' => $expiry ), '', false ) ) {
				return array( 'name' => $name, 'token' => $token );
			}
		}
		return array();
	}

	private function release_link_lock( array $lock ) {
		if ( empty( $lock['name'] ) || empty( $lock['token'] ) ) { return; }
		$current = get_option( $lock['name'], array() );
		if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], (string) $lock['token'] ) ) {
			delete_option( $lock['name'] );
		}
	}

	private function state_key( $state ) {
		return 'sa_google_state_' . hash( 'sha256', (string) $state );
	}

	private function challenge_key( $token ) {
		return 'sa_google_ch_' . hash( 'sha256', (string) $token );
	}

	private function state_cookie( $value, $expires ) {
		$options = array( 'expires' => $expires, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax' );
		if ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) { $options['domain'] = COOKIE_DOMAIN; }
		return setcookie( self::COOKIE_NAME, $value, $options );
	}

	private static function safe_observer_action( $hook, array $args ) {
		try { do_action_ref_array( (string) $hook, $args ); } catch ( Throwable $error ) { /* Observer failure must not corrupt authentication state. */ }
	}

	private function fail( $message, $page = 'login' ) {
		wp_safe_redirect( SA_Security::message_url( $page, 'error', $message ) );
		exit;
	}
}
