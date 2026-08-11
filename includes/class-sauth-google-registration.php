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
	const LOCK_TTL    = 30;

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
				'client_id'             => trim( (string) get_option( 'sauth_google_client_id', get_option( 'sa_google_client_id', '' ) ) ),
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

		$created = absint( $data['created_at'] ?? 0 );
		$now     = time();
		if ( ! $created || $created > $now + 60 || $created < $now - self::STATE_TTL - 60 ) {
			self::fail( 'The Google registration session expired.' );
		}
		if ( empty( $data['fingerprint'] ) || ! hash_equals( (string) $data['fingerprint'], SA_Security::client_fingerprint() ) ) {
			self::fail( 'The Google registration session changed unexpectedly.' );
		}
		if ( isset( $_GET['error'] ) ) {
			self::fail( 'Google registration was cancelled or denied.' );
		}
		if ( SAUTH_Operations::safe_mode() || ! SAUTH_Provider_Health::allow_request( 'google' ) || ! SAUTH_Account_Contract::provider_available() ) {
			self::fail( 'Google registration is temporarily unavailable. No account was created.' );
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( '' === $code ) {
			self::fail( 'Google did not return an authorization code.' );
		}

		$client_id     = trim( (string) get_option( 'sauth_google_client_id', get_option( 'sa_google_client_id', '' ) ) );
		$client_secret = SA_Security::decrypt( (string) get_option( 'sauth_google_client_secret', get_option( 'sa_google_client_secret', '' ) ) );
		$started       = microtime( true );
		$response      = wp_remote_post(
			SA_Google_OAuth::TOKEN_ENDPOINT,
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => self::callback_url(),
					'grant_type'    => 'authorization_code',
					'code_verifier' => (string) ( $data['verifier'] ?? '' ),
				),
			)
		);
		$code = '';
		$client_secret = '';
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
		$id_token = (string) $token['id_token'];
		$verify = wp_remote_get(
			add_query_arg( 'id_token', rawurlencode( $id_token ), SA_Google_OAuth::TOKENINFO_URL ),
			array( 'timeout' => 15 )
		);
		$id_token = '';
		$token = array();
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
		$claims = array();
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
		$created = absint( $data['created_at'] ?? 0 );
		$now     = time();
		if ( ! $created || $created > $now + 60 || $created < $now - self::CONTEXT_TTL - 60 ) {
			delete_transient( self::context_key( $token ) );
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
		$email   = sanitize_email( (string) ( $context['email'] ?? '' ) );
		$sub     = sanitize_text_field( (string) ( $context['sub'] ?? '' ) );
		$user    = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user instanceof WP_User || '' === $sub || ! is_email( $email ) || ! hash_equals( strtolower( (string) $user->user_email ), strtolower( $email ) ) ) {
			return false;
		}

		$lock = self::acquire_link_lock( $sub );
		if ( '' === $lock ) {
			return false;
		}
		try {
			$matches = get_users(
				array(
					'meta_key'    => '_sa_google_sub',
					'meta_value'  => $sub,
					'number'      => 2,
					'count_total' => false,
				)
			);
			foreach ( $matches as $match ) {
				if ( (int) $match->ID !== $user_id ) {
					return false;
				}
			}

			$projection = array(
				'_sauth_google_sub'            => $sub,
				'_sauth_google_email'          => $email,
				'_sauth_google_email_verified' => '1',
				'_sauth_google_account'        => '1',
				'_sauth_google_linked_at'      => current_time( 'mysql', true ),
				'_sauth_google_link_version'   => '4',
			);
			if ( ! empty( $context['picture'] ) ) {
				$projection['_sauth_google_picture'] = esc_url_raw( $context['picture'] );
			}
			foreach ( $projection as $key => $value ) {
				update_user_meta( $user_id, $key, $value );
				update_user_meta( $user_id, str_replace( '_sauth_', '_sa_', $key ), $value );
				if ( (string) get_user_meta( $user_id, $key, true ) !== (string) $value ) {
					return false;
				}
			}
			return true;
		} finally {
			self::release_link_lock( $sub, $lock );
		}
	}

	private static function valid_claims( $claims, array $data ) {
		if ( ! is_array( $claims ) || empty( $claims['sub'] ) || empty( $claims['email'] ) ) {
			return false;
		}
		$client_id      = trim( (string) get_option( 'sauth_google_client_id', get_option( 'sa_google_client_id', '' ) ) );
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

	private static function acquire_link_lock( $sub ) {
		$key   = 'sauth_google_registration_link_lock_' . hash( 'sha256', (string) $sub );
		$token = SA_Security::random_token( 16 );
		$value = array( 'token' => $token, 'expires' => time() + self::LOCK_TTL );
		if ( add_option( $key, $value, '', false ) ) {
			return $token;
		}
		$current = get_option( $key, array() );
		if ( is_array( $current ) && ! empty( $current['expires'] ) && (int) $current['expires'] < time() ) {
			delete_option( $key );
			if ( add_option( $key, $value, '', false ) ) {
				return $token;
			}
		}
		return '';
	}

	private static function release_link_lock( $sub, $token ) {
		$key     = 'sauth_google_registration_link_lock_' . hash( 'sha256', (string) $sub );
		$current = get_option( $key, array() );
		if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], (string) $token ) ) {
			delete_option( $key );
		}
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
