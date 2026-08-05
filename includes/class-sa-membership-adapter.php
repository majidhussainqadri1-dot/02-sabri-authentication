<?php

defined( 'ABSPATH' ) || exit;

/**
 * Controlled integration boundary with File 00: Sabri Membership Core.
 *
 * File 02 must never create platform roles, approve identities, alter
 * institutional status, read File 00 private metadata, or maintain a parallel
 * member profile.
 */
final class SA_Membership_Adapter {
	const PLUGIN_BASENAME = 'sabri-membership-core/sabri-membership-core.php';
	const MIN_VERSION     = '1.2.7';
	const CF01_VERSION    = '1.0.0';

	public static function plugin_active() {
		return self::available();
	}

	public static function available() {
		return defined( 'SMC_VERSION' )
			&& version_compare( (string) SMC_VERSION, self::MIN_VERSION, '>=' )
			&& function_exists( 'smc_page_url' )
			&& function_exists( 'smc_user_status' )
			&& class_exists( 'SMC_Security' )
			&& class_exists( 'SMC_CF01_Contract' )
			&& defined( 'SMC_CF01_CONTRACT_VERSION' )
			&& version_compare( (string) SMC_CF01_CONTRACT_VERSION, self::CF01_VERSION, '>=' )
			&& is_callable( array( 'SMC_CF01_Contract', 'membership_assertion' ) )
			&& is_callable( array( 'SMC_CF01_Contract', 'verify_step_up' ) );
	}

	public static function login_url( $redirect = '' ) {
		$url = SA_Security::page_url( 'login', wp_login_url() );
		return $redirect ? add_query_arg( 'redirect_to', rawurlencode( SA_Security::safe_redirect( $redirect ) ), $url ) : $url;
	}

	public static function register_url() {
		return SA_Security::page_url( 'signup', wp_registration_url() );
	}

	public static function profile_url() {
		return self::available() ? smc_page_url( 'sabri_profile', '/sabri-profile/' ) : admin_url( 'profile.php' );
	}

	public static function security_url() {
		return self::available() ? smc_page_url( 'sabri_security_center', '/sabri-security-center/' ) : admin_url( 'profile.php' );
	}

	public static function verification_url() {
		return self::available() ? smc_page_url( 'sabri_verification_status', '/sabri-verification-status/' ) : self::profile_url();
	}

	public static function status( $user_id ) {
		return self::available() ? (string) smc_user_status( absint( $user_id ) ) : '';
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
		$assertion = SMC_CF01_Contract::membership_assertion(
			absint( $user_id ),
			array(
				'action'  => sanitize_key( $action ),
				'purpose' => sanitize_key( $purpose ),
			)
		);
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

	public static function two_factor_enabled( $user_id ) {
		$assertion = self::membership_assertion( $user_id );
		return in_array( $assertion['result'] ?? '', array( 'allow', 'deny' ), true )
			&& ! empty( $assertion['membership']['two_factor_ready'] );
	}

	public static function can_use_google( $user_id ) {
		$user_id = absint( $user_id );
		return $user_id > 0 && self::approved( $user_id ) && self::two_factor_enabled( $user_id );
	}

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
		return SA_Authentication_Assurance::verify_and_record( absint( $user_id ), (string) $code, $context );
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
		return SA_Authentication_Assurance::assertion( absint( $user_id ), sanitize_key( $purpose ), (string) $scope );
	}

	private static function request_second_factor_context( $user_id ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'sa_google_verify' === $action ) {
			$token = isset( $_REQUEST['challenge'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['challenge'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$data  = $token && class_exists( 'SA_Google_OAuth' ) ? SA_Google_OAuth::challenge( $token ) : array();
			if ( ! $token || ! is_array( $data ) || empty( $data['user_id'] ) || absint( $data['user_id'] ) !== absint( $user_id ) || empty( $data['operation'] ) || ! in_array( $data['operation'], array( 'login', 'link' ), true ) ) {
				return array( 'purpose' => '', 'scope' => '' );
			}
			$operation = (string) $data['operation'];
			return array(
				'purpose' => 'link' === $operation ? 'authentication_link' : 'clinical_sign_in',
				'scope'   => 'google-' . $operation . '|' . hash( 'sha256', $token ),
			);
		}
		if ( 'sa_google_unlink' === $action ) {
			$sub = (string) get_user_meta( $user_id, '_sa_google_sub', true );
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
			return (bool) SMC_Security::audit( sanitize_key( $action ), absint( $user_id ), $details );
		}
		return false;
	}

	public static function legacy_role_count() {
		$count = count_users();
		return isset( $count['avail_roles']['sabri_doctor_pending'] ) ? absint( $count['avail_roles']['sabri_doctor_pending'] ) : 0;
	}
}
