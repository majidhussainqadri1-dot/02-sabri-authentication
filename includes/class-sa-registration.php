<?php

defined( 'ABSPATH' ) || exit;

/**
 * Authentication entry, registration orchestration and password recovery.
 *
 * File 02 owns the surfaces and flow coordination. File 00 remains the sole
 * owner of membership eligibility, identity, guardian, role and verification.
 */
final class SA_Registration {
	public function hooks() {
		add_action( 'admin_post_nopriv_sa_login', array( $this, 'login' ) );
		add_action( 'admin_post_sa_login', array( $this, 'login' ) );
		add_action( 'admin_post_nopriv_sa_register', array( $this, 'register' ) );
		add_action( 'admin_post_sa_register', array( $this, 'register' ) );
		add_action( 'admin_post_nopriv_sa_forgot_password', array( $this, 'forgot_password' ) );
		add_action( 'admin_post_sa_forgot_password', array( $this, 'forgot_password' ) );
		add_action( 'admin_post_nopriv_sa_reset_password', array( $this, 'reset_password' ) );
		add_action( 'admin_post_sa_reset_password', array( $this, 'reset_password' ) );
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

	public function reset_password() {
		$login = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ) ) : '';
		$key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		check_admin_referer( 'sa_reset_password_' . $login, 'sa_nonce' );

		if ( SA_Security::rate_limited( 'reset_password', 8, 1800, strtolower( $login ) ) ) {
			wp_safe_redirect( SA_Security::message_url( 'reset', 'error', 'Too many reset attempts. Request a new password reset email.' ) );
			exit;
		}

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( SA_Security::message_url( 'reset', 'error', 'This reset link is invalid, expired or already used.' ) );
			exit;
		}

		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$confirm  = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : '';
		if ( $password !== $confirm || strlen( $password ) < 12 ) {
			$url = add_query_arg(
				array(
					'key'   => rawurlencode( $key ),
					'login' => rawurlencode( $login ),
				),
				SA_Security::message_url( 'reset', 'error', 'Use matching passwords of at least 12 characters.' )
			);
			wp_safe_redirect( $url );
			exit;
		}

		reset_password( $user, $password );
		if ( class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
		}
		SA_Security::clear_rate_limit( 'reset_password', strtolower( $login ) );
		SAUTH_Event_Outbox::emit(
			'PasswordResetCompleted.v1',
			$user->ID,
			$user->ID,
			array( 'all_sessions_revoked' => true, 'method' => 'email_reset' ),
			'security'
		);
		SA_Membership_Adapter::audit( 'password_reset_completed', $user->ID );
		wp_safe_redirect( SA_Security::message_url( 'login', 'success', 'Your password was changed. Sign in again on this device.' ) );
		exit;
	}

	public function logout() {
		check_admin_referer( 'sa_logout' );
		$user_id  = get_current_user_id();
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/' );
		wp_logout();
		if ( $user_id ) {
			SA_Membership_Adapter::audit( 'authentication_session_logout', $user_id );
		}
		wp_safe_redirect( SA_Security::safe_redirect( $redirect ) );
		exit;
	}
}
