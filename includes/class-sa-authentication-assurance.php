<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02 authentication assurance for CF-01 and other approved consumers.
 *
 * File 00 verifies its own second factor. File 02 binds successful evidence to
 * the current WordPress authentication session, purpose, opaque scope and TTL.
 * Neither service grants clinical object, field or relationship authorization.
 */
final class SA_Authentication_Assurance {
	const CONTRACT_NAME    = 'sa.cf01.authentication-assurance';
	const CONTRACT_VERSION = '1.0.0';
	const PROVIDER_VERSION = '1.0.0';
	const PENDING_TTL      = 300;

	private static $purposes = array(
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
		add_action( 'set_logged_in_cookie', array( __CLASS__, 'promote_pending_for_cookie' ), 20, 6 );
		add_action( 'clear_auth_cookie', array( __CLASS__, 'clear_current_session' ), 5 );
	}

	/**
	 * Ask File 00 to verify its second factor and bind accepted evidence.
	 *
	 * @param int    $user_id Subject WordPress user ID.
	 * @param string $code File 00-owned TOTP or recovery code.
	 * @param array  $context Purpose, opaque scope and trace context.
	 * @return array<string,mixed>
	 */
	public static function verify_and_record( $user_id, $code, $context = array() ) {
		$user_id = absint( $user_id );
		$context = is_array( $context ) ? $context : array();
		$purpose = sanitize_key( $context['purpose'] ?? '' );
		$scope   = trim( (string) ( $context['scope'] ?? '' ) );
		$trace   = self::trace_id( $context['trace_id'] ?? '' );
		$result  = self::empty_assertion( $purpose, $scope, $trace );

		if ( ! self::provider_available() ) {
			$result['reason_code'] = 'provider_unavailable';
			return $result;
		}
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			$result['reason_code'] = 'subject_unavailable';
			return $result;
		}
		if ( ! in_array( $purpose, self::$purposes, true ) || '' === $scope ) {
			$result['reason_code'] = 'unsupported_purpose_or_scope';
			return $result;
		}

		$scope_hash = self::scope_hash( $scope );
		if ( '' === $scope_hash ) {
			$result['reason_code'] = 'scope_hash_unavailable';
			return $result;
		}

		$provider = SMC_CF01_Contract::verify_step_up(
			$user_id,
			(string) $code,
			array(
				'purpose'  => self::provider_purpose( $purpose ),
				'scope'    => $scope,
				'trace_id' => $trace,
			)
		);
		if ( ! self::valid_provider_result( $provider ) ) {
			$result['reason_code'] = 'provider_contract_invalid';
			return $result;
		}
		if ( 'allow' !== $provider['result'] ) {
			$result['result'] = 'deny' === $provider['result'] ? 'invalid' : 'unknown';
			$result['reason_code'] = 'provider_' . sanitize_key( $provider['reason_code'] ?? 'unknown' );
			return $result;
		}

		$receipt = array(
			'contract'            => self::CONTRACT_NAME,
			'contract_version'    => self::CONTRACT_VERSION,
			'provider_contract'   => (string) $provider['contract'],
			'provider_version'    => (string) $provider['contract_version'],
			'producer_version'    => defined( 'SA_VERSION' ) ? SA_VERSION : '',
			'subject_uuid'        => (string) $provider['subject_uuid'],
			'purpose'             => $purpose,
			'scope_hash'          => $scope_hash,
			'provider_scope_hash' => (string) $provider['scope_hash'],
			'method'              => sanitize_key( $provider['method'] ),
			'assurance_level'     => 'aal2',
			'verified_at'         => (string) $provider['verified_at'],
			'expires_at'          => gmdate( 'c', time() + self::ttl_for_purpose( $purpose ) ),
			'trace_id'            => $trace,
			'fingerprint'         => SA_Security::client_fingerprint(),
			'session_binding'     => '',
		);

		$token = self::current_subject_session_token( $user_id );
		if ( '' !== $token ) {
			if ( ! self::store_session_receipt( $user_id, $token, $receipt ) ) {
				$result['result'] = 'invalid';
				$result['reason_code'] = 'session_receipt_store_failed';
				return $result;
			}
			return self::public_receipt( $receipt, 'valid', 'authentication_verified' );
		}

		if ( 'clinical_sign_in' !== $purpose ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'authenticated_session_required';
			return $result;
		}
		if ( ! self::store_pending_receipt( $user_id, $receipt ) ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'pending_receipt_store_failed';
			return $result;
		}
		return self::public_receipt( $receipt, 'valid', 'authentication_verified_pending_session' );
	}

	/**
	 * Return current session-bound assurance for a purpose and opaque scope.
	 *
	 * @param int    $user_id Subject WordPress user ID.
	 * @param string $purpose Approved purpose.
	 * @param string $scope Opaque action/object scope.
	 * @return array<string,mixed>
	 */
	public static function assertion( $user_id, $purpose, $scope ) {
		$user_id = absint( $user_id );
		$purpose = sanitize_key( $purpose );
		$scope   = trim( (string) $scope );
		$result  = self::empty_assertion( $purpose, $scope, self::trace_id( '' ) );

		if ( ! self::provider_available() ) {
			$result['reason_code'] = 'provider_unavailable';
			return $result;
		}
		if ( ! in_array( $purpose, self::$purposes, true ) || '' === $scope ) {
			$result['reason_code'] = 'unsupported_purpose_or_scope';
			return $result;
		}
		$token = self::current_subject_session_token( $user_id );
		if ( '' === $token ) {
			$result['result'] = 'invalid';
			$result['reason_code'] = 'current_session_unavailable';
			return $result;
		}
		$scope_hash = self::scope_hash( $scope );
		$key = self::session_receipt_key( $user_id, $token, $purpose, $scope_hash );
		$receipt = get_transient( $key );
		if ( ! self::valid_receipt( $receipt, $user_id, $token, $purpose, $scope_hash ) ) {
			delete_transient( $key );
			$result['result'] = 'invalid';
			$result['reason_code'] = 'assurance_missing_expired_or_mismatched';
			return $result;
		}
		return self::public_receipt( $receipt, 'valid', 'session_assurance_valid' );
	}

	/**
	 * Promote pre-login assurance when WordPress creates the authenticated token.
	 */
	public static function promote_pending_for_cookie( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		$user_id = absint( $user_id );
		$token   = (string) $token;
		if ( ! $user_id || '' === $token || ! self::provider_available() ) {
			return;
		}
		$index_key = self::pending_index_key( $user_id );
		$keys = get_transient( $index_key );
		$keys = is_array( $keys ) ? array_values( array_unique( array_filter( array_map( 'sanitize_key', $keys ) ) ) ) : array();
		foreach ( $keys as $key ) {
			$receipt = get_transient( $key );
			if ( ! is_array( $receipt )
				|| ! hash_equals( (string) ( $receipt['fingerprint'] ?? '' ), SA_Security::client_fingerprint() )
				|| self::expired( $receipt['expires_at'] ?? '' )
				|| ! self::subject_matches( $user_id, $receipt['subject_uuid'] ?? '', $receipt['purpose'] ?? '' ) ) {
				delete_transient( $key );
				continue;
			}
			self::store_session_receipt( $user_id, $token, $receipt );
			delete_transient( $key );
		}
		delete_transient( $index_key );
	}

	public static function clear_current_session() {
		$token = wp_get_session_token();
		if ( '' === (string) $token ) {
			return;
		}
		$binding = self::session_binding( (string) $token );
		$index_key = 'sa_cf01_session_index_' . substr( $binding, 0, 40 );
		$keys = get_transient( $index_key );
		if ( is_array( $keys ) ) {
			foreach ( $keys as $key ) {
				delete_transient( sanitize_key( $key ) );
			}
		}
		delete_transient( $index_key );
	}

	public static function provider_available() {
		return defined( 'SMC_VERSION' )
			&& version_compare( (string) SMC_VERSION, '1.2.7', '>=' )
			&& class_exists( 'SMC_CF01_Contract' )
			&& defined( 'SMC_CF01_CONTRACT_VERSION' )
			&& version_compare( (string) SMC_CF01_CONTRACT_VERSION, self::PROVIDER_VERSION, '>=' )
			&& is_callable( array( 'SMC_CF01_Contract', 'verify_step_up' ) )
			&& is_callable( array( 'SMC_CF01_Contract', 'membership_assertion' ) );
	}

	private static function store_pending_receipt( $user_id, $receipt ) {
		$ttl = min( self::PENDING_TTL, self::seconds_until( $receipt['expires_at'] ) );
		if ( $ttl < 1 ) {
			return false;
		}
		$key = self::pending_receipt_key( $user_id, $receipt['purpose'], $receipt['scope_hash'] );
		if ( ! self::write_transient_verified( $key, $receipt, $ttl ) ) {
			return false;
		}
		$index_key = self::pending_index_key( $user_id );
		$keys = get_transient( $index_key );
		$keys = is_array( $keys ) ? $keys : array();
		$keys[] = $key;
		$keys = array_slice( array_values( array_unique( $keys ) ), -5 );
		if ( ! self::write_transient_verified( $index_key, $keys, self::PENDING_TTL ) ) {
			delete_transient( $key );
			return false;
		}
		return true;
	}

	private static function store_session_receipt( $user_id, $token, $receipt ) {
		$binding = self::session_binding( $token );
		$receipt['session_binding'] = $binding;
		$key = self::session_receipt_key( $user_id, $token, $receipt['purpose'], $receipt['scope_hash'] );
		$ttl = self::seconds_until( $receipt['expires_at'] );
		if ( $ttl < 1 || ! self::write_transient_verified( $key, $receipt, $ttl ) ) {
			return false;
		}
		$index_key = 'sa_cf01_session_index_' . substr( $binding, 0, 40 );
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

	private static function write_transient_verified( $key, $value, $ttl ) {
		set_transient( $key, $value, max( 1, absint( $ttl ) ) );
		$stored = get_transient( $key );
		return false !== $stored && $stored === $value;
	}

	private static function valid_provider_result( $provider ) {
		if ( ! is_array( $provider ) ) {
			return false;
		}
		$required = array( 'contract', 'contract_version', 'subject_uuid', 'scope_hash', 'method', 'verified_at', 'result', 'reason_code' );
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $provider ) ) {
				return false;
			}
		}
		if ( 'smc.cf01.membership-assurance.step-up' !== $provider['contract']
			|| ! version_compare( (string) $provider['contract_version'], self::PROVIDER_VERSION, '>=' )
			|| ! in_array( $provider['result'], array( 'allow', 'deny', 'unknown' ), true ) ) {
			return false;
		}
		if ( 'allow' !== $provider['result'] ) {
			return true;
		}
		$verified = strtotime( (string) $provider['verified_at'] );
		return self::valid_uuid( $provider['subject_uuid'] )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/i', (string) $provider['scope_hash'] )
			&& in_array( $provider['method'], array( 'totp', 'recovery_code' ), true )
			&& false !== $verified
			&& $verified >= time() - 300
			&& $verified <= time() + 60;
	}

	private static function valid_receipt( $receipt, $user_id, $token, $purpose, $scope_hash ) {
		if ( ! is_array( $receipt ) || self::expired( $receipt['expires_at'] ?? '' ) ) {
			return false;
		}
		if ( self::CONTRACT_NAME !== ( $receipt['contract'] ?? '' )
			|| self::CONTRACT_VERSION !== ( $receipt['contract_version'] ?? '' )
			|| ! version_compare( (string) ( $receipt['provider_version'] ?? '' ), self::PROVIDER_VERSION, '>=' ) ) {
			return false;
		}
		if ( ! hash_equals( (string) ( $receipt['purpose'] ?? '' ), (string) $purpose )
			|| ! hash_equals( (string) ( $receipt['scope_hash'] ?? '' ), (string) $scope_hash )
			|| ! hash_equals( (string) ( $receipt['fingerprint'] ?? '' ), SA_Security::client_fingerprint() ) ) {
			return false;
		}
		if ( ! hash_equals( (string) ( $receipt['session_binding'] ?? '' ), self::session_binding( $token ) ) ) {
			return false;
		}
		return self::subject_matches( $user_id, $receipt['subject_uuid'] ?? '', $purpose );
	}

	private static function subject_matches( $user_id, $subject_uuid, $purpose ) {
		$membership = SMC_CF01_Contract::membership_assertion( $user_id, array( 'action' => 'clinical_identity_link', 'purpose' => sanitize_key( $purpose ) ) );
		return is_array( $membership )
			&& in_array( $membership['result'] ?? '', array( 'allow', 'deny' ), true )
			&& isset( $membership['subject']['platform_uuid'] )
			&& self::valid_uuid( $subject_uuid )
			&& hash_equals( (string) $subject_uuid, (string) $membership['subject']['platform_uuid'] );
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
			'producer_version' => defined( 'SA_VERSION' ) ? SA_VERSION : '',
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
			'producer_version' => defined( 'SA_VERSION' ) ? SA_VERSION : '',
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

	private static function provider_purpose( $purpose ) {
		return in_array( $purpose, array( 'authentication_link', 'authentication_unlink' ), true ) ? 'clinical_sign_in' : $purpose;
	}

	private static function ttl_for_purpose( $purpose ) {
		$map = array(
			'clinical_sign_in'      => 900,
			'authentication_link'   => 300,
			'authentication_unlink' => 300,
			'prescription_sign'     => 300,
			'clinical_export'       => 300,
			'clinical_transfer'     => 300,
			'break_glass'           => 120,
			'guardian_sensitive'    => 300,
			'key_recovery'          => 120,
		);
		return isset( $map[ $purpose ] ) ? (int) $map[ $purpose ] : 120;
	}

	private static function pending_index_key( $user_id ) {
		return 'sa_cf01_pending_index_' . substr( hash_hmac( 'sha256', absint( $user_id ) . '|' . SA_Security::client_fingerprint(), wp_salt( 'nonce' ) ), 0, 40 );
	}

	private static function pending_receipt_key( $user_id, $purpose, $scope_hash ) {
		return 'sa_cf01_pending_' . substr( hash_hmac( 'sha256', absint( $user_id ) . '|' . SA_Security::client_fingerprint() . '|' . $purpose . '|' . $scope_hash, wp_salt( 'nonce' ) ), 0, 40 );
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

	private static function seconds_until( $value ) {
		$timestamp = strtotime( (string) $value );
		return false === $timestamp ? 0 : max( 0, $timestamp - time() );
	}

	private static function trace_id( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( self::valid_uuid( $value ) ) {
			return $value;
		}
		return strtolower( wp_generate_uuid4() );
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
