<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02 Modern Authentication contract.
 *
 * Browser-facing capability discovery is advisory only. Server-side credential,
 * membership, risk and authorization checks remain authoritative.
 */
final class SAUTH_Modern_Auth {
	const CONTRACT_VERSION = '1.0.0';
	const UPGRADE_WINDOW    = 180;
	const FEDCM_NONCE_TTL   = 300;
	const MAX_RELATED_ORIGINS = 5;

	private static $feature_ids = array(
		'F02-X-24-001','F02-X-24-002','F02-X-24-003','F02-X-24-004',
		'F02-X-24-005','F02-X-24-006','F02-X-24-007','F02-X-24-008',
		'F02-X-24-009','F02-X-24-010','F02-X-24-011','F02-X-24-012',
		'F02-X-24-013','F02-X-24-014','F02-X-24-015','F02-X-24-016',
		'F02-X-24-017','F02-X-24-018','F02-X-24-019','F02-X-24-020',
		'F02-X-24-021','F02-X-24-022','F02-X-24-023','F02-X-24-024',
	);

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'localize_assets' ), 40 );
		add_action( 'sauth_event_recorded', array( __CLASS__, 'observe_authentication_event' ), 20, 1 );
		add_action( 'wp_ajax_sauth_modern_credential_signal', array( __CLASS__, 'credential_signal_ajax' ) );
		add_action( 'wp_ajax_nopriv_sauth_modern_fedcm_begin', array( __CLASS__, 'fedcm_begin_ajax' ) );
		add_action( 'wp_ajax_sauth_modern_fedcm_begin', array( __CLASS__, 'fedcm_begin_ajax' ) );
		add_action( 'wp_ajax_nopriv_sauth_modern_fedcm_finish', array( __CLASS__, 'fedcm_finish_ajax' ) );
		add_action( 'wp_ajax_sauth_modern_fedcm_finish', array( __CLASS__, 'fedcm_finish_ajax' ) );
		add_filter( 'sauth_modern_auth_features_v1', array( __CLASS__, 'filter_feature_registry' ) );
	}

	public static function feature_ids() {
		return self::$feature_ids;
	}

	public static function feature_registry() {
		$status = array(
			'001'=>'active','002'=>'active','003'=>'active','004'=>'active','005'=>'active','006'=>'active',
			'007'=>'active','008'=>'active','009'=>'active','010'=>'active','011'=>'active','012'=>'active',
			'013'=>'active','014'=>'active','015'=>'active','016'=>'active','017'=>'active','018'=>'active',
			'019'=>'active','020'=>'adapter-ready','021'=>'adapter-ready','022'=>'active','023'=>'progressive','024'=>'adapter-ready',
		);
		$out = array();
		foreach ( self::$feature_ids as $id ) {
			$key = substr( $id, -3 );
			$out[ $id ] = array( 'status' => $status[ $key ], 'contract' => self::CONTRACT_VERSION );
		}
		return $out;
	}

	public static function filter_feature_registry( $registry ) {
		$registry = is_array( $registry ) ? $registry : array();
		return array_merge( $registry, self::feature_registry() );
	}

	public static function localize_assets() {
		if ( ! class_exists( 'SA_Access_Control' ) || ! SA_Access_Control::is_file02_page() ) { return; }
		if ( ! wp_script_is( 'sauth-authentication', 'enqueued' ) ) { return; }
		$fedcm = apply_filters( 'sauth_fedcm_browser_config_v1', array() );
		$fedcm = is_array( $fedcm ) ? $fedcm : array();
		if ( ! empty( $fedcm['configURL'] ) && 0 !== strpos( (string) $fedcm['configURL'], 'https://' ) ) { $fedcm = array(); }
		$signals = is_user_logged_in() && class_exists( 'SAUTH_Passkeys' ) && is_callable( array( 'SAUTH_Passkeys', 'browser_signal_payload' ) )
			? SAUTH_Passkeys::browser_signal_payload( get_current_user_id() )
			: array();
		wp_localize_script(
			'sauth-authentication',
			'SabriAuthModern',
			array(
				'contractVersion' => self::CONTRACT_VERSION,
				'featureIds'      => self::$feature_ids,
				'conditional'     => true,
				'hybridHints'     => true,
				'credentialSignals' => true,
				'credentialSignalPayload' => $signals,
				'fedcmProgressive'  => ! empty( $fedcm ),
				'fedcmProvider'     => $fedcm,
				'upgradeWindow'   => is_user_logged_in() ? self::upgrade_window( get_current_user_id() ) : array(),
				'passkeyManagerUrl' => class_exists( 'SAUTH_Passkeys' ) ? SAUTH_Passkeys::manager_url() : home_url( '/account-passkeys/' ),
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => is_user_logged_in() ? wp_create_nonce( 'sauth_modern_auth' ) : '',
				'cryptoRegistry'  => self::crypto_registry(),
			)
		);
	}

	public static function observe_authentication_event( $event ) {
		if ( ! is_array( $event ) || 'AccountAuthenticationSucceeded.v1' !== (string) ( $event['event_name'] ?? '' ) ) { return; }
		$user_id = absint( $event['subject_user_id'] ?? 0 );
		$method  = sanitize_key( (string) ( $event['payload']['method'] ?? '' ) );
		if ( ! $user_id || ! in_array( $method, array( 'password','google','google_oidc' ), true ) ) { return; }
		$record = array(
			'user_id'     => $user_id,
			'fingerprint' => SA_Security::client_fingerprint(),
			'method'      => $method,
			'issued_at'   => time(),
			'expires_at'  => time() + self::UPGRADE_WINDOW,
		);
		set_transient( self::upgrade_key( $user_id ), $record, self::UPGRADE_WINDOW );
	}

	public static function upgrade_window( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) { return array(); }
		$record = get_transient( self::upgrade_key( $user_id ) );
		if ( ! is_array( $record )
			|| absint( $record['user_id'] ?? 0 ) !== $user_id
			|| absint( $record['expires_at'] ?? 0 ) <= time()
			|| ! hash_equals( (string) ( $record['fingerprint'] ?? '' ), SA_Security::client_fingerprint() ) ) {
			delete_transient( self::upgrade_key( $user_id ) );
			return array();
		}
		return array(
			'eligible'    => true,
			'expiresAt'   => absint( $record['expires_at'] ),
			'method'      => sanitize_key( (string) $record['method'] ),
		);
	}

	public static function credential_signal_ajax() {
		if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'code'=>'authentication_required' ), 401 ); }
		check_ajax_referer( 'sauth_modern_auth', 'nonce' );
		$signal = isset( $_POST['signal'] ) ? sanitize_key( wp_unslash( $_POST['signal'] ) ) : '';
		if ( ! in_array( $signal, array( 'all_accepted_credentials','unknown_credential','current_user_details' ), true ) ) {
			wp_send_json_error( array( 'code'=>'signal_invalid' ), 400 );
		}
		/* Browser Credential Management signals are advisory. They never mutate
		 * canonical credential truth on their own. */
		SA_Membership_Adapter::audit( 'browser_credential_signal_observed', get_current_user_id(), array( 'signal' => $signal ) );
		wp_send_json_success( array( 'accepted'=>true, 'authoritative'=>false ) );
	}

	public static function related_origins() {
		$raw = get_option( 'sauth_related_origins', array() );
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();
		foreach ( $raw as $origin ) {
			$normalized = self::normalize_origin( $origin );
			if ( '' !== $normalized ) { $out[ $normalized ] = $normalized; }
			if ( count( $out ) >= self::MAX_RELATED_ORIGINS ) { break; }
		}
		return array_values( $out );
	}

	public static function normalize_origin( $origin ) {
		$origin = trim( (string) $origin );
		if ( '' === $origin || false !== strpos( $origin, '*' ) ) { return ''; }
		$parts = wp_parse_url( $origin );
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) || empty( $parts['host'] ) ) { return ''; }
		if ( ! empty( $parts['path'] ) && '/' !== $parts['path'] ) { return ''; }
		if ( ! empty( $parts['query'] ) || ! empty( $parts['fragment'] ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) { return ''; }
		$port = isset( $parts['port'] ) ? ':' . absint( $parts['port'] ) : '';
		return 'https://' . strtolower( (string) $parts['host'] ) . $port;
	}

	public static function related_origin_manifest() {
		return array( 'origins' => self::related_origins() );
	}

	public static function crypto_registry() {
		return array(
			'webauthn' => array(
				array( 'name'=>'ES256', 'cose_alg'=>-7, 'status'=>'active' ),
				array( 'name'=>'RS256', 'cose_alg'=>-257, 'status'=>'active' ),
			),
			'dpop' => array( 'ES256','RS256' ),
			'private_key_export' => false,
		);
	}

	public static function fedcm_begin_ajax() {
		if ( class_exists( 'SAUTH_Operations' ) && SAUTH_Operations::safe_mode() ) {
			wp_send_json_error( array( 'code'=>'safe_mode_active' ), 503 );
		}
		$nonce = SA_Security::random_token( 32 );
		$id    = strtolower( wp_generate_uuid4() );
		if ( '' === $nonce ) { wp_send_json_error( array( 'code'=>'nonce_generation_failed' ), 503 ); }
		$record = array(
			'nonce_hash'  => hash( 'sha256', $nonce ),
			'fingerprint' => SA_Security::client_fingerprint(),
			'created_at'  => time(),
			'expires_at'  => time() + self::FEDCM_NONCE_TTL,
		);
		set_transient( 'sauth_fedcm_' . $id, $record, self::FEDCM_NONCE_TTL );
		$stored = get_transient( 'sauth_fedcm_' . $id );
		if ( ! is_array( $stored ) || $stored !== $record ) { wp_send_json_error( array( 'code'=>'nonce_store_failed' ), 503 ); }
		wp_send_json_success( array( 'nonceId'=>$id, 'nonce'=>$nonce, 'progressive'=>true ) );
	}

	public static function fedcm_finish_ajax() {
		$id    = isset( $_POST['nonce_id'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['nonce_id'] ) ) ) : '';
		$nonce = isset( $_POST['nonce'] ) ? (string) wp_unslash( $_POST['nonce'] ) : '';
		$token = isset( $_POST['token'] ) ? (string) wp_unslash( $_POST['token'] ) : '';
		if ( ! preg_match( '/^[0-9a-f-]{36}$/', $id ) || strlen( $nonce ) > 512 || strlen( $token ) > 16384 ) {
			wp_send_json_error( array( 'code'=>'fedcm_payload_invalid' ), 400 );
		}
		$key = 'sauth_fedcm_' . $id;
		$record = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $record )
			|| absint( $record['expires_at'] ?? 0 ) <= time()
			|| ! hash_equals( (string) $record['fingerprint'], SA_Security::client_fingerprint() )
			|| ! hash_equals( (string) $record['nonce_hash'], hash( 'sha256', $nonce ) ) ) {
			wp_send_json_error( array( 'code'=>'fedcm_nonce_invalid' ), 400 );
		}
		$verified = apply_filters( 'sauth_fedcm_verify_token_v1', null, $token, $nonce, $record );
		$token = ''; $nonce = ''; unset( $_POST['token'], $_POST['nonce'] );
		if ( ! is_array( $verified ) || empty( $verified['verified'] ) || empty( $verified['user_id'] ) ) {
			wp_send_json_error( array( 'code'=>'fedcm_server_verification_unavailable' ), 503 );
		}
		/* FedCM browser mediation is never sufficient by itself: the verifier
		 * must return a server-validated local user subject. */
		$user_id = absint( $verified['user_id'] );
		if ( class_exists( 'SAUTH_Security_Orchestrator' ) && SAUTH_Security_Orchestrator::authentication_blocked( $user_id ) ) {
			wp_send_json_error( array( 'code'=>'emergency_lockdown_active' ), 403 );
		}
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) { wp_send_json_error( array( 'code'=>'fedcm_subject_invalid' ), 400 ); }
		$completion = SAUTH_Account_Contract::completion_state( $user_id, array( 'purpose'=>'fedcm_sign_in' ) );
		$membership = SA_Membership_Adapter::membership_assertion( $user_id, 'clinical_identity_link', 'fedcm_sign_in' );
		if ( ! SA_Membership_Adapter::sign_in_allowed( $membership, $completion ) ) {
			wp_send_json_error( array( 'code'=>'membership_not_eligible' ), 403 );
		}
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, false, is_ssl() );
		if ( ! SAUTH_Login_Risk::record_successful_login( $user_id, 'fedcm', 0 ) ) {
			SAUTH_Session_Manager::revoke_user_sessions( $user_id, 'fedcm_risk_store_failed' );
			wp_clear_auth_cookie();
			wp_send_json_error( array( 'code'=>'fedcm_session_unverified' ), 503 );
		}
		SA_Membership_Adapter::audit( 'fedcm_server_verified_sign_in', $user_id );
		wp_send_json_success( array( 'redirect'=>home_url( '/' ) ) );
	}

	public static function portability_metadata( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) { return array(); }
		$table = $wpdb->prefix . 'sauth_passkeys';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT public_id,algorithm,attachment,transports,backup_eligible,status,created_at,last_used_at FROM {$table} WHERE user_id=%d ORDER BY id ASC", $user_id ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	private static function upgrade_key( $user_id ) {
		return 'sauth_upgrade_' . absint( $user_id ) . '_' . substr( hash_hmac( 'sha256', SA_Security::client_fingerprint(), wp_salt( 'auth' ) ), 0, 20 );
	}
}
