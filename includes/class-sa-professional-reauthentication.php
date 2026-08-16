<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02-owned current-password plus AAL2 reauthentication bridge.
 *
 * This contract proves authentication only. File 09 and CF-01 must still
 * revalidate professional status, membership, purpose, object, relationship,
 * consent, field and record version through their canonical owners.
 */
final class SA_Professional_Reauthentication {
	const CONTRACT_NAME      = 'sa.professional-reauthentication';
	const CONTRACT_VERSION   = '1.0.0';
	const AUTH_PURPOSE       = 'clinical_sign_in';
	const MAX_SCOPE_BYTES    = 2048;
	const MAX_PASSWORD_BYTES = 4096;

	public static function init() {
		add_action( 'clear_auth_cookie', array( __CLASS__, 'clear_current_session' ), 4 );
	}

	/**
	 * Verify current password plus a fresh File 02 passkey assurance and record a
	 * session-bound professional reauthentication receipt. `$code` is retained
	 * only for historical API compatibility; File 00 no longer owns MFA codes.
	 *
	 * @return array<string,mixed>
	 */
	public static function verify_and_record( $user_id, $password, $code, $context = array() ) {
		$user_id = absint( $user_id );
		$context = is_array( $context ) ? $context : array();
		$scope   = trim( (string) ( $context['scope'] ?? '' ) );
		$trace   = self::trace_id( $context['trace_id'] ?? '' );
		$password = (string) $password;

		/* Bounds must precede empty_assertion(), which hashes the scope. */
		if ( '' === $scope || strlen( $scope ) > self::MAX_SCOPE_BYTES ) {
			$result = self::empty_assertion( '', $trace );
			$result['result'] = 'invalid';
			$result['reason_code'] = 'opaque_scope_invalid';
			return $result;
		}
		if ( '' === $password || strlen( $password ) > self::MAX_PASSWORD_BYTES ) {
			$result = self::empty_assertion( $scope, $trace );
			$result['result'] = 'invalid';
			$result['reason_code'] = 'current_password_invalid';
			return $result;
		}
		$result = self::empty_assertion( $scope, $trace );
		$code = '';

		if ( class_exists( 'SAUTH_Operations' ) && SAUTH_Operations::safe_mode() ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'safe_mode_active';
			return $result;
		}
		if ( ! class_exists( 'SA_Authentication_Assurance' ) || ! is_callable( array( 'SA_Authentication_Assurance', 'verify_and_record' ) ) ) {
			$result['reason_code'] = 'authentication_assurance_unavailable';
			return $result;
		}
		if ( ! $user_id || ! is_user_logged_in() || get_current_user_id() !== $user_id || '' === (string) wp_get_session_token() ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'current_authenticated_session_required';
			return $result;
		}

		try {
			$user = get_userdata( $user_id );
			$password_ok = $user && wp_check_password( $password, (string) $user->user_pass, $user_id );
		} catch ( Throwable $error ) {
			self::record_dependency_failure( 'password_verification', $error );
			$password_ok = false;
		}
		$password = '';
		if ( ! $password_ok ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'current_password_invalid';
			self::safe_observer_action( 'sa_professional_reauthentication_failed', array( $user_id, 'current_password_invalid', $trace ) );
			return $result;
		}

		try {
			$assurance = SA_Authentication_Assurance::verify_and_record(
				$user_id,
				'',
				array(
					'purpose'  => self::AUTH_PURPOSE,
					'scope'    => $scope,
					'trace_id' => $trace,
				)
			);
		} catch ( Throwable $error ) {
			self::record_dependency_failure( 'authentication_assurance', $error );
			$result['reason_code'] = 'authentication_assurance_unavailable';
			return $result;
		}
		$result = self::from_authentication_assurance( $assurance, $scope, $trace, true );
		if ( 'valid' !== $result['result'] ) {
			return $result;
		}
		if ( ! self::store_receipt( $user_id, $scope, $result ) ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'professional_receipt_store_failed';
			return $result;
		}
		self::safe_observer_action( 'sa_professional_reauthentication_verified', array( $result ) );
		return $result;
	}

	/** Return existing session-bound password plus AAL2 evidence. */
	public static function assertion( $user_id, $scope ) {
		$user_id = absint( $user_id );
		$scope   = trim( (string) $scope );
		$trace   = self::trace_id( '' );
		if ( '' === $scope || strlen( $scope ) > self::MAX_SCOPE_BYTES ) {
			$result = self::empty_assertion( '', $trace );
			$result['result'] = 'invalid';
			$result['reason_code'] = 'opaque_scope_invalid';
			return $result;
		}
		$result = self::empty_assertion( $scope, $trace );
		if ( class_exists( 'SAUTH_Operations' ) && SAUTH_Operations::safe_mode() ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'safe_mode_active';
			return $result;
		}
		if ( ! class_exists( 'SA_Authentication_Assurance' ) || ! is_callable( array( 'SA_Authentication_Assurance', 'assertion' ) ) ) {
			$result['reason_code'] = 'authentication_assurance_unavailable';
			return $result;
		}
		if ( ! $user_id || ! is_user_logged_in() || get_current_user_id() !== $user_id ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'current_authenticated_session_required';
			return $result;
		}
		$token = (string) wp_get_session_token();
		if ( '' === $token ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'current_authenticated_session_required';
			return $result;
		}
		$key = self::receipt_key( $user_id, $token, $scope );
		$stored = get_transient( $key );
		if ( ! self::valid_stored_receipt( $stored, $user_id, $token, $scope ) ) {
			delete_transient( $key );
			$result['result'] = 'invalid';
			$result['reason_code'] = 'professional_receipt_missing_expired_or_mismatched';
			return $result;
		}

		try {
			$assurance = SA_Authentication_Assurance::assertion( $user_id, self::AUTH_PURPOSE, $scope );
		} catch ( Throwable $error ) {
			self::record_dependency_failure( 'authentication_assertion', $error );
			delete_transient( $key );
			$result['result'] = 'invalid';
			$result['reason_code'] = 'underlying_authentication_assurance_unavailable';
			return $result;
		}
		$live = self::from_authentication_assurance( $assurance, $scope, $trace, false );
		if ( 'valid' !== $live['result']
			|| ! hash_equals( (string) $stored['subject_uuid'], (string) $live['subject_uuid'] )
			|| ! hash_equals( (string) $stored['scope_hash'], (string) $live['scope_hash'] )
			|| ! hash_equals( (string) ( $stored['provider_trace_id'] ?? '' ), (string) ( $live['provider_trace_id'] ?? '' ) ) ) {
			delete_transient( $key );
			$result['result'] = 'invalid';
			$result['reason_code'] = 'underlying_authentication_assurance_invalid';
			return $result;
		}
		return self::public_receipt( $stored );
	}

	public static function clear_current_session() {
		$token = (string) wp_get_session_token();
		if ( '' === $token ) {
			return;
		}
		$index_key = self::index_key( $token );
		$keys = get_transient( $index_key );
		if ( is_array( $keys ) ) {
			foreach ( $keys as $key ) {
				delete_transient( sanitize_key( $key ) );
			}
		}
		delete_transient( $index_key );
	}

	private static function store_receipt( $user_id, $scope, $receipt ) {
		$token = (string) wp_get_session_token();
		$ttl   = self::seconds_until( $receipt['expires_at'] ?? '' );
		if ( '' === $token || $ttl < 1 ) {
			return false;
		}
		$receipt['session_binding'] = self::session_binding( $token );
		$receipt['fingerprint'] = SA_Security::client_fingerprint();
		$receipt['password_verified'] = true;
		$receipt['password_binding'] = self::password_binding( $user_id );
		if ( '' === $receipt['password_binding'] ) { return false; }
		$key = self::receipt_key( $user_id, $token, $scope );
		if ( ! self::write_transient_verified( $key, $receipt, $ttl ) ) {
			return false;
		}
		$index_key = self::index_key( $token );
		$keys = get_transient( $index_key );
		$keys = is_array( $keys ) ? $keys : array();
		$keys[] = $key;
		$keys = array_slice( array_values( array_unique( $keys ) ), -20 );
		if ( ! self::write_transient_verified( $index_key, $keys, max( $ttl, 60 ) ) ) {
			delete_transient( $key );
			return false;
		}
		return true;
	}

	private static function valid_stored_receipt( $receipt, $user_id, $token, $scope ) {
		if ( ! is_array( $receipt )
			|| self::CONTRACT_NAME !== ( $receipt['contract'] ?? '' )
			|| self::CONTRACT_VERSION !== ( $receipt['contract_version'] ?? '' )
			|| empty( $receipt['password_verified'] )
			|| self::seconds_until( $receipt['expires_at'] ?? '' ) < 1 ) {
			return false;
		}
		if ( ! hash_equals( (string) ( $receipt['session_binding'] ?? '' ), self::session_binding( $token ) )
			|| ! hash_equals( (string) ( $receipt['fingerprint'] ?? '' ), SA_Security::client_fingerprint() )
			|| ! hash_equals( self::scope_hash( $scope ), (string) ( $receipt['scope_hash'] ?? '' ) )
			|| '' === self::password_binding( $user_id )
			|| ! hash_equals( (string) ( $receipt['password_binding'] ?? '' ), self::password_binding( $user_id ) ) ) {
			return false;
		}
		return self::valid_uuid( $receipt['subject_uuid'] ?? '' )
			&& 'valid' === ( $receipt['result'] ?? '' )
			&& 'professional_verification_review' === ( $receipt['purpose'] ?? '' )
			&& self::AUTH_PURPOSE === ( $receipt['authentication_purpose'] ?? '' )
			&& 'sa.cf01.authentication-assurance' === ( $receipt['provider_contract'] ?? '' )
			&& '1.0.0' === ( $receipt['provider_version'] ?? '' )
			&& hash_equals( self::scope_hash( $scope ), (string) ( $receipt['provider_scope_hash'] ?? '' ) )
			&& self::valid_uuid( $receipt['provider_trace_id'] ?? '' )
			&& hash_equals( strtolower( (string) ( $receipt['trace_id'] ?? '' ) ), strtolower( (string) ( $receipt['provider_trace_id'] ?? '' ) ) )
			&& 'aal2' === ( $receipt['assurance_level'] ?? '' )
			&& 'webauthn_passkey' === ( $receipt['method'] ?? '' );
	}

	private static function from_authentication_assurance( $assurance, $scope, $trace, $password_verified ) {
		$result = self::empty_assertion( $scope, $trace );
		if ( ! is_array( $assurance )
			|| 'sa.cf01.authentication-assurance' !== ( $assurance['contract'] ?? '' )
			|| '1.0.0' !== ( $assurance['contract_version'] ?? '' ) ) {
			$result['reason_code'] = 'authentication_contract_invalid';
			return $result;
		}
		$auth_result = sanitize_key( (string) ( $assurance['result'] ?? 'unknown' ) );
		if ( ! in_array( $auth_result, array( 'valid', 'invalid', 'unknown' ), true ) ) {
			$result['reason_code'] = 'authentication_result_invalid';
			return $result;
		}
		$result['result'] = $auth_result;
		$result['reason_code'] = 'valid' === $auth_result
			? 'professional_reauthentication_valid'
			: 'authentication_' . sanitize_key( (string) ( $assurance['reason_code'] ?? 'unknown' ) );
		$provider_scope_hash = (string) ( $assurance['scope_hash'] ?? '' );
		$provider_trace_id = (string) ( $assurance['trace_id'] ?? '' );
		$result['provider_contract'] = (string) ( $assurance['contract'] ?? '' );
		$result['provider_version'] = (string) ( $assurance['contract_version'] ?? '' );
		$result['provider_scope_hash'] = $provider_scope_hash;
		$result['provider_trace_id'] = $provider_trace_id;
		$result['subject_uuid']      = (string) ( $assurance['subject_uuid'] ?? '' );
		$result['scope_hash']        = self::scope_hash( $scope );
		$result['method']            = sanitize_key( (string) ( $assurance['method'] ?? '' ) );
		$result['assurance_level']   = sanitize_key( (string) ( $assurance['assurance_level'] ?? '' ) );
		$result['verified_at']       = (string) ( $assurance['verified_at'] ?? '' );
		$result['expires_at']        = (string) ( $assurance['expires_at'] ?? '' );
		$result['trace_id']          = self::valid_uuid( $provider_trace_id ) ? strtolower( $provider_trace_id ) : $trace;
		$result['password_verified'] = (bool) $password_verified;
		if ( 'valid' === $auth_result ) {
			$verified = strtotime( $result['verified_at'] );
			$expires  = strtotime( $result['expires_at'] );
			if ( ! self::valid_uuid( $result['subject_uuid'] )
				|| 'clinical_sign_in' !== (string) ( $assurance['purpose'] ?? '' )
				|| ! hash_equals( $result['scope_hash'], $provider_scope_hash )
				|| ! self::valid_uuid( $provider_trace_id )
				|| ( $password_verified && ! hash_equals( strtolower( $trace ), strtolower( $provider_trace_id ) ) )
				|| 'webauthn_passkey' !== $result['method']
				|| 'aal2' !== $result['assurance_level']
				|| false === $verified
				|| false === $expires
				|| $verified > time() + 60
				|| $verified < time() - 960
				|| $expires <= time()
				|| $expires > $verified + 900 ) {
				$result['result'] = 'unknown';
				$result['reason_code'] = 'authentication_evidence_invalid';
			}
		}
		return $result;
	}

	private static function public_receipt( $receipt ) {
		unset( $receipt['session_binding'], $receipt['fingerprint'], $receipt['password_binding'] );
		return $receipt;
	}


	private static function password_binding( $user_id ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user instanceof WP_User || '' === (string) $user->user_pass ) { return ''; }
		return hash_hmac( 'sha256', (string) $user->user_pass, wp_salt( 'auth' ) );
	}

	private static function write_transient_verified( $key, $value, $ttl ) {
		set_transient( $key, $value, max( 1, absint( $ttl ) ) );
		$stored = get_transient( $key );
		return false !== $stored && $stored === $value;
	}

	private static function receipt_key( $user_id, $token, $scope ) {
		return 'sa_prof_reauth_' . substr( hash_hmac( 'sha256', absint( $user_id ) . '|' . self::session_binding( $token ) . '|' . self::scope_hash( $scope ), wp_salt( 'nonce' ) ), 0, 40 );
	}

	private static function index_key( $token ) {
		return 'sa_prof_reauth_index_' . substr( self::session_binding( $token ), 0, 40 );
	}

	private static function session_binding( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
	}

	private static function scope_hash( $scope ) {
		$scope = trim( (string) $scope );
		return '' === $scope ? '' : hash_hmac( 'sha256', $scope, wp_salt( 'nonce' ) );
	}

	private static function seconds_until( $value ) {
		$timestamp = strtotime( (string) $value );
		return false === $timestamp ? 0 : max( 0, $timestamp - time() );
	}

	private static function empty_assertion( $scope, $trace ) {
		return array(
			'contract'               => self::CONTRACT_NAME,
			'contract_version'       => self::CONTRACT_VERSION,
			'producer_version'       => defined( 'SAUTH_VERSION' ) ? SAUTH_VERSION : '',
			'purpose'                => 'professional_verification_review',
			'authentication_purpose' => self::AUTH_PURPOSE,
			'provider_contract'       => '',
			'provider_version'        => '',
			'provider_scope_hash'     => '',
			'provider_trace_id'       => '',
			'subject_uuid'           => '',
			'scope_hash'             => self::scope_hash( $scope ),
			'method'                 => '',
			'assurance_level'        => '',
			'password_verified'      => false,
			'verified_at'            => '',
			'expires_at'             => '',
			'trace_id'               => $trace,
			'result'                 => 'unknown',
			'reason_code'            => 'unresolved',
		);
	}

	private static function record_dependency_failure( $operation, Throwable $error ) {
		$operation = sanitize_key( (string) $operation );
		$reference = substr( hash( 'sha256', $operation . '|' . get_class( $error ) . '|' . $error->getMessage() . '|' . $error->getFile() . ':' . $error->getLine() ), 0, 20 );
		update_option( 'sauth_professional_reauth_dependency_degraded_v1', array( 'operation' => $operation, 'reference' => $reference, 'recorded_at' => time() ), false );
	}

	private static function safe_observer_action( $hook, array $args ) {
		try {
			do_action_ref_array( (string) $hook, $args );
		} catch ( Throwable $error ) {
			self::record_dependency_failure( 'observer_' . sanitize_key( $hook ), $error );
		}
	}

	private static function trace_id( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return self::valid_uuid( $value ) ? $value : strtolower( wp_generate_uuid4() );
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
