<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02 consumer boundary for File 00 account and identity orchestration.
 */
final class SAUTH_Account_Contract {
	const CONTRACT_NAME        = 'sauth.account-orchestration';
	const CONTRACT_VERSION     = '1.1.0';
	const PROVIDER_NAME        = 'smc.authentication-account';
	const PROVIDER_MIN_VERSION = '1.1.0';

	private static $required_provider_methods = array( 'register_account', 'mark_email_verified', 'get_completion_state' );

	private static function provider_class() {
		if ( class_exists( 'SMC_Authentication_Contract_V11' )
			&& defined( 'SMC_AUTHENTICATION_CONTRACT_V11_VERSION' )
			&& version_compare( (string) SMC_AUTHENTICATION_CONTRACT_V11_VERSION, self::PROVIDER_MIN_VERSION, '>=' ) ) {
			return 'SMC_Authentication_Contract_V11';
		}
		return '';
	}

	public static function provider_available() {
		$provider = self::provider_class();
		if ( '' === $provider ) {
			return false;
		}
		foreach ( self::$required_provider_methods as $method ) {
			if ( ! is_callable( array( $provider, $method ) ) ) {
				return false;
			}
		}
		return true;
	}

	public static function register_account( array $payload, array $context = array() ) {
		$provider = self::provider_class();
		if ( '' === $provider ) {
			return self::unknown( 'provider_unavailable' );
		}
		$result = call_user_func(
			array( $provider, 'register_account' ),
			self::registration_payload( $payload ),
			self::context( $context )
		);
		return self::normalize_result( $result, 'registration' );
	}

	public static function mark_email_verified( $user_id, $email, array $context = array() ) {
		$provider = self::provider_class();
		if ( '' === $provider ) {
			return self::unknown( 'provider_unavailable' );
		}
		$result = call_user_func(
			array( $provider, 'mark_email_verified' ),
			absint( $user_id ),
			sanitize_email( (string) $email ),
			self::context( $context )
		);
		return self::normalize_result( $result, 'email_verification' );
	}

	public static function completion_state( $user_id, array $context = array() ) {
		$provider = self::provider_class();
		if ( '' === $provider ) {
			return self::completion_unknown( 'provider_unavailable' );
		}
		$result = call_user_func(
			array( $provider, 'get_completion_state' ),
			absint( $user_id ),
			self::context( $context )
		);
		if ( ! is_array( $result )
			|| ! self::valid_provider_header( $result )
			|| ! isset( $result['missing_steps'], $result['next_route'] )
			|| ! is_array( $result['missing_steps'] ) ) {
			return self::completion_unknown( 'provider_contract_invalid' );
		}
		$missing = array_values( array_unique( array_filter( array_map( 'sanitize_key', $result['missing_steps'] ) ) ) );
		$route   = wp_validate_redirect( (string) $result['next_route'], '' );
		if ( ! empty( $missing ) && '' === $route ) {
			return self::completion_unknown( 'provider_route_invalid' );
		}
		return array(
			'contract'          => self::CONTRACT_NAME,
			'contract_version'  => self::CONTRACT_VERSION,
			'provider_contract' => (string) $result['contract'],
			'provider_version'  => (string) $result['contract_version'],
			'result'            => (string) $result['result'],
			'reason_code'       => sanitize_key( (string) $result['reason_code'] ),
			'missing_steps'     => $missing,
			'next_route'        => (string) $route,
		);
	}

	private static function registration_payload( array $payload ) {
		return array(
			'name'                     => sanitize_text_field( (string) ( $payload['name'] ?? '' ) ),
			'email'                    => sanitize_email( (string) ( $payload['email'] ?? '' ) ),
			'phone'                    => sanitize_text_field( (string) ( $payload['phone'] ?? '' ) ),
			'password'                 => (string) ( $payload['password'] ?? '' ),
			'password_confirm'         => (string) ( $payload['password_confirm'] ?? '' ),
			'authentication_method'    => sanitize_key( (string) ( $payload['authentication_method'] ?? 'password' ) ),
			'google_subject'           => sanitize_text_field( (string) ( $payload['google_subject'] ?? '' ) ),
			'google_email_verified'    => ! empty( $payload['google_email_verified'] ),
			'google_picture_candidate' => esc_url_raw( (string) ( $payload['google_picture_candidate'] ?? '' ) ),
			'sex'                      => sanitize_key( (string) ( $payload['sex'] ?? '' ) ),
			'date_of_birth'            => sanitize_text_field( (string) ( $payload['date_of_birth'] ?? '' ) ),
			'address'                  => sanitize_textarea_field( (string) ( $payload['address'] ?? '' ) ),
			'city'                     => sanitize_text_field( (string) ( $payload['city'] ?? '' ) ),
			'country'                  => sanitize_text_field( (string) ( $payload['country'] ?? '' ) ),
			'account_type'             => sanitize_key( (string) ( $payload['account_type'] ?? '' ) ),
			'identity_type'            => sanitize_key( (string) ( $payload['identity_type'] ?? '' ) ),
			'identity_reference'       => sanitize_text_field( (string) ( $payload['identity_reference'] ?? '' ) ),
			'guardian_reference'       => sanitize_text_field( (string) ( $payload['guardian_reference'] ?? '' ) ),
			'profile_photo_required'   => ! empty( $payload['profile_photo_required'] ),
			'terms_version'            => sanitize_text_field( (string) ( $payload['terms_version'] ?? '' ) ),
			'privacy_version'          => sanitize_text_field( (string) ( $payload['privacy_version'] ?? '' ) ),
			'ethical_conduct_version'  => sanitize_text_field( (string) ( $payload['ethical_conduct_version'] ?? '' ) ),
		);
	}

	private static function context( array $context ) {
		return array(
			'purpose'            => sanitize_key( (string) ( $context['purpose'] ?? 'authentication' ) ),
			'trace_id'           => self::trace_id( $context['trace_id'] ?? '' ),
			'idempotency_key'    => sanitize_text_field( (string) ( $context['idempotency_key'] ?? '' ) ),
			'client_fingerprint' => SA_Security::client_fingerprint(),
		);
	}

	private static function normalize_result( $result, $operation ) {
		if ( ! is_array( $result ) || ! self::valid_provider_header( $result ) ) {
			return self::unknown( 'provider_contract_invalid' );
		}
		$operation = sanitize_key( (string) $operation );
		if ( 'allow' === (string) $result['result'] ) {
			$user_id = absint( $result['user_id'] ?? 0 );
			if ( $user_id < 1 ) {
				return self::unknown( 'provider_subject_invalid' );
			}
			if ( 'registration' === $operation && ! self::valid_uuid( $result['subject_uuid'] ?? '' ) ) {
				return self::unknown( 'provider_subject_invalid' );
			}
		}
		$output = array(
			'contract'          => self::CONTRACT_NAME,
			'contract_version'  => self::CONTRACT_VERSION,
			'provider_contract' => (string) $result['contract'],
			'provider_version'  => (string) $result['contract_version'],
			'operation'         => $operation,
			'result'            => (string) $result['result'],
			'reason_code'       => sanitize_key( (string) $result['reason_code'] ),
		);
		if ( isset( $result['user_id'] ) ) {
			$output['user_id'] = absint( $result['user_id'] );
		}
		if ( isset( $result['subject_uuid'] ) && self::valid_uuid( $result['subject_uuid'] ) ) {
			$output['subject_uuid'] = strtolower( (string) $result['subject_uuid'] );
		}
		return $output;
	}

	private static function valid_provider_header( array $result ) {
		return isset( $result['contract'], $result['contract_version'], $result['result'], $result['reason_code'] )
			&& self::PROVIDER_NAME === (string) $result['contract']
			&& version_compare( (string) $result['contract_version'], self::PROVIDER_MIN_VERSION, '>=' )
			&& in_array( (string) $result['result'], array( 'allow', 'deny', 'unknown' ), true );
	}

	private static function valid_uuid( $value ) {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $value );
	}

	private static function completion_unknown( $reason ) {
		return array_merge( self::unknown( $reason ), array( 'missing_steps' => array(), 'next_route' => '' ) );
	}

	private static function unknown( $reason ) {
		return array(
			'contract'         => self::CONTRACT_NAME,
			'contract_version' => self::CONTRACT_VERSION,
			'result'           => 'unknown',
			'reason_code'      => sanitize_key( (string) $reason ),
		);
	}

	private static function trace_id( $candidate ) {
		$candidate = strtolower( preg_replace( '/[^a-f0-9-]/i', '', (string) $candidate ) );
		if ( strlen( $candidate ) >= 16 && strlen( $candidate ) <= 64 ) {
			return $candidate;
		}
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( Exception $error ) {
			return substr( hash( 'sha256', uniqid( 'sauth-trace-', true ) . '|' . microtime( true ) ), 0, 32 );
		}
	}
}
