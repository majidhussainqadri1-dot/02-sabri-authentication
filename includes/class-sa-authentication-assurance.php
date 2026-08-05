<?php

defined( 'ABSPATH' ) || exit;

/**
 * Session-, purpose- and scope-bound authentication assurance.
 *
 * File 00 performs canonical second-factor verification. File 02 stores only a
 * short-lived receipt bound to the current browser fingerprint and WordPress
 * session. A login-risk receipt uses the distinct local purpose
 * `authentication_sign_in`; it can never be consumed as clinical assurance.
 */
final class SA_Authentication_Assurance {
	const CONTRACT_NAME     = 'sa.cf01.authentication-assurance';
	const CONTRACT_VERSION  = '1.0.0';
	const PROVIDER_VERSION  = '1.0.0';
	const PENDING_INDEX_TTL = 900;

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
		add_action( 'set_logged_in_cookie', array( __CLASS__, 'promote_pending_for_cookie' ), 20, 6 );
		add_action( 'clear_auth_cookie', array( __CLASS__, 'clear_current_session' ), 1 );
	}

	public static function provider_available() {
		return class_exists( 'SMC_CF01_Contract' )
			&& defined( 'SMC_CF01_CONTRACT_VERSION' )
			&& version_compare( (string) SMC_CF01_CONTRACT_VERSION, self::PROVIDER_VERSION, '>=' )
			&& is_callable( array( 'SMC_CF01_Contract', 'verify_step_up' ) )
			&& is_callable( array( 'SMC_CF01_Contract', 'membership_assertion' ) );
	}

	/**
	 * Verify a File 00-owned second factor and record a bounded local receipt.
	 *
	 * @return array<string,mixed>
	 */
	public static function verify_and_record( $user_id, $code, array $context = array() ) {
		$user_id = absint( $user_id );
		$purpose = sanitize_key( (string) ( $context['purpose'] ?? '' ) );
		$scope   = trim( (string) ( $context['scope'] ?? '' ) );
		$trace   = self::trace_id( $context['trace_id'] ?? '' );
		$result  = self::empty_assertion( $purpose, $scope, $trace );

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'subject_invalid';
			return $result;
		}
		if ( ! in_array( $purpose, self::$purposes, true ) || '' === $scope ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'purpose_or_scope_invalid';
			return $result;
		}
		$code = trim( (string) $code );
		if ( '' === $code || strlen( $code ) > 128 ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'second_factor_invalid';
			return $result;
		}
		if ( ! self::provider_available() ) {
			$result['reason_code'] = 'provider_unavailable';
			return $result;
		}

		$token = self::current_subject_session_token( $user_id );
		$pre_session_allowed = in_array( $purpose, array( 'authentication_sign_in', 'clinical_sign_in' ), true );
		if ( '' === $token && ! $pre_session_allowed ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'authenticated_session_required';
			return $result;
		}

		$provider = SMC_CF01_Contract::verify_step_up(
			$user_id,
			$code,
			array(
				'purpose'  => self::provider_purpose( $purpose ),
				'scope'    => $scope,
				'trace_id' => $trace,
			)
		);
		$code = '';
		if ( ! self::valid_provider_result( $provider ) ) {
			$result['reason_code'] = 'provider_contract_invalid';
			return $result;
		}
		if ( 'unknown' === $provider['result'] ) {
			$result['reason_code'] = sanitize_key( (string) $provider['reason_code'] );
			return $result;
		}
		if ( 'deny' === $provider['result'] ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = sanitize_key( (string) $provider['reason_code'] );
			return $result;
		}
		if ( ! self::subject_matches( $user_id, $provider['subject_uuid'], $purpose ) ) {
			$result['reason_code'] = 'provider_subject_mismatch';
			return $result;
		}

		$ttl        = self::ttl_for_purpose( $purpose );
		$scope_hash = self::scope_hash( $scope );
		$receipt    = array(
			'contract'         => self::CONTRACT_NAME,
			'contract_version' => self::CONTRACT_VERSION,
			'provider_version' => (string) $provider['contract_version'],
			'subject_uuid'     => (string) $provider['subject_uuid'],
			'purpose'          => $purpose,
			'scope_hash'       => $scope_hash,
			'method'           => (string) $provider['method'],
			'assurance_level'  => 'aal2',
			'verified_at'      => (string) $provider['verified_at'],
			'expires_at'       => gmdate( 'c', time() + $ttl ),
			'trace_id'         => $trace,
			'fingerprint'      => SA_Security::client_fingerprint(),
			'session_binding'  => '',
		);

		if ( '' === $token ) {
			$key = self::pending_receipt_key( $user_id, $purpose, $scope_hash );
			if ( ! self::write_transient_verified( $key, $receipt, $ttl ) || ! self::index_pending( $user_id, $key ) ) {
				$result['reason_code'] = 'assurance_store_failed';
				return $result;
			}
			$result = self::public_receipt( $receipt, 'valid', 'authentication_verified_pending_session' );
			return $result;
		}

		$receipt['session_binding'] = self::session_binding( $token );
		$key = self::session_receipt_key( $user_id, $token, $purpose, $scope_hash );
		if ( ! self::write_transient_verified( $key, $receipt, $ttl ) || ! self::index_session( $user_id, $token, $key, $ttl ) ) {
			$result['reason_code'] = 'assurance_store_failed';
			return $result;
		}
		return self::public_receipt( $receipt, 'valid', 'authentication_verified' );
	}

	/**
	 * Return a current session-bound assurance projection.
	 *
	 * @return array<string,mixed>
	 */
	public static function assertion( $user_id, $purpose, $scope ) {
		$user_id = absint( $user_id );
		$purpose = sanitize_key( (string) $purpose );
		$scope   = trim( (string) $scope );
		$result  = self::empty_assertion( $purpose, $scope, self::trace_id( '' ) );
		if ( ! $user_id || ! in_array( $purpose, self::$purposes, true ) || '' === $scope ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'purpose_or_scope_invalid';
			return $result;
		}
		$token = self::current_subject_session_token( $user_id );
		if ( '' === $token ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'authenticated_session_required';
			return $result;
		}
		$scope_hash = self::scope_hash( $scope );
		$receipt    = get_transient( self::session_receipt_key( $user_id, $token, $purpose, $scope_hash ) );
		if ( ! self::valid_receipt( $receipt, $user_id, $token, $purpose, $scope_hash ) ) {
			$result['result']      = 'invalid';
			$result['reason_code'] = 'assurance_missing_expired_or_mismatched';
			return $result;
		}
		return self::public_receipt( $receipt, 'valid', 'authentication_assurance_valid' );
	}

	/**
	 * Promote pre-login receipts only after WordPress creates the exact session.
	 */
	public static function promote_pending_for_cookie( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		$user_id = absint( $user_id );
		$token   = (string) $token;
		if ( ! $user_id || 'logged_in' !== $scheme || '' === $token ) {
			return;
		}
		$index_key = self::pending_index_key( $user_id );
		$keys      = get_transient( $index_key );
		if ( ! is_array( $keys ) ) {
			return;
		}
		foreach ( array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) ) as $pending_key ) {
			$receipt = get_transient( $pending_key );
			if ( ! is_array( $receipt ) || self::expired( $receipt['expires_at'] ?? '' ) ) {
				delete_transient( $pending_key );
				continue;
			}
			if ( ! in_array( $receipt['purpose'] ?? '', array( 'authentication_sign_in', 'clinical_sign_in' ), true )
				|| ! hash_equals( (string) ( $receipt['fingerprint'] ?? '' ), SA_Security::client_fingerprint() ) ) {
				delete_transient( $pending_key );
				continue;
			}
			$receipt['session_binding'] = self::session_binding( $token );
			$ttl = self::seconds_until( $receipt['expires_at'] ?? '' );
			$key = self::session_receipt_key( $user_id, $token, (string) $receipt['purpose'], (string) $receipt['scope_hash'] );
			if ( $ttl > 0 && self::write_transient_verified( $key, $receipt, $ttl ) ) {
				self::index_session( $user_id, $token, $key, $ttl );
			}
			delete_transient( $pending_key );
		}
		delete_transient( $index_key );
	}

	/**
	 * Remove every assurance receipt associated with the current session.
	 */
	public static function clear_current_session() {
		$user_id = get_current_user_id();
		$token   = (string) wp_get_session_token();
		if ( ! $user_id || '' === $token ) {
			return;
		}
		$index_key = self::session_index_key( $user_id, $token );
		$keys      = get_transient( $index_key );
		if ( is_array( $keys ) ) {
			foreach ( array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) ) as $key ) {
				delete_transient( $key );
			}
		}
		delete_transient( $index_key );
	}

	private static function index_pending( $user_id, $key ) {
		$index_key = self::pending_index_key( $user_id );
		$keys      = get_transient( $index_key );
		$keys      = is_array( $keys ) ? $keys : array();
		$keys[]    = (string) $key;
		$keys      = array_slice( array_values( array_unique( $keys ) ), -20 );
		return self::write_transient_verified( $index_key, $keys, self::PENDING_INDEX_TTL );
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
		$membership = SMC_CF01_Contract::membership_assertion(
			$user_id,
			array( 'action' => 'clinical_identity_link', 'purpose' => sanitize_key( $purpose ) )
		);
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
		return in_array( $purpose, array( 'authentication_sign_in', 'authentication_link', 'authentication_unlink' ), true ) ? 'clinical_sign_in' : $purpose;
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

	private static function pending_receipt_key( $user_id, $purpose, $scope_hash ) {
		return 'sa_cf01_pending_' . substr( hash_hmac( 'sha256', absint( $user_id ) . '|' . SA_Security::client_fingerprint() . '|' . $purpose . '|' . $scope_hash, wp_salt( 'nonce' ) ), 0, 40 );
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

	private static function seconds_until( $value ) {
		$timestamp = strtotime( (string) $value );
		return false === $timestamp ? 0 : max( 0, $timestamp - time() );
	}

	private static function trace_id( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return self::valid_uuid( $value ) ? $value : strtolower( wp_generate_uuid4() );
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}
}
