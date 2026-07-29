<?php

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility routes and password recovery.
 *
 * Registration and password/TOTP login are owned by File 00.
 */
final class SA_Registration {
	public function hooks() {
		add_action( 'admin_post_nopriv_sa_login', array( $this, 'login' ) );
		add_action( 'admin_post_sa_login', array( $this, 'login' ) );
		add_action( 'admin_post_nopriv_sa_register', array( $this, 'register' ) );
		add_action( 'admin_post_sa_register', array( $this, 'register' ) );
		add_action( 'admin_post_nopriv_sa_forgot_password', array( $this, 'forgot_password' ) );
		add_action( 'admin_post_sa_forgot_password', array( $this, 'forgot_password' ) );
		add_action( 'admin_post_sa_logout', array( $this, 'logout' ) );
	}

	public function login() {
		check_admin_referer( 'sa_login', 'sa_nonce' );
		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : home_url( '/' );
		wp_safe_redirect( SA_Membership_Adapter::login_url( $redirect ) );
		exit;
	}

	public function register() {
		check_admin_referer( 'sa_register', 'sa_nonce' );
		wp_safe_redirect( SA_Membership_Adapter::register_url() );
		exit;
	}

	public function forgot_password() {
		check_admin_referer( 'sa_forgot_password', 'sa_nonce' );
		$login   = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		$subject = strtolower( trim( $login ) );

		$ip_blocked      = SA_Security::rate_limited( 'forgot_password_ip', 12, 1800 );
		$account_blocked = SA_Security::rate_limited( 'forgot_password_account', 4, 1800, $subject );
		if ( ! $ip_blocked && ! $account_blocked && '' !== $login ) {
			retrieve_password( $login );
		}

		wp_safe_redirect( SA_Security::message_url( 'forgot', 'success', 'If the account exists, a reset email will be sent.' ) );
		exit;
	}

	public function logout() {
		check_admin_referer( 'sa_logout' );
		$user_id  = get_current_user_id();
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/' );
		wp_logout();
		if ( $user_id ) {
			SA_Membership_Adapter::audit( 'google_session_logout', $user_id );
		}
		wp_safe_redirect( SA_Security::safe_redirect( $redirect ) );
		exit;
	}
}
