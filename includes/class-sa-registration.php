<?php

defined( 'ABSPATH' ) || exit;

/**
 * Authentication entry, registration orchestration and password recovery.
 *
 * File 02 owns the surfaces and flow coordination. File 00 remains the sole
 * owner of membership eligibility, identity, guardian, role and verification.
 */
final class SA_Registration {
	const MIN_PASSWORD_LENGTH = 12;
	const MIN_MALE_AGE        = 15;
	const MIN_FEMALE_AGE      = 12;

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
		$redirect = isset( $_POST['redirect_to'] ) ? SA_Security::safe_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) ) : home_url( '/' );
		$login    = isset( $_POST['user_login'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$remember = ! empty( $_POST['rememberme'] );
		$trap     = isset( $_POST['website'] ) ? trim( (string) wp_unslash( $_POST['website'] ) ) : '';
		$subject  = strtolower( $login );

		if ( '' !== $trap || '' === $login || '' === $password ) {
			$this->login_failure( 0, $redirect, 'invalid_request' );
		}

		$ip_blocked      = SA_Security::rate_limited( 'password_login_ip', 20, 900 );
		$account_blocked = SA_Security::rate_limited( 'password_login_account', 8, 900, $subject );
		if ( $ip_blocked || $account_blocked ) {
			$this->login_failure( 0, $redirect, 'rate_limited' );
		}

		$user = false !== strpos( $login, '@' ) ? get_user_by( 'email', sanitize_email( $login ) ) : get_user_by( 'login', sanitize_user( $login ) );
		$hash = $user instanceof WP_User ? (string) $user->user_pass : (string) get_option( 'sauth_dummy_password_hash', '' );
		if ( '' === $hash && function_exists( 'wp_hash_password' ) ) {
			$hash = wp_hash_password( SA_Security::random_token( 32 ) );
		}
		$valid = '' !== $hash && function_exists( 'wp_check_password' ) && wp_check_password( $password, $hash, $user instanceof WP_User ? $user->ID : 0 );
		if ( ! $valid || ! $user instanceof WP_User ) {
			$this->login_failure( 0, $redirect, 'credentials_invalid' );
		}

		$completion = SAUTH_Account_Contract::completion_state( $user->ID, array( 'purpose' => 'password_sign_in' ) );
		$assertion  = SA_Membership_Adapter::membership_assertion( $user->ID, 'authentication_sign_in', 'authentication' );
		if ( ! self::sign_in_allowed( $assertion, $completion ) ) {
			$this->login_failure( $user->ID, $redirect, 'membership_not_eligible' );
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );

		SA_Security::clear_rate_limit( 'password_login_account', $subject );
		SAUTH_Event_Outbox::emit(
			'AccountAuthenticationSucceeded.v1',
			$user->ID,
			$user->ID,
			array( 'method' => 'password', 'completion_required' => ! empty( $completion['missing_steps'] ) ),
			'security'
		);
		SA_Membership_Adapter::audit( 'password_authentication_succeeded', $user->ID );

		$destination = $redirect;
		if ( 'allow' === ( $completion['result'] ?? '' ) && ! empty( $completion['missing_steps'] ) && ! empty( $completion['next_route'] ) ) {
			$destination = SA_Security::safe_redirect( $completion['next_route'], SA_Membership_Adapter::profile_url() );
		}
		wp_safe_redirect( $destination );
		exit;
	}

	public function register() {
		check_admin_referer( 'sa_register', 'sa_nonce' );
		$trap = isset( $_POST['website'] ) ? trim( (string) wp_unslash( $_POST['website'] ) ) : '';
		if ( '' !== $trap ) {
			$this->registration_redirect( 'error', 'Registration could not be completed.' );
		}
		if ( ! SAUTH_Account_Contract::provider_available() ) {
			$this->registration_redirect( 'error', 'Account registration is temporarily unavailable. No account was created.' );
		}

		$payload = self::registration_input( $_POST );
		$valid   = self::validate_registration( $payload );
		if ( is_wp_error( $valid ) ) {
			$this->registration_redirect( 'error', $valid->get_error_message() );
		}

		$email_key = hash_hmac( 'sha256', strtolower( $payload['email'] ), wp_salt( 'nonce' ) );
		$blocked   = SA_Security::rate_limited( 'registration_ip', 8, HOUR_IN_SECONDS )
			|| SA_Security::rate_limited( 'registration_email', 3, HOUR_IN_SECONDS, $email_key );
		if ( $blocked ) {
			$this->registration_redirect( 'error', 'Registration is temporarily limited. Please wait and try again.' );
		}

		$result = SAUTH_Account_Contract::register_account(
			$payload,
			array(
				'purpose'         => 'account_registration',
				'idempotency_key' => 'registration-' . substr( $email_key, 0, 24 ) . '-' . gmdate( 'YmdH' ),
			)
		);
		if ( 'allow' !== ( $result['result'] ?? '' ) || empty( $result['user_id'] ) ) {
			SAUTH_Event_Outbox::emit(
				'AccountAuthenticationFailed.v1',
				0,
				0,
				array( 'method' => 'registration', 'reason' => sanitize_key( (string) ( $result['reason_code'] ?? 'provider_rejected' ) ) ),
				'security'
			);
			$this->registration_redirect( 'error', 'Registration could not be completed. The email or identity details may already belong to an account, or the membership service may require review.' );
		}

		$user_id = absint( $result['user_id'] );
		SA_Membership_Adapter::audit( 'account_registration_orchestrated', $user_id, array( 'contract_version' => SA_ACCOUNT_CONTRACT_VERSION ) );
		$delivery = SAUTH_Email_Verification::issue( $user_id, $payload['email'], true );
		SA_Security::clear_rate_limit( 'registration_email', $email_key );

		if ( is_wp_error( $delivery ) ) {
			wp_safe_redirect( SA_Security::message_url( 'email_verify', 'error', $delivery->get_error_message() ) );
			exit;
		}
		wp_safe_redirect( SA_Security::message_url( 'email_verify', 'success', 'Your account was created. Open the one-time link sent to your email address to verify ownership.' ) );
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
		if ( $password !== $confirm || strlen( $password ) < self::MIN_PASSWORD_LENGTH ) {
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

	/**
	 * @param array<string,mixed> $input Raw request input.
	 * @return array<string,mixed>
	 */
	public static function registration_input( array $input ) {
		return array(
			'name'               => isset( $input['name'] ) ? sanitize_text_field( wp_unslash( $input['name'] ) ) : '',
			'email'              => isset( $input['email'] ) ? sanitize_email( wp_unslash( $input['email'] ) ) : '',
			'phone'              => isset( $input['phone'] ) ? sanitize_text_field( wp_unslash( $input['phone'] ) ) : '',
			'password'           => isset( $input['password'] ) ? (string) wp_unslash( $input['password'] ) : '',
			'password_confirm'   => isset( $input['password_confirm'] ) ? (string) wp_unslash( $input['password_confirm'] ) : '',
			'sex'                => isset( $input['sex'] ) ? sanitize_key( wp_unslash( $input['sex'] ) ) : '',
			'date_of_birth'      => isset( $input['date_of_birth'] ) ? sanitize_text_field( wp_unslash( $input['date_of_birth'] ) ) : '',
			'address'            => isset( $input['address'] ) ? sanitize_textarea_field( wp_unslash( $input['address'] ) ) : '',
			'country'            => isset( $input['country'] ) ? sanitize_text_field( wp_unslash( $input['country'] ) ) : '',
			'identity_reference' => isset( $input['identity_reference'] ) ? sanitize_text_field( wp_unslash( $input['identity_reference'] ) ) : '',
			'guardian_reference' => isset( $input['guardian_reference'] ) ? sanitize_text_field( wp_unslash( $input['guardian_reference'] ) ) : '',
			'terms_version'      => ! empty( $input['accept_terms'] ) ? '2026-08-05' : '',
			'privacy_version'    => ! empty( $input['accept_privacy'] ) ? '2026-08-05' : '',
		);
	}

	/**
	 * Validate File 02-owned input before the File 00 command handoff.
	 *
	 * @return true|WP_Error
	 */
	public static function validate_registration( array $payload ) {
		if ( strlen( trim( (string) $payload['name'] ) ) < 2 || strlen( (string) $payload['name'] ) > 100 ) {
			return new WP_Error( 'sauth_registration_name', 'Enter your complete name.' );
		}
		if ( ! is_email( (string) $payload['email'] ) ) {
			return new WP_Error( 'sauth_registration_email', 'Enter a valid email address.' );
		}
		$phone_digits = preg_replace( '/\D+/', '', (string) $payload['phone'] );
		if ( strlen( $phone_digits ) < 8 || strlen( $phone_digits ) > 18 ) {
			return new WP_Error( 'sauth_registration_phone', 'Enter a valid phone number with country code.' );
		}
		if ( strlen( (string) $payload['password'] ) < self::MIN_PASSWORD_LENGTH || $payload['password'] !== $payload['password_confirm'] ) {
			return new WP_Error( 'sauth_registration_password', 'Use matching passwords of at least 12 characters.' );
		}
		if ( ! in_array( $payload['sex'], array( 'male', 'female' ), true ) ) {
			return new WP_Error( 'sauth_registration_sex', 'Select the applicable sex for the platform age rule.' );
		}
		$age = self::age_from_date( (string) $payload['date_of_birth'] );
		if ( null === $age ) {
			return new WP_Error( 'sauth_registration_birth_date', 'Enter a valid date of birth.' );
		}
		$minimum = 'male' === $payload['sex'] ? self::MIN_MALE_AGE : self::MIN_FEMALE_AGE;
		if ( $age < $minimum ) {
			return new WP_Error( 'sauth_registration_age', 'The account does not meet the platform minimum-age rule.' );
		}
		if ( $age < 18 && '' === trim( (string) $payload['guardian_reference'] ) ) {
			return new WP_Error( 'sauth_registration_guardian', 'A verifiable guardian reference is required for every minor account.' );
		}
		if ( '' === trim( (string) $payload['address'] ) || '' === trim( (string) $payload['country'] ) ) {
			return new WP_Error( 'sauth_registration_address', 'Enter your address and country.' );
		}
		if ( '' === trim( (string) $payload['identity_reference'] ) ) {
			return new WP_Error( 'sauth_registration_identity', 'Enter the National ID or Passport reference required by Membership Core.' );
		}
		if ( '' === (string) $payload['terms_version'] || '' === (string) $payload['privacy_version'] ) {
			return new WP_Error( 'sauth_registration_consent', 'Accept the current Terms and Privacy Notice to continue.' );
		}
		return true;
	}

	private static function age_from_date( $date ) {
		$birth = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		$errors = DateTimeImmutable::getLastErrors();
		if ( ! $birth || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $birth > new DateTimeImmutable( 'today' ) ) {
			return null;
		}
		return (int) $birth->diff( new DateTimeImmutable( 'today' ) )->y;
	}

	private static function sign_in_allowed( array $assertion, array $completion ) {
		if ( 'unknown' === ( $assertion['result'] ?? 'unknown' ) ) {
			return false;
		}
		if ( ! empty( $assertion['membership']['suspended'] ) ) {
			return false;
		}
		if ( 'allow' === ( $assertion['result'] ?? '' ) ) {
			return true;
		}
		return 'allow' === ( $completion['result'] ?? '' ) && ! empty( $completion['missing_steps'] ) && ! empty( $completion['next_route'] );
	}

	private function login_failure( $user_id, $redirect, $reason ) {
		$user_id = absint( $user_id );
		SAUTH_Event_Outbox::emit(
			'AccountAuthenticationFailed.v1',
			$user_id,
			$user_id,
			array( 'method' => 'password', 'reason' => sanitize_key( (string) $reason ) ),
			'security'
		);
		if ( $user_id ) {
			SA_Membership_Adapter::audit( 'password_authentication_failed', $user_id, array( 'reason' => sanitize_key( (string) $reason ) ) );
		}
		$url = add_query_arg( 'redirect_to', rawurlencode( SA_Security::safe_redirect( $redirect ) ), SA_Security::message_url( 'login', 'error', 'The sign-in details were not accepted. Check your credentials or complete account verification.' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private function registration_redirect( $type, $message ) {
		wp_safe_redirect( SA_Security::message_url( 'signup', $type, $message ) );
		exit;
	}
}
