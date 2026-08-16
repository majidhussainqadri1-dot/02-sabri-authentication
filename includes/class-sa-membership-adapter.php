<?php

defined( 'ABSPATH' ) || exit;

/**
 * Controlled integration boundary with File 00: Sabri Membership Core.
 *
 * File 02 must never create platform roles, approve identities, alter
 * institutional status, read File 00 private metadata, or maintain a parallel
 * member profile. Authentication factors are owned by File 02.
 */
final class SA_Membership_Adapter {
	const PLUGIN_BASENAME = 'sabri-membership-core/sabri-membership-core.php';
	const MIN_VERSION     = '1.2.43';
	const CF01_VERSION    = '1.1.0';

	/* Canonical File 00 managed-page contract. These keys are the exact keys
	 * stored in smc_page_map by Sabri Membership Core; File 02 must not invent
	 * parallel sabri-* membership routes. */
	const MEMBERSHIP_APPLICATION_KEY  = 'application';
	const MEMBERSHIP_APPLICATION_PATH = '/membership-application/';
	const MEMBERSHIP_SECURITY_KEY     = 'security';
	const MEMBERSHIP_SECURITY_PATH    = '/membership-security/';
	const MEMBERSHIP_STATUS_KEY       = 'status';
	const MEMBERSHIP_STATUS_PATH      = '/membership-status/';

	public static function plugin_active() {
		return self::available();
	}

	public static function available() {
		try {
			if ( ! defined( 'SMC_VERSION' )
				|| version_compare( (string) SMC_VERSION, self::MIN_VERSION, '<' )
				|| ! defined( 'SMC_DB_VERSION' )
				|| (string) SMC_DB_VERSION !== (string) get_option( 'smc_db_version', '' )
				|| ! function_exists( 'smc_page_url' )
				|| ! function_exists( 'smc_user_status' )
				|| ! class_exists( 'SMC_Security' )
				|| ! class_exists( 'SMC_Completion' )
				|| ! is_callable( array( 'SMC_Completion', 'safe_mode' ) )
				|| SMC_Completion::safe_mode()
				|| ! class_exists( 'SMC_CF01_Contract' )
				|| ! defined( 'SMC_CF01_CONTRACT_VERSION' )
				|| version_compare( (string) SMC_CF01_CONTRACT_VERSION, self::CF01_VERSION, '<' )
				|| ! is_callable( array( 'SMC_CF01_Contract', 'membership_assertion' ) ) ) {
				return false;
			}
			return true;
		} catch ( Throwable $error ) {
			return false;
		}
	}

	public static function login_url( $redirect = '' ) {
		$url = SA_Security::page_url( 'login', wp_login_url() );
		return $redirect ? add_query_arg( 'redirect_to', SA_Security::safe_redirect( $redirect ), $url ) : $url;
	}

	public static function register_url() {
		return SA_Security::page_url( 'signup', wp_registration_url() );
	}

	/** File 00 owns membership/profile completion through its application page. */
	public static function profile_url() {
		return self::safe_page_url( self::MEMBERSHIP_APPLICATION_KEY, self::MEMBERSHIP_APPLICATION_PATH, admin_url( 'profile.php' ) );
	}

	/** File 00 membership-security route; File 24 remains the platform security-center owner. */
	public static function security_url() {
		return self::safe_page_url( self::MEMBERSHIP_SECURITY_KEY, self::MEMBERSHIP_SECURITY_PATH, admin_url( 'profile.php' ) );
	}

	/** File 00 membership/verification-status route. */
	public static function verification_url() {
		return self::safe_page_url( self::MEMBERSHIP_STATUS_KEY, self::MEMBERSHIP_STATUS_PATH, self::profile_url() );
	}

	private static function safe_page_url( $key, $path, $fallback ) {
		if ( ! self::available() ) {
			return $fallback;
		}
		try {
			$url = smc_page_url( (string) $key, (string) $path );
			return is_string( $url ) && '' !== $url ? $url : $fallback;
		} catch ( Throwable $error ) {
			return $fallback;
		}
	}

	public static function status( $user_id ) {
		if ( ! self::available() ) {
			return '';
		}
		try {
			return (string) smc_user_status( absint( $user_id ) );
		} catch ( Throwable $error ) {
			return '';
		}
	}

	public static function membership_assertion( $user_id, $action = 'clinical_identity_link', $purpose = 'authentication' ) {
		if ( ! self::available() ) {
			return array(
				'contract'         => 'smc.cf01.membership-assurance',
				'contract_version' => '',
				'result'           => 'unknown',
				'reason_code'      => 'provider_unavailable',
			);
		}
		try {
			$assertion = SMC_CF01_Contract::membership_assertion(
				absint( $user_id ),
				array(
					'action'  => sanitize_key( $action ),
					'purpose' => sanitize_key( $purpose ),
				)
			);
		} catch ( Throwable $error ) {
			return array(
				'contract'         => 'smc.cf01.membership-assurance',
				'contract_version' => '',
				'result'           => 'unknown',
				'reason_code'      => 'provider_exception',
			);
		}
		return is_array( $assertion ) ? $assertion : array(
			'contract'         => 'smc.cf01.membership-assurance',
			'contract_version' => '',
			'result'           => 'unknown',
			'reason_code'      => 'provider_contract_invalid',
		);
	}

	public static function approved( $user_id ) {
		$assertion = self::membership_assertion( $user_id );
		return 'allow' === ( $assertion['result'] ?? '' )
			&& ! empty( $assertion['membership']['active'] )
			&& empty( $assertion['membership']['suspended'] );
	}

	/** Historical helper now reflects current hardened File 02 passkey assurance only. */
	public static function two_factor_enabled( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! class_exists( 'SAUTH_Passkey_Runtime' ) || ! is_callable( array( 'SAUTH_Passkey_Runtime', 'current_assurance' ) ) ) {
			return false;
		}
		try {
			$assurance = SAUTH_Passkey_Runtime::current_assurance( $user_id );
			return is_array( $assurance ) && 'file02' === ( $assurance['owner'] ?? '' ) && ! empty( $assurance['passkey_asserted'] ) && (int) ( $assurance['level'] ?? 0 ) >= 3;
		} catch ( Throwable $error ) {
			return false;
		}
	}

	/** Google OIDC eligibility is membership eligibility; link/unlink have their own passkey gate. */
	public static function can_use_google( $user_id ) {
		$user_id = absint( $user_id );
		return $user_id > 0 && self::approved( $user_id );
	}

	/** Compatibility alias for historical callers; current provider is File 02 passkey assurance. */
	public static function verify_second_factor( $user_id, $code, $context = array() ) {
		$result = self::verify_second_factor_result( $user_id, $code, $context );
		return 'valid' === ( $result['result'] ?? '' );
	}

	public static function verify_second_factor_result( $user_id, $code, $context = array() ) {
		if ( ! self::available() || ! class_exists( 'SA_Authentication_Assurance' ) ) {
			return array(
				'contract'         => 'sa.cf01.authentication-assurance',
				'contract_version' => '',
				'result'           => 'unknown',
				'reason_code'      => 'provider_unavailable',
			);
		}
		$context = is_array( $context ) ? $context : array();
		if ( empty( $context['purpose'] ) || empty( $context['scope'] ) ) {
			$context = self::request_second_factor_context( absint( $user_id ) );
		}
		try {
			return SA_Authentication_Assurance::verify_and_record( absint( $user_id ), (string) $code, $context );
		} catch ( Throwable $error ) {
			return array(
				'contract'         => 'sa.cf01.authentication-assurance',
				'contract_version' => '1.0.0',
				'result'           => 'unknown',
				'reason_code'      => 'provider_exception',
			);
		}
	}

	public static function authentication_assertion( $user_id, $purpose, $scope ) {
		if ( ! self::available() || ! class_exists( 'SA_Authentication_Assurance' ) ) {
			return array(
				'contract'         => 'sa.cf01.authentication-assurance',
				'contract_version' => '',
				'result'           => 'unknown',
				'reason_code'      => 'provider_unavailable',
			);
		}
		try {
			return SA_Authentication_Assurance::assertion( absint( $user_id ), sanitize_key( $purpose ), (string) $scope );
		} catch ( Throwable $error ) {
			return array(
				'contract'         => 'sa.cf01.authentication-assurance',
				'contract_version' => '1.0.0',
				'result'           => 'unknown',
				'reason_code'      => 'provider_exception',
			);
		}
	}

	private static function request_second_factor_context( $user_id ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'sa_google_unlink' === $action ) {
			$sub = (string) get_user_meta( $user_id, '_sauth_google_sub', true );
			if ( '' === $sub ) {
				$sub = (string) get_user_meta( $user_id, '_sa_google_sub', true );
			}
			if ( '' === $sub ) {
				return array( 'purpose' => '', 'scope' => '' );
			}
			return array(
				'purpose' => 'authentication_unlink',
				'scope'   => 'google-unlink|' . hash( 'sha256', $sub . '|' . $user_id ),
			);
		}
		return array( 'purpose' => '', 'scope' => '' );
	}

	public static function audit( $action, $user_id, array $details = array() ) {
		if ( self::available() && is_callable( array( 'SMC_Security', 'audit' ) ) ) {
			try {
				return (bool) SMC_Security::audit( sanitize_key( $action ), absint( $user_id ), $details );
			} catch ( Throwable $error ) {
				return false;
			}
		}
		return false;
	}

	public static function legacy_role_count() {
		$count = count_users();
		return isset( $count['avail_roles']['sabri_doctor_pending'] ) ? absint( $count['avail_roles']['sabri_doctor_pending'] ) : 0;
	}
}
