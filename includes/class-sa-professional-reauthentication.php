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
		return self::from_authentication_assurance( $assurance, $scope, $trace, true );
	}

	/**
	 * Return existing session-bound reauthentication evidence.
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
		if ( ! $user_id || '' === $scope ) {
			$result['reason_code'] = 'subject_or_scope_unavailable';
			return $result;
		}
		$assurance = SA_Authentication_Assurance::assertion( $user_id, self::AUTH_PURPOSE, $scope );
		return self::from_authentication_assurance( $assurance, $scope, $trace, false );
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
		$result['authentication_purpose'] = self::AUTH_PURPOSE;

		if ( 'valid' === $auth_result ) {
			$verified = strtotime( $result['verified_at'] );
			$expires  = strtotime( $result['expires_at'] );
			if ( ! self::valid_uuid( $result['subject_uuid'] )
				|| 1 !== preg_match( '/^[a-f0-9]{64}$/i', $result['scope_hash'] )
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

		if ( 'valid' === $result['result'] ) {
			do_action( 'sa_professional_reauthentication_verified', $result );
		}
		return $result;
	}

	private static function empty_assertion( $scope, $trace ) {
		return array(
			'contract'               => self::CONTRACT_NAME,
			'contract_version'       => self::CONTRACT_VERSION,
			'producer_version'       => defined( 'SA_VERSION' ) ? SA_VERSION : '',
			'purpose'                => 'professional_verification_review',
			'authentication_purpose' => self::AUTH_PURPOSE,
			'subject_uuid'           => '',
			'scope_hash'             => '',
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
