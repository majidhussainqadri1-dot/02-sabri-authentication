<?php

defined( 'ABSPATH' ) || exit;

final class SA_Google_OAuth {
	const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
	const TOKENINFO_URL  = 'https://oauth2.googleapis.com/tokeninfo';
	const COOKIE_NAME    = 'sa_google_state_v2';

	public function hooks() {
		add_action( 'admin_post_nopriv_sa_google_start', array( $this, 'start' ) );
		add_action( 'admin_post_sa_google_start', array( $this, 'start' ) );
		add_action( 'admin_post_nopriv_sa_google_callback', array( $this, 'callback' ) );
		add_action( 'admin_post_sa_google_callback', array( $this, 'callback' ) );
		add_action( 'admin_post_nopriv_sa_google_verify', array( $this, 'verify_challenge' ) );
		add_action( 'admin_post_sa_google_verify', array( $this, 'verify_challenge' ) );
		add_action( 'admin_post_sa_google_unlink', array( $this, 'unlink' ) );
	}

	public static function callback_url() {
		return admin_url( 'admin-post.php?action=sa_google_callback', 'https' );
	}

	public static function configured() {
		return SA_Membership_Adapter::available()
			&& '1' === get_option( 'sa_google_enabled', '0' )
			&& '' !== trim( (string) get_option( 'sa_google_client_id', '' ) )
			&& '' !== self::client_secret();
	}

	private static function client_secret() {
		return SA_Security::decrypt( (string) get_option( 'sa_google_client_secret', '' ) );
	}

	public function start() {
		if ( ! self::configured() ) {
			$this->fail( 'Google sign-in is not configured or Membership Core is unavailable.' );
		}
		if ( ! is_ssl() ) {
			$this->fail( 'Google sign-in requires HTTPS.' );
		}

		$flow = isset( $_GET['flow'] ) ? sanitize_key( wp_unslash( $_GET['flow'] ) ) : 'login';
		$flow = in_array( $flow, array( 'login', 'link' ), true ) ? $flow : 'login';

		if ( 'link' === $flow ) {
			check_admin_referer( 'sa_google_link_start' );
			if ( ! is_user_logged_in() || ! SA_Membership_Adapter::can_use_google( get_current_user_id() ) ) {
				$this->fail( 'An approved Membership Core account with two-factor authentication is required before linking Google.', 'google_account' );
			}
		} elseif ( is_user_logged_in() ) {
			wp_safe_redirect( SA_Security::page_url( 'google_account', SA_Membership_Adapter::profile_url() ) );
			exit;
		}

		$actor_id = get_current_user_id();
		$subject  = $flow . '|' . $actor_id;
		if ( SA_Security::rate_limited( 'google_start', 10, 900, $subject ) ) {
			$this->fail( 'Please wait before trying Google authentication again.', 'link' === $flow ? 'google_account' : 'login' );
		}

		$state     = SA_Security::random_token( 32 );
		$nonce     = SA_Security::random_token( 32 );
		$verifier  = SA_Security::random_token( 48 );
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
		$redirect  = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/' );
		$data      = array(
			'flow'        => $flow,
			'actor_id'    => $actor_id,
			'nonce'       => $nonce,
			'verifier'    => $verifier,
			'redirect'    => SA_Security::safe_redirect( $redirect ),
			'fingerprint' => SA_Security::client_fingerprint(),
			'created'     => time(),
		);
		set_transient( $this->state_key( $state ), $data, 10 * MINUTE_IN_SECONDS );
		$this->state_cookie( $state, time() + 600 );

		$url = add_query_arg(
			array(
				'client_id'              => trim( (string) get_option( 'sa_google_client_id', '' ) ),
				'redirect_uri'           => self::callback_url(),
				'response_type'          => 'code',
				'scope'                  => 'openid email profile',
				'state'                  => $state,
				'nonce'                  => $nonce,
				'code_challenge'         => $challenge,
				'code_challenge_method'  => 'S256',
				'prompt'                 => 'select_account',
				'include_granted_scopes' => 'true',
			),
			self::AUTH_ENDPOINT
		);
		wp_redirect( esc_url_raw( $url ) );
		exit;
	}

	public function callback() {
		$state  = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$cookie = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : '';
		$data   = $state ? get_transient( $this->state_key( $state ) ) : false;

		if ( ! $state || ! $cookie || ! hash_equals( $cookie, $state ) || ! is_array( $data ) ) {
			$this->fail( 'The Google authentication session could not be verified.' );
		}
		delete_transient( $this->state_key( $state ) );
		$this->state_cookie( '', time() - HOUR_IN_SECONDS );

		if ( ! isset( $data['fingerprint'] ) || ! hash_equals( (string) $data['fingerprint'], SA_Security::client_fingerprint() ) ) {
			$this->fail( 'The Google authentication session changed unexpectedly.' );
		}
		if ( isset( $_GET['error'] ) ) {
			$this->fail( 'Google sign-in was cancelled or denied.' );
		}

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( ! $code ) {
			$this->fail( 'Google did not return an authorization code.' );
		}

		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => trim( (string) get_option( 'sa_google_client_id', '' ) ),
					'client_secret' => self::client_secret(),
					'redirect_uri'  => self::callback_url(),
					'grant_type'    => 'authorization_code',
					'code_verifier' => isset( $data['verifier'] ) ? (string) $data['verifier'] : '',
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->fail( 'Google authentication could not be completed.' );
		}

		$token = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $token ) || empty( $token['id_token'] ) ) {
			$this->fail( 'Google did not return a valid identity token.' );
		}

		$verify = wp_remote_get( add_query_arg( 'id_token', $token['id_token'], self::TOKENINFO_URL ), array( 'timeout' => 15 ) );
		if ( is_wp_error( $verify ) || 200 !== wp_remote_retrieve_response_code( $verify ) ) {
			$this->fail( 'The Google identity token could not be validated.' );
		}
		$claims = json_decode( wp_remote_retrieve_body( $verify ), true );
		if ( ! $this->valid_claims( $claims, $data ) ) {
			$this->fail( 'The Google identity token failed validation.' );
		}

		$flow = isset( $data['flow'] ) && 'link' === $data['flow'] ? 'link' : 'login';
		$user = 'link' === $flow ? $this->link_target( $claims, $data ) : $this->linked_user( $claims['sub'], true );
		if ( is_wp_error( $user ) ) {
			$this->fail( $user->get_error_message(), 'link' === $flow ? 'google_account' : 'login' );
		}
		if ( 'login' === $flow && 0 !== strcasecmp( (string) $user->user_email, (string) $claims['email'] ) ) {
			$this->fail( 'The linked Google email no longer matches the verified Membership Core email. Sign in through Membership Core and re-link Google.', 'login' );
		}
		if ( ! $user instanceof WP_User || ! SA_Membership_Adapter::can_use_google( $user->ID ) ) {
			$this->fail( 'The Membership Core account must be approved and protected by two-factor authentication.', 'login' === $flow ? 'login' : 'google_account' );
		}

		$challenge = SA_Security::random_token( 32 );
		$challenge_data = array(
			'operation'   => $flow,
			'user_id'     => (int) $user->ID,
			'sub'         => sanitize_text_field( $claims['sub'] ),
			'email'       => sanitize_email( $claims['email'] ),
			'picture'     => empty( $claims['picture'] ) ? '' : esc_url_raw( $claims['picture'] ),
			'redirect'    => isset( $data['redirect'] ) ? SA_Security::safe_redirect( $data['redirect'] ) : home_url( '/' ),
			'fingerprint' => SA_Security::client_fingerprint(),
			'created'     => time(),
		);
		set_transient( $this->challenge_key( $challenge ), $challenge_data, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'challenge', rawurlencode( $challenge ), SA_Security::page_url( 'google_verify' ) ) );
		exit;
	}

	public function verify_challenge() {
		$token = isset( $_POST['challenge'] ) ? sanitize_text_field( wp_unslash( $_POST['challenge'] ) ) : '';
		check_admin_referer( 'sa_google_verify_' . $token, 'sa_nonce' );
		$data = $token ? get_transient( $this->challenge_key( $token ) ) : false;

		if ( ! is_array( $data ) || empty( $data['user_id'] ) || empty( $data['operation'] ) ) {
			$this->fail( 'This Google verification challenge has expired.' );
		}
		if ( empty( $data['fingerprint'] ) || ! hash_equals( (string) $data['fingerprint'], SA_Security::client_fingerprint() ) ) {
			delete_transient( $this->challenge_key( $token ) );
			$this->fail( 'The Google verification challenge changed unexpectedly.' );
		}
		if ( 'link' === $data['operation'] && ( ! is_user_logged_in() || get_current_user_id() !== (int) $data['user_id'] ) ) {
			delete_transient( $this->challenge_key( $token ) );
			$this->fail( 'Your signed-in Membership Core session is required to link Google.', 'google_account' );
		}

		$code = isset( $_POST['second_factor'] ) ? sanitize_text_field( wp_unslash( $_POST['second_factor'] ) ) : '';
		$challenge_blocked = SA_Security::rate_limited( 'google_second_factor_challenge', 5, 900, $token );
		$user_blocked      = SA_Security::rate_limited( 'google_second_factor_user', 10, 900, (string) $data['user_id'] );
		if ( $challenge_blocked || $user_blocked ) {
			delete_transient( $this->challenge_key( $token ) );
			SA_Membership_Adapter::audit( 'google_second_factor_rate_limited', (int) $data['user_id'], array( 'operation' => $data['operation'] ) );
			$this->fail( 'Too many verification attempts. Start the Google authentication process again.' );
		}
		if ( ! SA_Membership_Adapter::verify_second_factor( (int) $data['user_id'], $code ) ) {
			SA_Membership_Adapter::audit( 'google_second_factor_failed', (int) $data['user_id'], array( 'operation' => $data['operation'] ) );
			$url = SA_Security::message_url( 'google_verify', 'error', 'The Authenticator or recovery code was not accepted.', array( 'challenge' => $token ) );
			wp_safe_redirect( $url );
			exit;
		}

		$user = get_user_by( 'id', (int) $data['user_id'] );
		if ( ! $user || ! SA_Membership_Adapter::can_use_google( $user->ID ) ) {
			delete_transient( $this->challenge_key( $token ) );
			$this->fail( 'The Membership Core account is not eligible for Google authentication.' );
		}

		if ( 'link' === $data['operation'] ) {
			$lock = $this->acquire_link_lock( $data['sub'] );
			if ( ! $lock ) {
				delete_transient( $this->challenge_key( $token ) );
				$this->fail( 'This Google account is being linked in another request. Wait briefly and try again.', 'google_account' );
			}
			$existing = $this->linked_user( $data['sub'], false );
			if ( is_wp_error( $existing ) && 'sa_google_unlinked' !== $existing->get_error_code() ) {
				$this->release_link_lock( $lock );
				delete_transient( $this->challenge_key( $token ) );
				$this->fail( 'This Google account link requires administrative review.', 'google_account' );
			}
			if ( $existing instanceof WP_User && $existing->ID !== $user->ID ) {
				$this->release_link_lock( $lock );
				delete_transient( $this->challenge_key( $token ) );
				$this->fail( 'This Google account is already linked to another membership.', 'google_account' );
			}
			$this->store_link( $user->ID, $data, true );
			$this->release_link_lock( $lock );
			SA_Membership_Adapter::audit( 'google_account_linked', $user->ID, array( 'email' => $data['email'] ) );
			$destination = SA_Security::message_url( 'google_account', 'success', 'Google has been linked to your verified Membership Core account.' );
		} else {
			$linked = $this->linked_user( $data['sub'], true );
			if ( ! $linked instanceof WP_User || $linked->ID !== $user->ID ) {
				delete_transient( $this->challenge_key( $token ) );
				$this->fail( 'The Google account link is no longer valid.' );
			}
			$this->store_link( $user->ID, $data, false );
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true, is_ssl() );
			do_action( 'wp_login', $user->user_login, $user );
			update_user_meta( $user->ID, '_sa_google_last_login_at', current_time( 'mysql', true ) );
			SA_Membership_Adapter::audit( 'google_login_success', $user->ID );
			$destination = isset( $data['redirect'] ) ? SA_Security::safe_redirect( $data['redirect'] ) : SA_Membership_Adapter::profile_url();
		}

		SA_Security::clear_rate_limit( 'google_second_factor_challenge', $token );
		SA_Security::clear_rate_limit( 'google_second_factor_user', (string) $user->ID );
		delete_transient( $this->challenge_key( $token ) );
		wp_safe_redirect( $destination );
		exit;
	}

	public function unlink() {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( 'sa_google_unlink', 'sa_nonce' );
		$user_id = get_current_user_id();
		$code    = isset( $_POST['second_factor'] ) ? sanitize_text_field( wp_unslash( $_POST['second_factor'] ) ) : '';

		if ( SA_Security::rate_limited( 'google_unlink', 5, 900, (string) $user_id ) || ! SA_Membership_Adapter::verify_second_factor( $user_id, $code ) ) {
			wp_safe_redirect( SA_Security::message_url( 'google_account', 'error', 'The Google account was not unlinked. Verify your current Authenticator or recovery code.' ) );
			exit;
		}

		foreach ( self::google_meta_keys() as $key ) {
			delete_user_meta( $user_id, $key );
		}
		if ( class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $user_id )->destroy_others( wp_get_session_token() );
		}
		SA_Security::clear_rate_limit( 'google_unlink', (string) $user_id );
		SA_Membership_Adapter::audit( 'google_account_unlinked', $user_id );
		wp_safe_redirect( SA_Security::message_url( 'google_account', 'success', 'Google has been unlinked. Your Membership Core password and two-factor sign-in remain available.' ) );
		exit;
	}

	public static function explicitly_linked( $user_id ) {
		$user_id = absint( $user_id );
		return $user_id > 0
			&& '1' === (string) get_user_meta( $user_id, '_sa_google_account', true )
			&& '1' === (string) get_user_meta( $user_id, '_sa_google_email_verified', true )
			&& '2' === (string) get_user_meta( $user_id, '_sa_google_link_version', true )
			&& '' !== (string) get_user_meta( $user_id, '_sa_google_sub', true )
			&& is_email( (string) get_user_meta( $user_id, '_sa_google_email', true ) );
	}

	public static function challenge( $token ) {
		$token = sanitize_text_field( (string) $token );
		$data  = $token ? get_transient( 'sa_google_ch_' . hash( 'sha256', $token ) ) : false;
		return is_array( $data ) ? $data : array();
	}

	public static function google_meta_keys() {
		return array(
			'_sa_google_sub',
			'_sa_google_email',
			'_sa_google_picture',
			'_sa_google_email_verified',
			'_sa_google_account',
			'_sa_google_linked_at',
			'_sa_google_last_login_at',
			'_sa_google_link_version',
		);
	}

	private function valid_claims( $claims, array $data ) {
		if ( ! is_array( $claims ) || empty( $claims['sub'] ) || empty( $claims['email'] ) ) {
			return false;
		}
		$client_id      = trim( (string) get_option( 'sa_google_client_id', '' ) );
		$valid_issuer   = isset( $claims['iss'] ) && in_array( $claims['iss'], array( 'https://accounts.google.com', 'accounts.google.com' ), true );
		$valid_nonce    = isset( $claims['nonce'], $data['nonce'] ) && hash_equals( (string) $data['nonce'], (string) $claims['nonce'] );
		$email_verified = isset( $claims['email_verified'] ) && in_array( $claims['email_verified'], array( true, 'true', '1', 1 ), true );
		$audience       = isset( $claims['aud'] ) ? $claims['aud'] : '';
		$valid_audience = is_array( $audience ) ? in_array( $client_id, $audience, true ) : hash_equals( $client_id, (string) $audience );
		if ( is_array( $audience ) && count( $audience ) > 1 ) {
			$valid_audience = $valid_audience && isset( $claims['azp'] ) && hash_equals( $client_id, (string) $claims['azp'] );
		}
		$now            = time();
		$valid_time     = ! empty( $claims['exp'] ) && (int) $claims['exp'] > $now;
		if ( isset( $claims['iat'] ) ) {
			$issued = (int) $claims['iat'];
			$valid_time = $valid_time && $issued <= $now + 60 && $issued >= $now - 600;
		}
		return $valid_issuer && $valid_nonce && $email_verified && $valid_audience && $valid_time && is_email( $claims['email'] );
	}

	private function link_target( array $claims, array $data ) {
		if ( ! is_user_logged_in() || empty( $data['actor_id'] ) || get_current_user_id() !== (int) $data['actor_id'] ) {
			return new WP_Error( 'sa_link_session', 'Your signed-in Membership Core session is required to link Google.' );
		}
		$user = wp_get_current_user();
		if ( 0 !== strcasecmp( (string) $user->user_email, (string) $claims['email'] ) ) {
			return new WP_Error( 'sa_link_email', 'The Google email must exactly match the verified Membership Core email.' );
		}
		$existing = $this->linked_user( $claims['sub'], false );
		if ( is_wp_error( $existing ) && 'sa_google_unlinked' !== $existing->get_error_code() ) {
			return new WP_Error( 'sa_link_conflict', 'This Google account link requires administrative review.' );
		}
		if ( $existing instanceof WP_User && $existing->ID !== $user->ID ) {
			return new WP_Error( 'sa_link_conflict', 'This Google account is already linked to another membership.' );
		}
		return $user;
	}

	private function linked_user( $sub, $require_explicit = true ) {
		$users = get_users(
			array(
				'meta_key'    => '_sa_google_sub',
				'meta_value'  => sanitize_text_field( (string) $sub ),
				'number'      => 2,
				'count_total' => false,
			)
		);
		if ( count( $users ) > 1 ) {
			return new WP_Error( 'sa_google_duplicate', 'A duplicate Google account link requires administrative review.' );
		}
		if ( empty( $users ) ) {
			return new WP_Error( 'sa_google_unlinked', 'This Google account is not linked. Sign in through Membership Core first, then link Google from Google Account Security.' );
		}
		if ( $require_explicit && ! self::explicitly_linked( $users[0]->ID ) ) {
			return new WP_Error( 'sa_google_legacy_link', 'This legacy or incomplete Google association must be explicitly re-linked from Google Account Security before it can be used.' );
		}
		return $users[0];
	}

	private function store_link( $user_id, array $data, $explicit_link = false ) {
		update_user_meta( $user_id, '_sa_google_sub', sanitize_text_field( $data['sub'] ) );
		update_user_meta( $user_id, '_sa_google_email', sanitize_email( $data['email'] ) );
		update_user_meta( $user_id, '_sa_google_email_verified', '1' );
		update_user_meta( $user_id, '_sa_google_account', '1' );
		if ( $explicit_link || '' === (string) get_user_meta( $user_id, '_sa_google_linked_at', true ) ) {
			update_user_meta( $user_id, '_sa_google_linked_at', current_time( 'mysql', true ) );
		}
		update_user_meta( $user_id, '_sa_google_link_version', '2' );
		if ( ! empty( $data['picture'] ) ) {
			update_user_meta( $user_id, '_sa_google_picture', esc_url_raw( $data['picture'] ) );
		} else {
			delete_user_meta( $user_id, '_sa_google_picture' );
		}
	}

	private function acquire_link_lock( $sub ) {
		$name   = 'sa_google_link_lock_' . substr( hash( 'sha256', (string) $sub ), 0, 40 );
		$token  = SA_Security::random_token( 16 );
		$expiry = time() + 30;
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
		if ( empty( $lock['name'] ) || empty( $lock['token'] ) ) {
			return;
		}
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
		$options = array(
			'expires'  => $expires,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => true,
			'httponly' => true,
			'samesite' => 'Lax',
		);
		if ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) {
			$options['domain'] = COOKIE_DOMAIN;
		}
		setcookie( self::COOKIE_NAME, $value, $options );
	}

	private function fail( $message, $page = 'login' ) {
		wp_safe_redirect( SA_Security::message_url( $page, 'error', $message ) );
		exit;
	}
}
