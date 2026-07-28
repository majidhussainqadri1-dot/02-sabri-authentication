<?php

defined( 'ABSPATH' ) || exit;

/**
 * Controlled integration boundary with File 00: Sabri Membership Core.
 *
 * File 02 must never create platform roles, approve identities, alter
 * institutional status, or maintain a parallel member profile.
 */
final class SA_Membership_Adapter {
	const PLUGIN_BASENAME = 'sabri-membership-core/sabri-membership-core.php';
	const MIN_VERSION     = '1.0.1';

	public static function plugin_active() {
		// Active plugins are loaded before a plugin activation hook runs. Requiring
		// the actual API surface also rejects inactive, too-old, or broken copies.
		return self::available();
	}

	public static function available() {
		return defined( 'SMC_VERSION' )
			&& version_compare( (string) SMC_VERSION, self::MIN_VERSION, '>=' )
			&& function_exists( 'smc_page_url' )
			&& function_exists( 'smc_user_status' )
			&& class_exists( 'SMC_Security' );
	}

	public static function login_url( $redirect = '' ) {
		$url = self::available() ? smc_page_url( 'sabri_login', '/sabri-login/' ) : wp_login_url();
		return $redirect ? add_query_arg( 'redirect_to', SA_Security::safe_redirect( $redirect ), $url ) : $url;
	}

	public static function register_url() {
		return self::available() ? smc_page_url( 'sabri_register', '/sabri-register/' ) : wp_registration_url();
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

	public static function approved( $user_id ) {
		return in_array( self::status( $user_id ), array( 'approved', 'verified' ), true );
	}

	public static function two_factor_enabled( $user_id ) {
		return self::available() && '1' === (string) get_user_meta( absint( $user_id ), '_smc_2fa_enabled', true );
	}

	public static function can_use_google( $user_id ) {
		$user_id = absint( $user_id );
		return $user_id > 0 && self::approved( $user_id ) && self::two_factor_enabled( $user_id );
	}

	public static function verify_second_factor( $user_id, $code ) {
		if ( ! self::available() ) {
			return false;
		}

		$user_id = absint( $user_id );
		$code    = strtoupper( trim( sanitize_text_field( (string) $code ) ) );
		$secret  = (string) get_user_meta( $user_id, '_smc_totp_secret', true );

		if ( $secret && SMC_Security::verify_totp( $secret, $code ) ) {
			return true;
		}

		return (bool) SMC_Security::consume_recovery_code( $user_id, $code );
	}

	public static function audit( $action, $user_id, array $details = array() ) {
		if ( self::available() && is_callable( array( 'SMC_Security', 'audit' ) ) ) {
			SMC_Security::audit( sanitize_key( $action ), absint( $user_id ), 'google_account', 0, $details );
		}
	}

	public static function legacy_role_count() {
		$count = count_users();
		return isset( $count['avail_roles']['sabri_doctor_pending'] ) ? absint( $count['avail_roles']['sabri_doctor_pending'] ) : 0;
	}
}
