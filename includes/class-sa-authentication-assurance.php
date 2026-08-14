<?php

defined( 'ABSPATH' ) || exit;

/**
 * Session-, purpose- and scope-bound authentication assurance owned by File 02.
 *
 * File 00 owns membership/identity prerequisites, not authentication factors.
 * Current strong authentication is therefore consumed from File 02 passkey
 * assurance and is revalidated against a fresh File 00 membership assertion.
 */
final class SA_Authentication_Assurance {
	const CONTRACT_NAME     = 'sa.cf01.authentication-assurance';
	const CONTRACT_VERSION  = '1.0.0';
	const PROVIDER_VERSION  = '1.0.0';
	const PENDING_INDEX_TTL = 900; // Legacy cleanup compatibility only.
	const MAX_SCOPE_BYTES   = 2048;

	private static $purposes = array(
		'authentication_sign_in',
		'clinical_sign_in',
		'authentication_link',
		'authentication_unlink',
		'prescription_sign',
		'clinical_export',
		'clinical_transfer',
		'break_glass',
		'guardian_sensitive',
		'key_recovery',
	);

	public static function init() {
		/* Pending pre-login File 00 factor receipts were retired with File 00 MFA.
		 * Keep this hook only to delete legacy pending state after a new login. */
		add_action( 'set_logged_in_cookie', array( __CLASS__, 'promote_pending_for_cookie' ), 20, 6 );
		add_action( 'clear_auth_cookie', array( __CLASS__, 'clear_current_session' ), 1 );
	}

	/** Whether a current File 02 strong-auth provider can be consumed. */
	public static function provider_available() {
		return class_exists( 'SA_Membership_Adapter' )
			&& SA_Membership_Adapter::available()
			&& class_exists( 'SAUTH_Passkeys' )
			&& is_callable( array( 'SAUTH_Passkeys', 'file00_assurance' ) );
	}

	/**
	 * Record a session-bound assurance receipt from an already verified File 02
	 * passkey ceremony. The `$code` parameter is retained only for the historical
	 * API signature; File 00 no longer owns TOTP/recovery-code verification.
	 *
	 * @return array<string,mixed>
	 */
	public static function verify_and_record( $user_id, $code, array $context = array() ) {
		$user_id = absint( $user_id );
		$purpose = sanitize_key( (string) ( $context['purpose'] ?? '' ) );
		$scope   = trim( (string) ( $context['scope'] ?? '' ) );
		$trace   = self::trace_id( $context['trace_id'] ?? '' );

		/* Reject before any scope HMAC or provider call. */
		if ( ! in_array( $purpose, self::$purposes, true ) || '' === $scope || strlen( $scope ) > self::MAX_SCOPE_BYTES ) {
			$result = self::empty_assertion( $purpose, '', $trace );
			$result['result']      = 'invalid';
			$result['reason_code'] = 'purpose_or_scope_invalid';
			return $result;
		}
		$result = self::empty_assertion( $purpose, $scope, $trace );
		$code = ''; // Explicitly discard retired compatibility factor material.

		if ( class_exists( 'SAUTH_Operations' ) && SAUTH_Operations::safe_mode() ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'safe_mode_active';
			return $result;
		}
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'subject_invalid';
			return $result;
		}
		if ( ! self::provider_available() ) {
			$result['reason_code'] = 'provider_unavailable';
			return $result;
		}

		$token = self::current_subject_session_token( $user_id );
		if ( '' === $token ) {
			/* Pre-login strong authentication must use the dedicated WebAuthn login
			 * ceremony; no legacy File 00 factor receipt may be fabricated here. */
			$result['result']      = 'invalid';
			$result['reason_code'] = 'passkey_ceremony_required';
			return $result;
		}

		$passkey = self::current_passkey_assurance( $user_id );
		if ( empty( $passkey['passkey_asserted'] ) || 'file02' !== (string) ( $passkey['owner'] ?? '' ) || 'webauthn_passkey' !== (string) ( $passkey['method'] ?? '' ) ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'fresh_passkey_assurance_required';
			return $result;
		}

		$membership = self::membership_for_purpose( $user_id, $purpose, $trace );
		$subject_uuid = (string) ( $membership['subject']['platform_uuid'] ?? '' );
		if ( 'allow' !== (string) ( $membership['result'] ?? '' ) || ! self::valid_uuid( $subject_uuid ) ) {
			$result['reason_code'] = 'membership_subject_unavailable';
			return $result;
		}

		$ttl            = self::ttl_for_purpose( $purpose );
		$verified_epoch = absint( $passkey['verified_at'] ?? 0 );
		$expires_epoch  = min( time() + $ttl, $verified_epoch + $ttl );
		if ( ! $verified_epoch || $verified_epoch > time() + 60 || $expires_epoch <= time() ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'provider_assurance_expired';
			return $result;
		}

		$scope_hash = self::scope_hash( $scope );
		$receipt = array(
			'contract'         => self::CONTRACT_NAME,
			'contract_version' => self::CONTRACT_VERSION,
			'provider_version' => (string) ( $passkey['contract_version'] ?? '' ),
			'subject_uuid'     => strtolower( $subject_uuid ),
			'purpose'          => $purpose,
			'scope_hash'       => $scope_hash,
			'method'           => 'webauthn_passkey',
			'assurance_level'  => 'aal2',
			'verified_at'      => gmdate( 'c', $verified_epoch ),
			'expires_at'       => gmdate( 'c', $expires_epoch ),
			'trace_id'         => $trace,
			'fingerprint'      => SA_Security::client_fingerprint(),
			'session_binding'  => self::session_binding( $token ),
		);

		$key = self::session_receipt_key( $user_id, $token, $purpose, $scope_hash );
		$receipt_ttl = max( 1, $expires_epoch - time() );
		if ( ! self::write_transient_verified( $key, $receipt, $receipt_ttl ) ) {
			$result['reason_code'] = 'assurance_store_failed';
			return $result;
		}
		if ( ! self::index_session( $user_id, $token, $key, $receipt_ttl ) ) {
			delete_transient( $key );
			$result['reason_code'] = 'assurance_store_failed';
			return $result;
		}
		return self::public_receipt( $receipt, 'valid', 'authentication_verified' );
	}

	/** Return a current session-bound assurance projection. */
	public static function assertion( $user_id, $purpose, $scope ) {
		$user_id = absint( $user_id );
		$purpose = sanitize_key( (string) $purpose );
		$scope   = trim( (string) $scope );
		$trace   = self::trace_id( '' );
		if ( ! in_array( $purpose, self::$purposes, true ) || '' === $scope || strlen( $scope ) > self::MAX_SCOPE_BYTES ) {
			$result = self::empty_assertion( $purpose, '', $trace );
			$result['result']      = 'invalid';
			$result['reason_code'] = 'purpose_or_scope_invalid';
			return $result;
		}
		$result = self::empty_assertion( $purpose, $scope, $trace );
		if ( ! $user_id || ( class_exists( 'SAUTH_Operations' ) && SAUTH_Operations::safe_mode() ) ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = ! $user_id ? 'subject_invalid' : 'safe_mode_active';
			return $result;
		}
		$token = self::current_subject_session_token( $user_id );
		if ( '' === $token ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'authenticated_session_required';
			return $result;
		}
		$scope_hash = self::scope_hash( $scope );
		$key = self::session_receipt_key( $user_id, $token, $purpose, $scope_hash );
		$receipt = get_transient( $key );
		if ( ! self::valid_receipt( $receipt, $user_id, $token, $purpose, $scope_hash ) ) {
			delete_transient( $key );
			$result['result']      = 'invalid';
			$result['reason_code'] = 'assurance_missing_expired_or_mismatched';
			return $result;
		}
		$passkey = self::current_passkey_assurance( $user_id );
		if ( empty( $passkey['passkey_asserted'] )
			|| 'file02' !== (string) ( $passkey['owner'] ?? '' )
			|| 'webauthn_passkey' !== (string) ( $passkey['method'] ?? '' )
			|| ! defined( 'SAUTH_PASSKEY_CONTRACT_VERSION' )
			|| SAUTH_PASSKEY_CONTRACT_VERSION !== (string) ( $passkey['contract_version'] ?? '' ) ) {
			delete_transient( $key );
			$result['result']      = 'invalid';
			$result['reason_code'] = 'underlying_passkey_assurance_invalid';
			return $result;
		}
		return self::public_receipt( $receipt, 'valid', 'authentication_assurance_valid' );
	}

	/** Retired pending File 00 factor receipts are never promoted. */
	public static function promote_pending_for_cookie( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || 'logged_in' !== $scheme ) {
			return;
		}
		$index_key = self::pending_index_key( $user_id );
		$keys = get_transient( $index_key );
		if ( is_array( $keys ) ) {
			foreach ( array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) ) as $key ) {
				delete_transient( $key );
			}
		}
		delete_transient( $index_key );
	}

	/** Remove every File 02 assurance receipt associated with current session. */
	public static function clear_current_session() {
		$user_id = get_current_user_id();
		$token   = (string) wp_get_session_token();
		if ( ! $user_id || '' === $token ) {
			return;
		}
		$index_key = self::session_index_key( $user_id, $token );
		$keys = get_transient( $index_key );
		if ( is_array( $keys ) ) {
			foreach ( array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) ) as $key ) {
				delete_transient( $key );
			}
		}
		delete_transient( $index_key );
	}

	private static function current_passkey_assurance( $user_id ) {
		if ( ! self::provider_available() ) {
			return array();
		}
		try {
			$result = SAUTH_Passkeys::file00_assurance( array(), absint( $user_id ) );
			return is_array( $result ) ? $result : array();
		} catch ( Throwable $error ) {
			return array();
		}
	}

	private static function membership_for_purpose( $user_id, $purpose, $trace ) {
		$action_map = array(
			'prescription_sign'  => 'prescription_sign',
			'clinical_export'    => 'clinical_export',
			'clinical_transfer'  => 'clinical_export',
			'break_glass'        => 'break_glass',
			'guardian_sensitive' => 'guardian_sensitive',
			'key_recovery'       => 'key_recovery',
		);
		$action = isset( $action_map[ $purpose ] ) ? $action_map[ $purpose ] : 'clinical_identity_link';
		try {
			return SA_Membership_Adapter::membership_assertion( absint( $user_id ), $action, sanitize_key( $purpose ) );
		} catch ( Throwable $error ) {
			return array( 'result' => 'unknown', 'reason_code' => 'membership_exception', 'trace_id' => $trace );
		}
	}

	private static function index_session( $user_id, $token, $key, $ttl ) {
		$index_key = self::session_index_key( $user_id, $token );
		$keys      = get_transient( $index_key );
		$keys      = is_array( $keys ) ? $keys : array();
		$keys[]    = (string) $key;
		$keys      = array_slice( array_values( array_unique( $keys ) ), -30 );
		return self::write_transient_verified( $index_key, $keys, max( 1, absint( $ttl ) ) );
	}

	private static function write_transient_verified( $key, $value, $ttl ) {
		set_transient( $key, $value, max( 1, absint( $ttl ) ) );
		$stored = get_transient( $key );
		return false !== $stored && $stored === $value;
	}

	private static function valid_receipt( $receipt, $user_id, $token, $purpose, $scope_hash ) {
		if ( ! is_array( $receipt ) || self::expired( $receipt['expires_at'] ?? '' ) ) {
			return false;
		}
		if ( self::CONTRACT_NAME !== ( $receipt['contract'] ?? '' )
			|| self::CONTRACT_VERSION !== ( $receipt['contract_version'] ?? '' )
			|| 'webauthn_passkey' !== ( $receipt['method'] ?? '' )
			|| 'aal2' !== ( $receipt['assurance_level'] ?? '' ) ) {
			return false;
		}
		if ( ! hash_equals( (string) ( $receipt['purpose'] ?? '' ), (string) $purpose )
			|| ! hash_equals( (string) ( $receipt['scope_hash'] ?? '' ), (string) $scope_hash )
			|| ! hash_equals( (string) ( $receipt['fingerprint'] ?? '' ), SA_Security::client_fingerprint() )
			|| ! hash_equals( (string) ( $receipt['session_binding'] ?? '' ), self::session_binding( $token ) ) ) {
			return false;
		}
		$membership = self::membership_for_purpose( $user_id, $purpose, (string) ( $receipt['trace_id'] ?? '' ) );
		$live_uuid = (string) ( $membership['subject']['platform_uuid'] ?? '' );
		return 'allow' === (string) ( $membership['result'] ?? '' )
			&& self::valid_uuid( $live_uuid )
			&& self::valid_uuid( $receipt['subject_uuid'] ?? '' )
			&& hash_equals( strtolower( $live_uuid ), strtolower( (string) $receipt['subject_uuid'] ) );
	}

	private static function current_subject_session_token( $user_id ) {
		if ( ! is_user_logged_in() || get_current_user_id() !== absint( $user_id ) ) {
			return '';
		}
		return (string) wp_get_session_token();
	}

	private static function empty_assertion( $purpose, $scope, $trace ) {
		return array(
			'contract'         => self::CONTRACT_NAME,
			'contract_version' => self::CONTRACT_VERSION,
			'producer_version' => defined( 'SAUTH_VERSION' ) ? SAUTH_VERSION : '',
			'subject_uuid'     => '',
			'purpose'          => sanitize_key( $purpose ),
			'scope_hash'       => self::scope_hash( $scope ),
			'method'           => '',
			'assurance_level'  => '',
			'verified_at'      => '',
			'expires_at'       => '',
			'trace_id'         => $trace,
			'result'           => 'unknown',
			'reason_code'      => 'unresolved',
		);
	}

	private static function public_receipt( $receipt, $result, $reason ) {
		return array(
			'contract'         => self::CONTRACT_NAME,
			'contract_version' => self::CONTRACT_VERSION,
			'producer_version' => defined( 'SAUTH_VERSION' ) ? SAUTH_VERSION : '',
			'subject_uuid'     => (string) $receipt['subject_uuid'],
			'purpose'          => (string) $receipt['purpose'],
			'scope_hash'       => (string) $receipt['scope_hash'],
			'method'           => (string) $receipt['method'],
			'assurance_level'  => (string) $receipt['assurance_level'],
			'verified_at'      => (string) $receipt['verified_at'],
			'expires_at'       => (string) $receipt['expires_at'],
			'trace_id'         => (string) $receipt['trace_id'],
			'result'           => $result,
			'reason_code'      => $reason,
		);
	}

	private static function ttl_for_purpose( $purpose ) {
		$map = array(
			'authentication_sign_in' => 300,
			'clinical_sign_in'       => 900,
			'authentication_link'    => 300,
			'authentication_unlink'  => 300,
			'prescription_sign'      => 300,
			'clinical_export'        => 300,
			'clinical_transfer'      => 300,
			'break_glass'            => 120,
			'guardian_sensitive'     => 300,
			'key_recovery'           => 120,
		);
		return isset( $map[ $purpose ] ) ? (int) $map[ $purpose ] : 120;
	}

	private static function pending_index_key( $user_id ) {
		return 'sa_cf01_pending_index_' . substr( hash_hmac( 'sha256', absint( $user_id ) . '|' . SA_Security::client_fingerprint(), wp_salt( 'nonce' ) ), 0, 40 );
	}

	private static function session_index_key( $user_id, $token ) {
		return 'sa_cf01_session_index_' . substr( hash_hmac( 'sha256', absint( $user_id ) . '|' . self::session_binding( $token ), wp_salt( 'nonce' ) ), 0, 40 );
	}

	private static function session_receipt_key( $user_id, $token, $purpose, $scope_hash ) {
		return 'sa_cf01_session_' . substr( hash_hmac( 'sha256', absint( $user_id ) . '|' . self::session_binding( $token ) . '|' . $purpose . '|' . $scope_hash, wp_salt( 'nonce' ) ), 0, 40 );
	}

	private static function session_binding( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	private static function scope_hash( $scope ) {
		$scope = trim( (string) $scope );
		return '' === $scope ? '' : hash_hmac( 'sha256', $scope, wp_salt( 'nonce' ) );
	}

	private static function expired( $value ) {
		$timestamp = strtotime( (string) $value );
		return false === $timestamp || $timestamp <= time();
	}

	private static function trace_id( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return self::valid_uuid( $value ) ? $value : strtolower( wp_generate_uuid4() );
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
