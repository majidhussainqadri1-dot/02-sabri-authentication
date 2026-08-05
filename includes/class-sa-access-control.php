<?php

defined( 'ABSPATH' ) || exit;

final class SA_Access_Control {
	public function privacy_hooks() {
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'template_redirect', array( $this, 'private_headers' ), 0 );
	}

	public function hooks() {
		add_filter( 'option_comment_registration', '__return_true' );
		add_filter( 'login_url', array( $this, 'login_url' ), 10, 3 );
		add_filter( 'register_url', array( $this, 'register_url' ) );
		add_filter( 'logout_url', array( $this, 'logout_url' ), 10, 2 );
		add_action( 'pre_comment_on_post', array( $this, 'require_login_for_comment' ) );
	}

	public function login_url( $login_url, $redirect, $force_reauth ) {
		if ( is_admin() ) {
			return $login_url;
		}
		$url = SA_Security::page_url( 'login', $login_url );
		if ( $redirect ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( SA_Security::safe_redirect( $redirect ) ), $url );
		}
		return $force_reauth ? add_query_arg( 'reauth', '1', $url ) : $url;
	}

	public function register_url( $url ) {
		return SA_Security::page_url( 'signup', $url );
	}

	public function logout_url( $url, $redirect ) {
		$action = wp_nonce_url( admin_url( 'admin-post.php?action=sa_logout' ), 'sa_logout' );
		return $redirect ? add_query_arg( 'redirect_to', rawurlencode( SA_Security::safe_redirect( $redirect ) ), $action ) : $action;
	}

	public function require_login_for_comment() {
		if ( ! is_user_logged_in() ) {
			$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
			wp_safe_redirect( add_query_arg( 'redirect_to', rawurlencode( SA_Security::safe_redirect( $redirect ) ), SA_Security::page_url( 'login', wp_login_url() ) ) );
			exit;
		}
	}

	public function robots( array $robots ) {
		if ( self::is_auth_page() ) {
			$robots['noindex']   = true;
			$robots['nofollow']  = true;
			$robots['noarchive'] = true;
			unset( $robots['index'], $robots['follow'] );
		}
		return $robots;
	}

	public function private_headers() {
		if ( ! self::is_auth_page() ) {
			return;
		}
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true );
			header( 'Pragma: no-cache', true );
			header( 'Referrer-Policy: no-referrer', true );
			header( 'X-Content-Type-Options: nosniff', true );
			header( 'X-Frame-Options: SAMEORIGIN', true );
			header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()', true );
		}
	}

	public static function is_file02_page() {
		if ( ! is_singular( 'page' ) ) {
			return false;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		if ( '1' === (string) get_post_meta( $post->ID, '_sa_private_page', true ) ) {
			return true;
		}
		foreach ( self::shortcodes() as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}
		return false;
	}

	public static function is_auth_page() {
		if ( self::is_file02_page() ) {
			return true;
		}
		if ( ! is_singular( 'page' ) ) {
			return false;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		foreach ( array( 'sabri_register', 'sabri_login', 'sabri_profile', 'sabri_security_center', 'sabri_verification_status' ) as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}
		return false;
	}

	public static function shortcodes() {
		return array(
			'sabri_auth_login',
			'sabri_auth_signup',
			'sabri_auth_verify_email',
			'sabri_auth_complete_profile',
			'sabri_auth_forgot_password',
			'sabri_auth_reset_password',
			'sabri_auth_sessions',
			'sabri_auth_access_required',
			'sabri_auth_google_account',
			'sabri_auth_google_verify',
		);
	}
}
