<?php

defined( 'ABSPATH' ) || exit;

/** File 02 private-surface and unmanaged-authentication boundary. */
final class SA_Access_Control {
	public function privacy_hooks() {
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'template_redirect', array( $this, 'private_headers' ), 0 );
	}

	public function hooks() {
		add_filter( 'option_comment_registration', '__return_true' );
		add_filter( 'login_url', array( $this, 'login_url' ), 10, 3 );
		add_filter( 'register_url', array( $this, 'register_url' ) );
		add_filter( 'lostpassword_url', array( $this, 'lostpassword_url' ), 10, 2 );
		add_filter( 'logout_url', array( $this, 'logout_url' ), 10, 2 );
		add_action( 'pre_comment_on_post', array( $this, 'require_login_for_comment' ) );
		add_action( 'login_init', array( $this, 'redirect_core_login_surface' ), 1 );
		add_filter( 'authenticate', array( $this, 'block_unmanaged_password_authentication' ), PHP_INT_MAX, 3 );
		add_filter( 'wp_is_application_passwords_available', '__return_false', PHP_INT_MAX );
		add_filter( 'xmlrpc_enabled', '__return_false', PHP_INT_MAX );
	}

	public function login_url( $login_url, $redirect, $force_reauth ) {
		$url = SA_Security::page_url( 'login', $login_url );
		if ( $redirect ) { $url = add_query_arg( 'redirect_to', SA_Security::safe_redirect( $redirect ), $url ); }
		return $force_reauth ? add_query_arg( 'reauth', '1', $url ) : $url;
	}

	public function register_url( $url ) { return SA_Security::page_url( 'signup', $url ); }

	public function lostpassword_url( $url, $redirect ) {
		$target = SA_Security::page_url( 'forgot', $url );
		return $redirect ? add_query_arg( 'redirect_to', SA_Security::safe_redirect( $redirect ), $target ) : $target;
	}

	public function logout_url( $url, $redirect ) {
		$action = wp_nonce_url( admin_url( 'admin-post.php?action=sa_logout' ), 'sa_logout' );
		return $redirect ? add_query_arg( 'redirect_to', SA_Security::safe_redirect( $redirect ), $action ) : $action;
	}

	/**
	 * File 02 owns password authentication. No plugin/core wp_signon plane may
	 * silently bypass its membership, risk, rate-limit or audit gates.
	 */
	public function block_unmanaged_password_authentication( $user, $username, $password ) {
		if ( '' === (string) $username && '' === (string) $password ) { return $user; }
		return new WP_Error( 'sauth_canonical_login_required', __( 'Use the Sabri Authentication login page for account sign-in.', 'sabri-authentication' ) );
	}

	/** Route wp-login account surfaces into File 02 without affecting postpass/logout. */
	public function redirect_core_login_surface() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $action, array( 'logout', 'postpass', 'confirmaction', 'confirm_admin_email' ), true ) ) { return; }
		if ( in_array( $action, array( 'lostpassword', 'retrievepassword' ), true ) ) {
			wp_safe_redirect( SA_Security::page_url( 'forgot', home_url( '/' ) ) ); exit;
		}
		if ( in_array( $action, array( 'rp', 'resetpass' ), true ) ) {
			$key = isset( $_REQUEST['key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$login = isset( $_REQUEST['login'] ) ? sanitize_user( wp_unslash( $_REQUEST['login'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$url = SA_Security::page_url( 'reset', home_url( '/' ) );
			if ( strlen( $key ) <= 256 && strlen( $login ) <= 128 ) { $url = add_query_arg( array( 'key' => $key, 'login' => $login ), $url ); }
			wp_safe_redirect( $url ); exit;
		}
		if ( 'register' === $action ) { wp_safe_redirect( SA_Security::page_url( 'signup', home_url( '/' ) ) ); exit; }
		$redirect = isset( $_REQUEST['redirect_to'] ) ? SA_Security::safe_redirect( wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$url = SA_Security::page_url( 'login', home_url( '/' ) );
		if ( '' !== $redirect ) { $url = add_query_arg( 'redirect_to', $redirect, $url ); }
		wp_safe_redirect( $url ); exit;
	}

	public function require_login_for_comment() {
		if ( ! is_user_logged_in() ) {
			$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
			wp_safe_redirect( add_query_arg( 'redirect_to', SA_Security::safe_redirect( $redirect ), SA_Security::page_url( 'login', home_url( '/' ) ) ) );
			exit;
		}
	}

	public function robots( array $robots ) {
		if ( self::is_auth_page() ) {
			$robots['noindex'] = true; $robots['nofollow'] = true; $robots['noarchive'] = true;
			unset( $robots['index'], $robots['follow'] );
		}
		return $robots;
	}

	public function private_headers() {
		if ( ! self::is_auth_page() ) { return; }
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true );
			header( 'Pragma: no-cache', true );
			header( 'Referrer-Policy: no-referrer', true );
			header( 'X-Content-Type-Options: nosniff', true );
			header( 'X-Frame-Options: SAMEORIGIN', true );
			header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()', true );
			header( 'Cross-Origin-Opener-Policy: same-origin', true );
		}
	}

	public static function is_file02_page() {
		if ( class_exists( 'SAUTH_Canonical_Routes' )
			&& SAUTH_Canonical_Routes::SESSIONS === (string) get_query_var( SAUTH_Canonical_Routes::QUERY_VAR ) ) {
			return true;
		}
		if ( ! is_singular( 'page' ) ) { return false; }
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) { return false; }
		if ( '1' === (string) get_post_meta( $post->ID, '_sa_private_page', true ) ) { return true; }
		foreach ( self::shortcodes() as $shortcode ) { if ( has_shortcode( $post->post_content, $shortcode ) ) { return true; } }
		return false;
	}

	public static function is_auth_page() {
		if ( self::is_file02_page() ) { return true; }
		if ( ! is_singular( 'page' ) ) { return false; }
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) { return false; }
		foreach ( array( 'sabri_register', 'sabri_login', 'sabri_profile', 'sabri_security_center', 'sabri_verification_status' ) as $shortcode ) { if ( has_shortcode( $post->post_content, $shortcode ) ) { return true; } }
		return false;
	}

	public static function shortcodes() {
		return array( 'sabri_auth_login','sabri_auth_signup','sabri_auth_verify_email','sabri_auth_risk_challenge','sabri_auth_complete_profile','sabri_auth_forgot_password','sabri_auth_reset_password','sabri_auth_sessions','sabri_auth_passkeys','sabri_auth_access_required','sabri_auth_google_account','sabri_auth_google_verify' );
	}
}
