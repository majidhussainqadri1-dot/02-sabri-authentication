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
	const CLAIM_TTL   = 3600;
	const CLAIM_HOOK  = 'sauth_google_registration_release_claim';

	public static function init() {
		add_action( 'admin_post_nopriv_sauth_google_registration_start', array( __CLASS__, 'start' ) );
		add_action( 'admin_post_nopriv_sauth_google_registration_callback', array( __CLASS__, 'callback' ) );
		add_action( self::CLAIM_HOOK, array( __CLASS__, 'release_claim' ), 10, 1 );
	}

	public static function callback_url() {
		return admin_url( 'admin-post.php?action=sauth_google_registration_callback', 'https' );
	}

	public static function start_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=sauth_google_registration_start' ), 'sauth_google_registration_start' );
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
		$state    = SA_Security::random_token( 32 );
		$nonce    = SA_Security::random_token( 32 );
		$verifier = SA_Security::random_token( 48 );
		if ( '' === $state || '' === $nonce || '' === $verifier ) {
			self::fail( 'A secure Google registration session could not be created.' );
		}
		$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
		$record = array(
			'nonce'       => $nonce,
			'verifier'    => $verifier,
			'fingerprint' => SA_Security::client_fingerprint(),
			'created_at'  => time(),
		);
		$key = self::state_key( $state );
		set_transient( $key, $record, self::STATE_TTL );
		if ( get_transient( $key ) !== $record ) {
			self::fail( 'The Google registration session could not be stored safely.' );
		}
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
		if ( '' === $state || strlen( $state ) > 256 || '' === $cookie || strlen( $cookie ) > 256 ) {
			self::fail( 'The Google registration session could not be verified.' );
		}
		$key = self::state_key( $state );
		$data = get_transient( $key );
		if ( ! hash_equals( $cookie, $state ) || ! is_array( $data ) ) {
			self::fail( 'The Google registration session could not be verified.' );
		}
		delete_transient( $key );
		self::state_cookie( '', time() - HOUR_IN_SECONDS );
		$created = absint( $data['created_at'] ?? 0 );
		$now = time();
		if ( ! $created || $created > $now + 60 || $created < $now - self::STATE_TTL - 60 ) {
			self::fail( 'The Google registration session expired.' );
		}
		if ( empty( $data['fingerprint'] ) || ! hash_equals( (string) $data['fingerprint'], SA_Security::client_fingerprint() ) ) {
			self::fail( 'The Google registration session changed unexpectedly.' );
		}
		if ( isset( $_GET['error'] ) ) {
			self::fail( 'Google registration was cancelled or denied.' );
		}
		if ( SAUTH_Operations::safe_mode() || ! SAUTH_Account_Contract::provider_available() ) {
			self::fail( 'Google registration is temporarily unavailable. No account was created.' );
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( '' === $code || strlen( $code ) > 4096 ) {
			self::fail( 'Google did not return a valid authorization code.' );
		}
		$client_id = trim( (string) get_option( 'sauth_google_client_id', get_option( 'sa_google_client_id', '' ) ) );
		$client_secret = SA_Security::decrypt( (string) get_option( 'sauth_google_client_secret', get_option( 'sa_google_client_secret', '' ) ) );
		$started = microtime( true );
		$response = wp_remote_post(
			SA_Google_OAuth::TOKEN_ENDPOINT,
			array(
				'timeout' => 15,
				'limit_response_size' => 1048576,
				'body' => array(
					'code' => $code,
					'client_id' => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri' => self::callback_url(),
					'grant_type' => 'authorization_code',
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
		if ( ! is_array( $token ) || empty( $token['id_token'] ) || strlen( (string) $token['id_token'] ) > 16384 ) {
			SAUTH_Provider_Health::record_failure( 'google', 'registration_identity_token_missing', $latency );
			self::fail( 'Google did not return a valid identity token.' );
		}
		$id_token = (string) $token['id_token'];
		$token = array();
		$verify = wp_remote_get(
			add_query_arg( 'id_token', $id_token, SA_Google_OAuth::TOKENINFO_URL ),
			array( 'timeout' => 15, 'limit_response_size' => 1048576 )
		);
		$id_token = '';
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
		if ( '' === $registration_token ) {
			self::fail( 'A secure registration continuation could not be created.' );
		}
		$context = array(
			'email'       => sanitize_email( $claims['email'] ),
			'sub'         => sanitize_text_field( $claims['sub'] ),
			'name'        => sanitize_text_field( (string) ( $claims['name'] ?? '' ) ),
			'picture'     => empty( $claims['picture'] ) ? '' : esc_url_raw( $claims['picture'] ),
			'fingerprint' => SA_Security::client_fingerprint(),
			'created_at'  => time(),
		);
		$claims = array();
		$context_key = self::context_key( $registration_token );
		set_transient( $context_key, $context, self::CONTEXT_TTL );
		if ( get_transient( $context_key ) !== $context ) {
			self::fail( 'The Google registration continuation could not be stored safely.' );
		}
		SAUTH_Provider_Health::record_success( 'google', $latency );
		wp_safe_redirect( add_query_arg( 'google_registration', rawurlencode( $registration_token ), SA_Security::page_url( 'signup', wp_registration_url() ) ) );
		exit;
	}

	public static function context( $token ) {
		$token = sanitize_text_field( (string) $token );
		if ( '' === $token || strlen( $token ) > 256 ) {
			return array();
		}
		$data = get_transient( self::context_key( $token ) );
		if ( ! is_array( $data ) || empty( $data['email'] ) || empty( $data['sub'] ) || empty( $data['fingerprint'] ) ) {
			return array();
		}
		$created = absint( $data['created_at'] ?? 0 );
		$now = time();
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

	/**
	 * Atomically consume one registration continuation. The durable hashed claim
	 * prevents concurrent/replayed form submissions from reusing Google proof.
	 */
	public static function consume( $token ) {
		$token = sanitize_text_field( (string) $token );
		if ( '' === $token || strlen( $token ) > 256 ) {
			return array();
		}
		$claim_key = self::claim_key( $token );
		if ( ! add_option( $claim_key, time(), '', false ) ) {
			return array();
		}
		if ( function_exists( 'wp_schedule_single_event' ) ) {
			wp_schedule_single_event( time() + self::CLAIM_TTL, self::CLAIM_HOOK, array( $claim_key ) );
		}
		$data = self::context( $token );
		delete_transient( self::context_key( $token ) );
		return $data;
	}

	public static function release_claim( $claim_key ) {
		$claim_key = sanitize_key( (string) $claim_key );
		if ( 0 === strpos( $claim_key, 'sauth_google_registration_claim_' ) ) {
			delete_option( $claim_key );
		}
	}

	public static function finalize_link( $user_id, array $context ) {
		$user_id = absint( $user_id );
		$email = sanitize_email( (string) ( $context['email'] ?? '' ) );
		$sub = sanitize_text_field( (string) ( $context['sub'] ?? '' ) );
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user instanceof WP_User || '' === $sub || ! is_email( $email ) || ! hash_equals( strtolower( (string) $user->user_email ), strtolower( $email ) ) ) {
			return false;
		}
		$lock = self::acquire_link_lock( $sub );
		if ( '' === $lock ) { return false; }
		try {
			$matches = array();
			foreach ( array( '_sauth_google_sub', '_sa_google_sub' ) as $meta_key ) {
				foreach ( get_users( array( 'meta_key' => $meta_key, 'meta_value' => $sub, 'number' => 3, 'count_total' => false ) ) as $match ) {
					$matches[ (int) $match->ID ] = $match;
				}
			}
			foreach ( $matches as $match ) {
				if ( (int) $match->ID !== $user_id ) { return false; }
			}
			$projection = array(
				'sub' => $sub,
				'email' => $email,
				'email_verified' => '1',
				'account' => '1',
				'linked_at' => current_time( 'mysql', true ),
				'link_version' => '4',
				'picture' => empty( $context['picture'] ) ? '' : esc_url_raw( $context['picture'] ),
			);
			$before = array();
			foreach ( array( '_sauth_google_', '_sa_google_' ) as $prefix ) {
				foreach ( $projection as $suffix => $value ) {
					$key = $prefix . $suffix;
					$before[ $key ] = get_user_meta( $user_id, $key, true );
					if ( 'picture' === $suffix && '' === $value ) { delete_user_meta( $user_id, $key ); }
					else { update_user_meta( $user_id, $key, $value ); }
				}
			}
			foreach ( array( '_sauth_google_', '_sa_google_' ) as $prefix ) {
				foreach ( $projection as $suffix => $value ) {
					$key = $prefix . $suffix;
					$expected = ( 'picture' === $suffix && '' === $value ) ? '' : (string) $value;
					if ( ! hash_equals( $expected, (string) get_user_meta( $user_id, $key, true ) ) ) {
						foreach ( $before as $restore_key => $restore_value ) {
							if ( '' === (string) $restore_value ) { delete_user_meta( $user_id, $restore_key ); }
							else { update_user_meta( $user_id, $restore_key, $restore_value ); }
						}
						return false;
					}
				}
			}
			return true;
		} finally {
			self::release_link_lock( $sub, $lock );
		}
	}

	private static function valid_claims( $claims, array $data ) {
		if ( ! is_array( $claims ) || empty( $claims['sub'] ) || empty( $claims['email'] ) || ! isset( $claims['iat'], $claims['exp'] ) ) {
			return false;
		}
		if ( strlen( (string) $claims['sub'] ) > 255 || strlen( (string) $claims['email'] ) > 320 ) { return false; }
		$client_id = trim( (string) get_option( 'sauth_google_client_id', get_option( 'sa_google_client_id', '' ) ) );
		$issuer_ok = isset( $claims['iss'] ) && in_array( $claims['iss'], array( 'https://accounts.google.com', 'accounts.google.com' ), true );
		$nonce_ok = isset( $claims['nonce'], $data['nonce'] ) && hash_equals( (string) $data['nonce'], (string) $claims['nonce'] );
		$email_verified = isset( $claims['email_verified'] ) && in_array( $claims['email_verified'], array( true, 'true', '1', 1 ), true );
		$audience = $claims['aud'] ?? '';
		$audience_ok = is_array( $audience ) ? count( $audience ) <= 8 && in_array( $client_id, $audience, true ) : hash_equals( $client_id, (string) $audience );
		if ( is_array( $audience ) && count( $audience ) > 1 ) {
			$audience_ok = $audience_ok && isset( $claims['azp'] ) && hash_equals( $client_id, (string) $claims['azp'] );
		}
		$now = time(); $iat = (int) $claims['iat']; $exp = (int) $claims['exp'];
		$time_ok = $iat > 0 && $iat <= $now + 60 && $iat >= $now - 600 && $exp > $now && $exp > $iat && $exp <= $iat + 7200;
		return $issuer_ok && $nonce_ok && $email_verified && $audience_ok && $time_ok && is_email( $claims['email'] );
	}

	private static function acquire_link_lock( $sub ) {
		$key = 'sauth_google_registration_link_lock_' . hash( 'sha256', (string) $sub );
		$token = SA_Security::random_token( 16 );
		if ( '' === $token ) { return ''; }
		$value = array( 'token' => $token, 'expires' => time() + self::LOCK_TTL );
		if ( add_option( $key, $value, '', false ) ) { return $token; }
		$current = get_option( $key, array() );
		if ( is_array( $current ) && ! empty( $current['expires'] ) && (int) $current['expires'] < time() ) {
			delete_option( $key );
			if ( add_option( $key, $value, '', false ) ) { return $token; }
		}
		return '';
	}

	private static function release_link_lock( $sub, $token ) {
		$key = 'sauth_google_registration_link_lock_' . hash( 'sha256', (string) $sub );
		$current = get_option( $key, array() );
		if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], (string) $token ) ) { delete_option( $key ); }
	}

	private static function state_key( $state ) { return 'sauth_google_registration_state_' . hash( 'sha256', (string) $state ); }
	private static function context_key( $token ) { return 'sauth_google_registration_context_' . hash( 'sha256', (string) $token ); }
	private static function claim_key( $token ) { return 'sauth_google_registration_claim_' . substr( hash( 'sha256', (string) $token ), 0, 40 ); }

	private static function state_cookie( $value, $expires ) {
		$options = array( 'expires' => $expires, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax' );
		if ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) { $options['domain'] = COOKIE_DOMAIN; }
		setcookie( self::COOKIE_NAME, $value, $options );
	}

	private static function fail( $message ) {
		wp_safe_redirect( SA_Security::message_url( 'signup', 'error', $message ) );
		exit;
	}
}
