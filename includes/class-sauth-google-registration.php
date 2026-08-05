<?php

defined( 'ABSPATH' ) || exit;

/**
 * Secure Google-first registration bridge.
 *
 * Google proves email ownership only. File 00 remains the canonical owner of
 * identity, membership, age/guardian eligibility, account class and consent.
 */
final class SAUTH_Google_Registration {
	const COOKIE_NAME = 'sauth_google_registration_state';
	const STATE_TTL   = 600;
	const CONTEXT_TTL = 900;

	public static function init() {
		add_action( 'admin_post_nopriv_sauth_google_registration_start', array( __CLASS__, 'start' ) );
		add_action( 'admin_post_nopriv_sauth_google_registration_callback', array( __CLASS__, 'callback' ) );
	}

	public static function callback_url() {
		return admin_url( 'admin-post.php?action=sauth_google_registration_callback', 'https' );
	}

	public static function start_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=sauth_google_registration_start' ),
			'sauth_google_registration_start'
		);
	}

	public static function available() {
		return ! SAUTH_Operations::safe_mode()
			&& SA_Google_OAuth::configured()
			&& SAUTH_Account_Contract::provider_available()
			&& SAUTH_Provider_Health::allow_request( 'google' );
	}

	public static function start() {
		check_admin_referer( 'sauth_google_registration_start' );
		if ( is_user_logged_in() ) {
			wp_safe_redirect( SA_Security::page_url( 'sessions', home_url( '/' ) ) );
			exit;
		}
		if ( ! self::available() || ! is_ssl() ) {
			self::fail( 'Google account registration is temporarily unavailable. You may still register with email and password.' );
		}
		if ( SA_Security::rate_limited( 'sauth_google_registration_start', 8, 900 ) ) {
			self::fail( 'Please wait before starting Google registration again.' );
		}

		$state     = SA_Security::random_token( 32 );
		$nonce     = SA_Security::random_token( 32 );
		$verifier  = SA_Security::random_token( 48 );
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
		$record    = array(
			'nonce'       => $nonce,
			'verifier'    => $verifier,
			'fingerprint' => SA_Security::client_fingerprint(),
			'created_at'  => time(),
		);
		set_transient( self::state_key( $state ), $record, self::STATE_TTL );
		self::state_cookie( $state, time() + self::STATE_TTL );

		$url = add_query_arg(
			array(
				'client_id'             => trim( (string) get_option( 'sa_google_client_id', '' ) ),
				'redirect_uri'          => self::callback_url(),
				'response_type'         => 'code',
				'scope'                 => 'openid email profile',
				'state'                 => $state,
				'nonce'                 => $nonce,
				'code_challenge'        => $challenge,
				'code_challenge_method' => 'S256',
				'prompt'                => 'select_account',
			),
			SA_Google_OAuth::AUTH_ENDPOINT
		);
		wp_redirect( esc_url_raw( $url ) );
		exit;
	}

	public static function callback() {
		$state  = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$cookie = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : '';
		$data   = $state ? get_transient( self::state_key( $state ) ) : false;

		if ( '' === $state || '' === $cookie || ! hash_equals( $cookie, $state ) || ! is_array( $data ) ) {
			self::fail( 'The Google registration session could not be verified.' );
		}
		delete_transient( self::state_key( $state ) );
		self::state_cookie( '', time() - HOUR_IN_SECONDS );
		if ( empty( $data['fingerprint'] ) || ! hash_equals( (string) $data['fingerprint'], SA_Security::client_fingerprint() ) ) {
			self::fail( 'The Google registration session changed unexpectedly.' );
		}
		if ( isset( $_GET['error'] ) ) {
			self::fail( 'Google registration was cancelled or denied.' );
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( '' === $code ) {
			self::fail( 'Google did not return an authorization code.' );
		}

		$started  = microtime( true );
		$response = wp_remote_post(
			SA_Google_OAuth::TOKEN_ENDPOINT,
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => trim( (string) get_option( 'sa_google_client_id', '' ) ),
					'client_secret' => SA_Security::decrypt( (string) get_option( 'sa_google_client_secret', '' ) ),
					'redirect_uri'  => self::callback_url(),
					'grant_type'    => 'authorization_code',
					'code_verifier' => (string) ( $data['verifier'] ?? '' ),
				),
			)
		);
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			SAUTH_Provider_Health::record_failure( 'google', 'registration_token_exchange_failed', $latency );
			self::fail( 'Google registration could not be completed.' );
		}
		$token = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $token ) || empty( $token['id_token'] ) ) {
			SAUTH_Provider_Health::record_failure( 'google', 'registration_identity_token_missing', $latency );
			self::fail( 'Google did not return a valid identity token.' );
		}
		$verify = wp_remote_get(
			add_query_arg( 'id_token', $token['id_token'], SA_Google_OAuth::TOKENINFO_URL ),
			array( 'timeout' => 15 )
		);
		if ( is_wp_error( $verify ) || 200 !== wp_remote_retrieve_response_code( $verify ) ) {
			SAUTH_Provider_Health::record_failure( 'google', 'registration_identity_validation_failed', $latency );
			self::fail( 'The Google identity token could not be validated.' );
		}
		$claims = json_decode( wp_remote_retrieve_body( $verify ), true );
		if ( ! self::valid_claims( $claims, $data ) ) {
			SAUTH_Provider_Health::record_failure( 'google', 'registration_claims_invalid', $latency );
			self::fail( 'The Google identity token failed validation.' );
		}

		$registration_token = SA_Security::random_token( 32 );
		$context = array(
			'email'       => sanitize_email( $claims['email'] ),
			'sub'         => sanitize_text_field( $claims['sub'] ),
			'name'        => sanitize_text_field( (string) ( $claims['name'] ?? '' ) ),
			'picture'     => empty( $claims['picture'] ) ? '' : esc_url_raw( $claims['picture'] ),
			'fingerprint' => SA_Security::client_fingerprint(),
			'created_at'  => time(),
		);
		set_transient( self::context_key( $registration_token ), $context, self::CONTEXT_TTL );
		SAUTH_Provider_Health::record_success( 'google', $latency );
		wp_safe_redirect(
			add_query_arg(
				'google_registration',
				rawurlencode( $registration_token ),
				SA_Security::page_url( 'signup', wp_registration_url() )
			)
		);
		exit;
	}

	public static function context( $token ) {
		$token = sanitize_text_field( (string) $token );
		$data  = '' !== $token ? get_transient( self::context_key( $token ) ) : false;
		if ( ! is_array( $data ) || empty( $data['email'] ) || empty( $data['sub'] ) || empty( $data['fingerprint'] ) ) {
			return array();
		}
		if ( ! hash_equals( (string) $data['fingerprint'], SA_Security::client_fingerprint() ) ) {
			delete_transient( self::context_key( $token ) );
			return array();
		}
		return $data;
	}

	public static function consume( $token ) {
		$data = self::context( $token );
		if ( ! empty( $data ) ) {
			delete_transient( self::context_key( $token ) );
		}
		return $data;
	}

	public static function finalize_link( $user_id, array $context ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || empty( $context['sub'] ) || empty( $context['email'] ) ) {
			return false;
		}
		$matches = get_users(
			array(
				'meta_key'    => '_sa_google_sub',
				'meta_value'  => sanitize_text_field( $context['sub'] ),
				'number'      => 2,
				'count_total' => false,
			)
		);
		foreach ( $matches as $match ) {
			if ( (int) $match->ID !== $user_id ) {
				return false;
			}
		}
		update_user_meta( $user_id, '_sa_google_sub', sanitize_text_field( $context['sub'] ) );
		update_user_meta( $user_id, '_sa_google_email', sanitize_email( $context['email'] ) );
		update_user_meta( $user_id, '_sa_google_email_verified', '1' );
		update_user_meta( $user_id, '_sa_google_account', '1' );
		update_user_meta( $user_id, '_sa_google_linked_at', current_time( 'mysql', true ) );
		update_user_meta( $user_id, '_sa_google_link_version', '3' );
		if ( ! empty( $context['picture'] ) ) {
			update_user_meta( $user_id, '_sa_google_picture', esc_url_raw( $context['picture'] ) );
		}
		return true;
	}

	private static function valid_claims( $claims, array $data ) {
		if ( ! is_array( $claims ) || empty( $claims['sub'] ) || empty( $claims['email'] ) ) {
			return false;
		}
		$client_id      = trim( (string) get_option( 'sa_google_client_id', '' ) );
		$issuer_ok      = isset( $claims['iss'] ) && in_array( $claims['iss'], array( 'https://accounts.google.com', 'accounts.google.com' ), true );
		$nonce_ok       = isset( $claims['nonce'], $data['nonce'] ) && hash_equals( (string) $data['nonce'], (string) $claims['nonce'] );
		$email_verified = isset( $claims['email_verified'] ) && in_array( $claims['email_verified'], array( true, 'true', '1', 1 ), true );
		$audience       = $claims['aud'] ?? '';
		$audience_ok    = is_array( $audience ) ? in_array( $client_id, $audience, true ) : hash_equals( $client_id, (string) $audience );
		if ( is_array( $audience ) && count( $audience ) > 1 ) {
			$audience_ok = $audience_ok && isset( $claims['azp'] ) && hash_equals( $client_id, (string) $claims['azp'] );
		}
		$now     = time();
		$time_ok = ! empty( $claims['exp'] ) && (int) $claims['exp'] > $now;
		if ( isset( $claims['iat'] ) ) {
			$issued  = (int) $claims['iat'];
			$time_ok = $time_ok && $issued <= $now + 60 && $issued >= $now - 600;
		}
		return $issuer_ok && $nonce_ok && $email_verified && $audience_ok && $time_ok && is_email( $claims['email'] );
	}

	private static function state_key( $state ) {
		return 'sauth_google_registration_state_' . hash( 'sha256', (string) $state );
	}

	private static function context_key( $token ) {
		return 'sauth_google_registration_context_' . hash( 'sha256', (string) $token );
	}

	private static function state_cookie( $value, $expires ) {
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

	private static function fail( $message ) {
		wp_safe_redirect( SA_Security::message_url( 'signup', 'error', $message ) );
		exit;
	}
}
