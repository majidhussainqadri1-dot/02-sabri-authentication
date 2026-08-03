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
	const CONTRACT_NAME    = 'sa.professional-reauthentication';
	const CONTRACT_VERSION = '1.0.0';
	const AUTH_PURPOSE     = 'clinical_sign_in';

	public static function init() {
		add_action( 'clear_auth_cookie', array( __CLASS__, 'clear_current_session' ), 4 );
	}

	/**
	 * Verify current password and record session-bound AAL2 evidence.
	 *
	 * @param int    $user_id Current WordPress user ID.
	 * @param string $password Current account password.
	 * @param string $code File 00-owned TOTP or recovery code.
	 * @param array  $context Opaque scope and trace context.
	 * @return array<string,mixed>
	 */
	public static function verify_and_record( $user_id, $password, $code, $context = array() ) {
		$user_id = absint( $user_id );
		$context = is_array( $context ) ? $context : array();
		$scope   = trim( (string) ( isset( $context['scope'] ) ? $context['scope'] : '' ) );
		$trace   = self::trace_id( isset( $context['trace_id'] ) ? $context['trace_id'] : '' );
		$result  = self::empty_assertion( $scope, $trace );

		if ( ! class_exists( 'SA_Authentication_Assurance' )
			|| ! is_callable( array( 'SA_Authentication_Assurance', 'verify_and_record' ) ) ) {
			$result['reason_code'] = 'authentication_assurance_unavailable';
			return $result;
		}
		if ( ! $user_id || ! is_user_logged_in() || get_current_user_id() !== $user_id || '' === (string) wp_get_session_token() ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'current_authenticated_session_required';
			return $result;
		}
		if ( '' === $scope ) {
			$result['reason_code'] = 'opaque_scope_required';
			return $result;
		}
		$user = get_userdata( $user_id );
		if ( ! $user || ! wp_check_password( (string) $password, (string) $user->user_pass, $user_id ) ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'current_password_invalid';
			do_action( 'sa_professional_reauthentication_failed', $user_id, 'current_password_invalid', $trace );
			return $result;
		}

		$assurance = SA_Authentication_Assurance::verify_and_record(
			$user_id,
			(string) $code,
			array(
				'purpose'  => self::AUTH_PURPOSE,
				'scope'    => $scope,
				'trace_id' => $trace,
			)
		);
		$result = self::from_authentication_assurance( $assurance, $scope, $trace, true );
		if ( 'valid' !== $result['result'] ) {
			return $result;
		}
		if ( ! self::store_receipt( $user_id, $scope, $result ) ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'professional_receipt_store_failed';
			return $result;
		}
		do_action( 'sa_professional_reauthentication_verified', $result );
		return $result;
	}

	/**
	 * Return existing session-bound password plus AAL2 evidence.
	 *
	 * @param int    $user_id Current WordPress user ID.
	 * @param string $scope Opaque review/action scope.
	 * @return array<string,mixed>
	 */
	public static function assertion( $user_id, $scope ) {
		$user_id = absint( $user_id );
		$scope   = trim( (string) $scope );
		$trace   = self::trace_id( '' );
		$result  = self::empty_assertion( $scope, $trace );
		if ( ! class_exists( 'SA_Authentication_Assurance' )
			|| ! is_callable( array( 'SA_Authentication_Assurance', 'assertion' ) ) ) {
			$result['reason_code'] = 'authentication_assurance_unavailable';
			return $result;
		}
		if ( ! $user_id || ! is_user_logged_in() || get_current_user_id() !== $user_id || '' === $scope ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'current_authenticated_session_required';
			return $result;
		}
		$token = (string) wp_get_session_token();
		$key   = self::receipt_key( $user_id, $token, $scope );
		$stored = get_transient( $key );
		if ( ! self::valid_stored_receipt( $stored, $user_id, $token, $scope ) ) {
			delete_transient( $key );
			$result['result'] = 'invalid';
			$result['reason_code'] = 'professional_receipt_missing_expired_or_mismatched';
			return $result;
		}

		$assurance = SA_Authentication_Assurance::assertion( $user_id, self::AUTH_PURPOSE, $scope );
		$live = self::from_authentication_assurance( $assurance, $scope, $trace, false );
		if ( 'valid' !== $live['result']
			|| ! hash_equals( (string) $stored['subject_uuid'], (string) $live['subject_uuid'] )
			|| ! hash_equals( (string) $stored['scope_hash'], (string) $live['scope_hash'] ) ) {
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
		$ttl   = self::seconds_until( isset( $receipt['expires_at'] ) ? $receipt['expires_at'] : '' );
		if ( '' === $token || $ttl < 1 ) {
			return false;
		}
		$receipt['session_binding'] = self::session_binding( $token );
		$receipt['fingerprint'] = SA_Security::client_fingerprint();
		$receipt['password_verified'] = true;
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
			|| self::CONTRACT_NAME !== ( isset( $receipt['contract'] ) ? $receipt['contract'] : '' )
			|| self::CONTRACT_VERSION !== ( isset( $receipt['contract_version'] ) ? $receipt['contract_version'] : '' )
			|| empty( $receipt['password_verified'] )
			|| self::seconds_until( isset( $receipt['expires_at'] ) ? $receipt['expires_at'] : '' ) < 1 ) {
			return false;
		}
		if ( ! hash_equals( (string) ( isset( $receipt['session_binding'] ) ? $receipt['session_binding'] : '' ), self::session_binding( $token ) )
			|| ! hash_equals( (string) ( isset( $receipt['fingerprint'] ) ? $receipt['fingerprint'] : '' ), SA_Security::client_fingerprint() )
			|| ! hash_equals( self::scope_hash( $scope ), (string) ( isset( $receipt['scope_hash'] ) ? $receipt['scope_hash'] : '' ) ) ) {
			return false;
		}
		return self::valid_uuid( isset( $receipt['subject_uuid'] ) ? $receipt['subject_uuid'] : '' )
			&& 'valid' === ( isset( $receipt['result'] ) ? $receipt['result'] : '' )
			&& 'professional_verification_review' === ( isset( $receipt['purpose'] ) ? $receipt['purpose'] : '' )
			&& 'aal2' === ( isset( $receipt['assurance_level'] ) ? $receipt['assurance_level'] : '' );
	}

	private static function from_authentication_assurance( $assurance, $scope, $trace, $password_verified ) {
		$result = self::empty_assertion( $scope, $trace );
		if ( ! is_array( $assurance )
			|| 'sa.cf01.authentication-assurance' !== ( isset( $assurance['contract'] ) ? $assurance['contract'] : '' )
			|| '1.0.0' !== ( isset( $assurance['contract_version'] ) ? $assurance['contract_version'] : '' ) ) {
			$result['reason_code'] = 'authentication_contract_invalid';
			return $result;
		}
		$auth_result = isset( $assurance['result'] ) ? sanitize_key( $assurance['result'] ) : 'unknown';
		if ( ! in_array( $auth_result, array( 'valid', 'invalid', 'unknown' ), true ) ) {
			$result['reason_code'] = 'authentication_result_invalid';
			return $result;
		}
		$result['result'] = $auth_result;
		$result['reason_code'] = 'valid' === $auth_result
			? 'professional_reauthentication_valid'
			: 'authentication_' . sanitize_key( isset( $assurance['reason_code'] ) ? $assurance['reason_code'] : 'unknown' );
		$result['subject_uuid']       = (string) ( isset( $assurance['subject_uuid'] ) ? $assurance['subject_uuid'] : '' );
		$result['scope_hash']         = (string) ( isset( $assurance['scope_hash'] ) ? $assurance['scope_hash'] : '' );
		$result['method']             = sanitize_key( isset( $assurance['method'] ) ? $assurance['method'] : '' );
		$result['assurance_level']    = sanitize_key( isset( $assurance['assurance_level'] ) ? $assurance['assurance_level'] : '' );
		$result['verified_at']        = (string) ( isset( $assurance['verified_at'] ) ? $assurance['verified_at'] : '' );
		$result['expires_at']         = (string) ( isset( $assurance['expires_at'] ) ? $assurance['expires_at'] : '' );
		$result['trace_id']           = (string) ( isset( $assurance['trace_id'] ) ? $assurance['trace_id'] : $trace );
		$result['password_verified']  = (bool) $password_verified;
		if ( 'valid' === $auth_result ) {
			$verified = strtotime( $result['verified_at'] );
			$expires  = strtotime( $result['expires_at'] );
			if ( ! self::valid_uuid( $result['subject_uuid'] )
				|| ! hash_equals( self::scope_hash( $scope ), $result['scope_hash'] )
				|| ! in_array( $result['method'], array( 'totp', 'recovery_code' ), true )
				|| 'aal2' !== $result['assurance_level']
				|| false === $verified
				|| false === $expires
				|| $verified > time() + 60
				|| $expires <= time() ) {
				$result['result'] = 'unknown';
				$result['reason_code'] = 'authentication_evidence_invalid';
			}
		}
		return $result;
	}

	private static function public_receipt( $receipt ) {
		unset( $receipt['session_binding'], $receipt['fingerprint'] );
		return $receipt;
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
			'producer_version'       => defined( 'SA_VERSION' ) ? SA_VERSION : '',
			'purpose'                => 'professional_verification_review',
			'authentication_purpose' => self::AUTH_PURPOSE,
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

	private static function trace_id( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return self::valid_uuid( $value ) ? $value : strtolower( wp_generate_uuid4() );
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
