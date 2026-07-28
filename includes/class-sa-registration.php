<?php

defined( 'ABSPATH' ) || exit;

final class SA_Registration {
	public function hooks() {
		add_action( 'admin_post_nopriv_sa_login', array( $this, 'login' ) );
		add_action( 'admin_post_nopriv_sa_register', array( $this, 'register' ) );
		add_action( 'admin_post_nopriv_sa_forgot_password', array( $this, 'forgot_password' ) );
		add_action( 'admin_post_sa_logout', array( $this, 'logout' ) );
	}

	public function login() {
		check_admin_referer( 'sa_login', 'sa_nonce' );
		if ( SA_Security::rate_limited( 'login', 8, 900 ) ) {
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'Too many attempts. Please wait and try again.' ) );
			exit;
		}

		$login    = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
		$remember = ! empty( $_POST['rememberme'] );
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );

		$user = wp_signon( array( 'user_login' => $login, 'user_password' => $password, 'remember' => $remember ), is_ssl() );
		if ( is_wp_error( $user ) ) {
			wp_safe_redirect( SA_Security::message_url( 'login', 'error', 'The login details could not be verified.' ) );
			exit;
		}

		if ( ! get_user_meta( $user->ID, '_sa_profile_complete', true ) ) {
			$pages = (array) get_option( 'sa_page_map', array() );
			$redirect = isset( $pages['complete'] ) ? get_permalink( absint( $pages['complete'] ) ) : $redirect;
		}
		wp_safe_redirect( SA_Security::safe_redirect( $redirect ) );
		exit;
	}

	public function register() {
		check_admin_referer( 'sa_register', 'sa_nonce' );
		if ( '1' !== get_option( 'sa_allow_registration', '1' ) ) {
			wp_safe_redirect( SA_Security::message_url( 'signup', 'error', 'New registration is temporarily closed.' ) );
			exit;
		}
		if ( ! empty( $_POST['website'] ) || SA_Security::rate_limited( 'register', 4, 1800 ) ) {
			wp_safe_redirect( SA_Security::message_url( 'signup', 'error', 'The registration request could not be accepted.' ) );
			exit;
		}

		$name     = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$country  = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$city     = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$type     = isset( $_POST['account_type'] ) ? sanitize_key( $_POST['account_type'] ) : 'member';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$confirm  = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : '';

		if ( '' === $name || ! is_email( $email ) || '' === $phone || '' === $country || '' === $city ) {
			wp_safe_redirect( SA_Security::message_url( 'signup', 'error', 'Please complete all required fields.' ) );
			exit;
		}
		if ( email_exists( $email ) ) {
			wp_safe_redirect( SA_Security::message_url( 'signup', 'error', 'An account may already exist. Please use Log In or Forgot Password.' ) );
			exit;
		}
		if ( strlen( $password ) < 10 || $password !== $confirm ) {
			wp_safe_redirect( SA_Security::message_url( 'signup', 'error', 'Use a matching password of at least 10 characters.' ) );
			exit;
		}
		if ( empty( $_POST['terms'] ) || empty( $_POST['privacy'] ) ) {
			wp_safe_redirect( SA_Security::message_url( 'signup', 'error', 'Terms and privacy consent are required.' ) );
			exit;
		}

		$role_map = array( 'member' => 'sabri_member', 'patient' => 'sabri_patient', 'student' => 'sabri_student', 'doctor' => 'sabri_doctor_pending' );
		$role     = isset( $role_map[ $type ] ) ? $role_map[ $type ] : 'sabri_member';
		$base     = sanitize_user( strstr( $email, '@', true ), true );
		$username = $base ? $base : 'member';
		$counter  = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $counter;
			$counter++;
		}

		$user_id = wp_insert_user( array( 'user_login' => $username, 'user_email' => $email, 'user_pass' => $password, 'display_name' => $name, 'role' => $role ) );
		if ( is_wp_error( $user_id ) ) {
			wp_safe_redirect( SA_Security::message_url( 'signup', 'error', 'The account could not be created.' ) );
			exit;
		}

		update_user_meta( $user_id, '_sa_phone', $phone );
		update_user_meta( $user_id, '_sa_country', $country );
		update_user_meta( $user_id, '_sa_city', $city );
		update_user_meta( $user_id, '_sa_account_type', $type );
		update_user_meta( $user_id, '_sa_profile_complete', '1' );
		update_user_meta( $user_id, '_sa_terms_accepted_at', current_time( 'mysql', true ) );
		update_user_meta( $user_id, '_sa_privacy_accepted_at', current_time( 'mysql', true ) );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		wp_safe_redirect( SA_Security::message_url( 'complete', 'success', 'Your account has been created. You may add more profile information.' ) );
		exit;
	}

	public function forgot_password() {
		check_admin_referer( 'sa_forgot_password', 'sa_nonce' );
		if ( SA_Security::rate_limited( 'forgot', 4, 1800 ) ) {
			wp_safe_redirect( SA_Security::message_url( 'forgot', 'success', 'If the account exists, a reset email will be sent.' ) );
			exit;
		}
		$login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		retrieve_password( $login );
		wp_safe_redirect( SA_Security::message_url( 'forgot', 'success', 'If the account exists, a reset email will be sent.' ) );
		exit;
	}

	public function logout() {
		check_admin_referer( 'sa_logout' );
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/' );
		wp_logout();
		wp_safe_redirect( SA_Security::safe_redirect( $redirect ) );
		exit;
	}
}
