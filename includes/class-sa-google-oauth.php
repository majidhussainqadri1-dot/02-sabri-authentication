<?php

defined( 'ABSPATH' ) || exit;

final class SA_Google_OAuth {
	const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
	const TOKENINFO_URL  = 'https://oauth2.googleapis.com/tokeninfo';

	public function hooks() {
		add_action( 'admin_post_nopriv_sa_google_start', array( $this, 'start' ) );
		add_action( 'admin_post_sa_google_start', array( $this, 'start' ) );
		add_action( 'admin_post_nopriv_sa_google_callback', array( $this, 'callback' ) );
		add_action( 'admin_post_sa_google_callback', array( $this, 'callback' ) );
	}

	public static function callback_url() {
		return admin_url( 'admin-post.php?action=sa_google_callback' );
	}

	public static function configured() {
		return '1' === get_option( 'sa_google_enabled', '0' ) && '' !== trim( (string) get_option( 'sa_google_client_id', '' ) ) && '' !== self::client_secret();
	}

	private static function client_secret() {
		return SA_Security::decrypt( (string) get_option( 'sa_google_client_secret', '' ) );
	}

	public function start() {
		if ( ! self::configured() ) {
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'Google login is not configured yet.' ) );
			exit;
		}
		if ( SA_Security::rate_limited( 'google_start', 10, 900 ) ) {
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'Please wait before trying Google login again.' ) );
			exit;
		}

		$state    = wp_generate_password( 48, false, false );
		$nonce    = wp_generate_password( 48, false, false );
		$verifier = wp_generate_password( 64, false, false );
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/' );
		set_transient( 'sa_google_' . hash( 'sha256', $state ), array( 'nonce' => $nonce, 'verifier' => $verifier, 'redirect' => SA_Security::safe_redirect( $redirect ), 'created' => time() ), 10 * MINUTE_IN_SECONDS );
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
		$cookie = isset( $_COOKIE['sa_google_state'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['sa_google_state'] ) ) : '';
		$data   = $state ? get_transient( 'sa_google_' . hash( 'sha256', $state ) ) : false;

		if ( ! $state || ! $cookie || ! hash_equals( $cookie, $state ) || ! is_array( $data ) ) {
			$this->fail( 'The Google login session could not be verified.' );
		}
		delete_transient( 'sa_google_' . hash( 'sha256', $state ) );
		$this->state_cookie( '', time() - 3600 );

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
					'code_verifier' => isset( $data['verifier'] ) ? $data['verifier'] : '',
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
		$claims = is_wp_error( $verify ) ? array() : json_decode( wp_remote_retrieve_body( $verify ), true );
		$client_id = trim( (string) get_option( 'sa_google_client_id', '' ) );
		$valid_issuer = isset( $claims['iss'] ) && in_array( $claims['iss'], array( 'https://accounts.google.com', 'accounts.google.com' ), true );
		$valid_nonce  = isset( $claims['nonce'] ) && hash_equals( (string) $data['nonce'], (string) $claims['nonce'] );
		$email_verified = isset( $claims['email_verified'] ) && in_array( $claims['email_verified'], array( true, 'true', '1', 1 ), true );
		if ( ! is_array( $claims ) || empty( $claims['sub'] ) || empty( $claims['email'] ) || ! $email_verified || ! $valid_issuer || empty( $claims['aud'] ) || ! hash_equals( $client_id, (string) $claims['aud'] ) || empty( $claims['exp'] ) || (int) $claims['exp'] < time() || ! $valid_nonce ) {
			$this->fail( 'The Google identity token failed validation.' );
		}

		$user = $this->find_or_create_user( $claims );
		if ( is_wp_error( $user ) ) {
			$this->fail( 'The Google account could not be connected.' );
		}
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		$redirect = $data['redirect'];
		if ( ! get_user_meta( $user->ID, '_sa_profile_complete', true ) ) {
			$pages = (array) get_option( 'sa_page_map', array() );
			$redirect = isset( $pages['complete'] ) ? get_permalink( absint( $pages['complete'] ) ) : $redirect;
		}
		wp_safe_redirect( SA_Security::safe_redirect( $redirect ) );
		exit;
	}

	private function find_or_create_user( array $claims ) {
		$users = get_users( array( 'meta_key' => '_sa_google_sub', 'meta_value' => sanitize_text_field( $claims['sub'] ), 'number' => 1, 'count_total' => false ) );
		if ( $users ) {
			return $users[0];
		}
		$email = sanitize_email( $claims['email'] );
		$user  = get_user_by( 'email', $email );
		if ( $user && user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'sa_admin_google_link', 'Administrative accounts must be linked explicitly.' );
		}
		if ( ! $user ) {
			if ( '1' !== get_option( 'sa_allow_registration', '1' ) ) {
				return new WP_Error( 'sa_registration_closed', 'Registration is closed.' );
			}
			$base = sanitize_user( strstr( $email, '@', true ), true );
			$username = $base ? $base : 'google-member';
			$counter = 1;
			while ( username_exists( $username ) ) { $username = $base . $counter; $counter++; }
			$user_id = wp_insert_user( array( 'user_login' => $username, 'user_email' => $email, 'user_pass' => wp_generate_password( 32, true, true ), 'display_name' => isset( $claims['name'] ) ? sanitize_text_field( $claims['name'] ) : $username, 'role' => 'sabri_member' ) );
			if ( is_wp_error( $user_id ) ) { return $user_id; }
			$user = get_user_by( 'id', $user_id );
			update_user_meta( $user_id, '_sa_google_account', '1' );
			update_user_meta( $user_id, '_sa_account_type', 'member' );
			update_user_meta( $user_id, '_sa_profile_complete', '0' );
		}
		update_user_meta( $user->ID, '_sa_google_sub', sanitize_text_field( $claims['sub'] ) );
		update_user_meta( $user->ID, '_sa_google_email_verified', '1' );
		if ( ! empty( $claims['picture'] ) ) { update_user_meta( $user->ID, '_sa_google_picture', esc_url_raw( $claims['picture'] ) ); }
		return $user;
	}

	private function state_cookie( $value, $expires ) {
		$options = array(
			'expires'  => $expires,
			'path'     => COOKIEPATH ? COOKIEPATH : '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		);
		if ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) {
			$options['domain'] = COOKIE_DOMAIN;
		}
		setcookie( 'sa_google_state', $value, $options );
	}

	private function fail( $message ) {
		wp_safe_redirect( SA_Security::message_url( 'login', 'error', $message ) );
		exit;
	}
}
