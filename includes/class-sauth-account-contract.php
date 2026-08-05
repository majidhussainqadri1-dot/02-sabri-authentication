<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02 consumer boundary for File 00 account and identity orchestration.
 *
 * File 02 owns authentication surfaces and orchestration. File 00 remains the
 * sole owner of platform UUID, membership eligibility, roles, guardian state,
 * verification and institutional authority. This class deliberately fails
 * closed when the required provider contract is absent or malformed.
 */
final class SAUTH_Account_Contract {
	const CONTRACT_NAME        = 'sauth.account-orchestration';
	const CONTRACT_VERSION     = '1.0.0';
	const PROVIDER_NAME        = 'smc.authentication-account';
	const PROVIDER_MIN_VERSION = '1.0.0';

	private static $required_provider_methods = array(
		'register_account',
		'mark_email_verified',
		'get_completion_state',
	);

	public static function provider_available() {
		if ( ! class_exists( 'SMC_Authentication_Contract' )
			|| ! defined( 'SMC_AUTHENTICATION_CONTRACT_VERSION' )
			|| ! version_compare( (string) SMC_AUTHENTICATION_CONTRACT_VERSION, self::PROVIDER_MIN_VERSION, '>=' ) ) {
			return false;
		}

		foreach ( self::$required_provider_methods as $method ) {
			if ( ! is_callable( array( 'SMC_Authentication_Contract', $method ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Orchestrate registration without creating a parallel identity authority.
	 *
	 * @param array<string,mixed> $payload Validated File 02 registration input.
	 * @param array<string,mixed> $context Request/audit context.
	 * @return array<string,mixed>
	 */
	public static function register_account( array $payload, array $context = array() ) {
		if ( ! self::provider_available() ) {
			return self::unknown( 'provider_unavailable' );
		}

		$result = SMC_Authentication_Contract::register_account(
			self::registration_payload( $payload ),
			self::context( $context )
		);
		return self::normalize_result( $result, 'registration' );
	}

	/**
	 * Notify File 00 that File 02 has completed a signed email challenge.
	 */
	public static function mark_email_verified( $user_id, $email, array $context = array() ) {
		if ( ! self::provider_available() ) {
			return self::unknown( 'provider_unavailable' );
		}

		$result = SMC_Authentication_Contract::mark_email_verified(
			absint( $user_id ),
			sanitize_email( (string) $email ),
			self::context( $context )
		);
		return self::normalize_result( $result, 'email_verification' );
	}

	/**
	 * Resolve owner-sourced post-authentication completion requirements.
	 */
	public static function completion_state( $user_id, array $context = array() ) {
		if ( ! self::provider_available() ) {
			return array(
				'contract'         => self::CONTRACT_NAME,
				'contract_version' => self::CONTRACT_VERSION,
				'result'           => 'unknown',
				'reason_code'      => 'provider_unavailable',
				'missing_steps'    => array(),
				'next_route'       => '',
			);
		}

		$result = SMC_Authentication_Contract::get_completion_state(
			absint( $user_id ),
			self::context( $context )
		);
		if ( ! is_array( $result )
			|| ! self::valid_provider_header( $result )
			|| ! isset( $result['missing_steps'], $result['next_route'] )
			|| ! is_array( $result['missing_steps'] ) ) {
			return array(
				'contract'         => self::CONTRACT_NAME,
				'contract_version' => self::CONTRACT_VERSION,
				'result'           => 'unknown',
				'reason_code'      => 'provider_contract_invalid',
				'missing_steps'    => array(),
				'next_route'       => '',
			);
		}

		return array(
			'contract'         => self::CONTRACT_NAME,
			'contract_version' => self::CONTRACT_VERSION,
			'provider_contract'=> (string) $result['contract'],
			'provider_version' => (string) $result['contract_version'],
			'result'           => (string) $result['result'],
			'reason_code'      => sanitize_key( (string) $result['reason_code'] ),
			'missing_steps'    => array_values( array_unique( array_filter( array_map( 'sanitize_key', $result['missing_steps'] ) ) ) ),
			'next_route'       => SA_Security::safe_redirect( (string) $result['next_route'], '' ),
		);
	}

	private static function registration_payload( array $payload ) {
		return array(
			'name'       => sanitize_text_field( (string) ( $payload['name'] ?? '' ) ),
			'email'      => sanitize_email( (string) ( $payload['email'] ?? '' ) ),
			'phone'      => sanitize_text_field( (string) ( $payload['phone'] ?? '' ) ),
			'password'   => (string) ( $payload['password'] ?? '' ),
			'sex'        => sanitize_key( (string) ( $payload['sex'] ?? '' ) ),
			'date_of_birth' => sanitize_text_field( (string) ( $payload['date_of_birth'] ?? '' ) ),
			'address'    => sanitize_textarea_field( (string) ( $payload['address'] ?? '' ) ),
			'country'    => sanitize_text_field( (string) ( $payload['country'] ?? '' ) ),
			'identity_reference' => sanitize_text_field( (string) ( $payload['identity_reference'] ?? '' ) ),
			'guardian_reference' => sanitize_text_field( (string) ( $payload['guardian_reference'] ?? '' ) ),
			'terms_version'       => sanitize_text_field( (string) ( $payload['terms_version'] ?? '' ) ),
			'privacy_version'     => sanitize_text_field( (string) ( $payload['privacy_version'] ?? '' ) ),
		);
	}

	private static function context( array $context ) {
		return array(
			'purpose'     => sanitize_key( (string) ( $context['purpose'] ?? 'authentication' ) ),
			'trace_id'    => self::trace_id( $context['trace_id'] ?? '' ),
			'idempotency_key' => sanitize_text_field( (string) ( $context['idempotency_key'] ?? '' ) ),
			'client_fingerprint' => SA_Security::client_fingerprint(),
		);
	}

	private static function normalize_result( $result, $operation ) {
		if ( ! is_array( $result ) || ! self::valid_provider_header( $result ) ) {
			return self::unknown( 'provider_contract_invalid' );
		}

		$output = array(
			'contract'          => self::CONTRACT_NAME,
			'contract_version'  => self::CONTRACT_VERSION,
			'provider_contract' => (string) $result['contract'],
			'provider_version'  => (string) $result['contract_version'],
			'operation'         => sanitize_key( (string) $operation ),
			'result'            => (string) $result['result'],
			'reason_code'       => sanitize_key( (string) $result['reason_code'] ),
		);
		if ( isset( $result['user_id'] ) ) {
			$output['user_id'] = absint( $result['user_id'] );
		}
		if ( isset( $result['subject_uuid'] ) ) {
			$output['subject_uuid'] = sanitize_text_field( (string) $result['subject_uuid'] );
		}
		return $output;
	}

	private static function valid_provider_header( array $result ) {
		return isset( $result['contract'], $result['contract_version'], $result['result'], $result['reason_code'] )
			&& self::PROVIDER_NAME === (string) $result['contract']
			&& version_compare( (string) $result['contract_version'], self::PROVIDER_MIN_VERSION, '>=' )
			&& in_array( (string) $result['result'], array( 'allow', 'deny', 'unknown' ), true );
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
		return function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
	}
}
