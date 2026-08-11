<?php

defined( 'ABSPATH' ) || exit;

/**
 * File 02 -> File 00 authentication-assurance bridge for password sessions.
 *
 * File 00 1.2.42 retired its own MFA ceremony and consumes a fresh, versioned
 * File 02 claim through `smc_file02_authentication_assurance_v1`. Passkey
 * authentication is projected separately by SAUTH_Passkeys at a higher filter
 * priority and can upgrade this password claim from level 2 to level 3.
 *
 * This bridge never treats an existing WordPress session as fresh evidence.
 * A receipt is created only while WordPress is setting the logged-in cookie for
 * a password-authenticated request, then bound to that exact session token and
 * privacy-minimized client fingerprint for at most five minutes.
 */
final class SAUTH_File00_Authentication_Assurance {
	const CONTRACT_VERSION = '1.0.0';
	const RECEIPT_TTL       = 300;
	const LEVEL             = 2;

	public static function init() {
		add_action( 'set_logged_in_cookie', array( __CLASS__, 'capture_password_session' ), 15, 6 );
		add_action( 'clear_auth_cookie', array( __CLASS__, 'clear_current_session' ), 1 );
		add_filter( 'smc_file02_authentication_assurance_v1', array( __CLASS__, 'file00_assurance' ), 10, 2 );
	}

	/**
	 * Capture only a successful password-authenticated cookie issuance.
	 *
	 * File 02's custom password handler reaches wp_set_auth_cookie() only after
	 * wp_check_password() succeeds. WordPress' core wp-login.php password flow
	 * reaches set_logged_in_cookie only after core authentication succeeds.
	 */
	public static function capture_password_session( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		unset( $cookie, $expire, $expiration );
		$user_id = absint( $user_id );
		$token   = (string) $token;
		if ( ! $user_id || 'logged_in' !== (string) $scheme || '' === $token || ! get_userdata( $user_id ) ) {
			return;
		}
		$source = self::password_request_source();
		if ( '' === $source ) {
			return;
		}

		$now = time();
		$receipt = array(
			'contract_version' => self::CONTRACT_VERSION,
			'owner'            => 'file02',
			'user_id'          => $user_id,
			'level'            => self::LEVEL,
			'method'           => 'password',
			'passkey_asserted' => false,
			'hardware_backed'  => false,
			'verified_at'      => $now,
			'expires_at'       => $now + self::RECEIPT_TTL,
			'fingerprint'      => SA_Security::client_fingerprint(),
			'session_binding'  => self::session_binding( $token ),
			'source'           => $source,
		);
		$key = self::receipt_key( $user_id, $token );
		set_transient( $key, $receipt, self::RECEIPT_TTL );
		$stored = get_transient( $key );
		if ( ! is_array( $stored ) || $stored !== $receipt ) {
			delete_transient( $key );
			return;
		}

		if ( class_exists( 'SA_Membership_Adapter' ) && is_callable( array( 'SA_Membership_Adapter', 'audit' ) ) ) {
			SA_Membership_Adapter::audit(
				'file02_password_assurance_issued',
				$user_id,
				array( 'contract_version' => self::CONTRACT_VERSION, 'level' => self::LEVEL, 'source' => $source )
			);
		}
	}

	/**
	 * Provide the exact File 00 Advanced Trust projection contract.
	 */
	public static function file00_assurance( $baseline, $user_id ) {
		$baseline = is_array( $baseline ) ? $baseline : array();
		$user_id  = absint( $user_id );
		if ( ! $user_id || get_current_user_id() !== $user_id ) {
			return $baseline;
		}
		$token = (string) wp_get_session_token();
		if ( '' === $token ) {
			return $baseline;
		}
		$receipt = get_transient( self::receipt_key( $user_id, $token ) );
		if ( ! self::valid_receipt( $receipt, $user_id, $token ) ) {
			return $baseline;
		}

		return array(
			'contract_version' => self::CONTRACT_VERSION,
			'owner'            => 'file02',
			'level'            => self::LEVEL,
			'method'           => 'password',
			'passkey_asserted' => false,
			'hardware_backed'  => false,
			'verified_at'      => absint( $receipt['verified_at'] ),
		);
	}

	public static function clear_current_session() {
		$user_id = get_current_user_id();
		$token   = (string) wp_get_session_token();
		if ( ! $user_id || '' === $token ) {
			return;
		}
		delete_transient( self::receipt_key( $user_id, $token ) );
	}

	private static function valid_receipt( $receipt, $user_id, $token ) {
		if ( ! is_array( $receipt )
			|| self::CONTRACT_VERSION !== (string) ( $receipt['contract_version'] ?? '' )
			|| 'file02' !== (string) ( $receipt['owner'] ?? '' )
			|| 'password' !== (string) ( $receipt['method'] ?? '' )
			|| self::LEVEL !== absint( $receipt['level'] ?? 0 )
			|| absint( $receipt['user_id'] ?? 0 ) !== absint( $user_id )
			|| ! empty( $receipt['passkey_asserted'] )
			|| ! empty( $receipt['hardware_backed'] ) ) {
			return false;
		}
		$now         = time();
		$verified_at = absint( $receipt['verified_at'] ?? 0 );
		$expires_at  = absint( $receipt['expires_at'] ?? 0 );
		if ( ! $verified_at || $verified_at < $now - self::RECEIPT_TTL || $verified_at > $now + 60 || $expires_at <= $now || $expires_at > $verified_at + self::RECEIPT_TTL ) {
			return false;
		}
		return hash_equals( (string) ( $receipt['fingerprint'] ?? '' ), SA_Security::client_fingerprint() )
			&& hash_equals( (string) ( $receipt['session_binding'] ?? '' ), self::session_binding( $token ) );
	}

	private static function password_request_source() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $action, array( 'sa_login', 'sauth_login', 'sauth_login_risk_verify' ), true ) ) {
			return 'file02_password';
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		$core = is_string( $path ) && 'wp-login.php' === basename( $path );
		if ( $core && isset( $_POST['log'], $_POST['pwd'] ) && ( '' === $action || 'login' === $action ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return 'wordpress_password';
		}
		return '';
	}

	private static function receipt_key( $user_id, $token ) {
		$binding = self::session_binding( $token );
		return 'sauth_f00_auth_' . substr( hash_hmac( 'sha256', absint( $user_id ) . '|' . $binding, wp_salt( 'auth' ) ), 0, 48 );
	}

	private static function session_binding( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'logged_in' ) );
	}
}
