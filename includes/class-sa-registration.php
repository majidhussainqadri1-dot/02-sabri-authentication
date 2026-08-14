<?php

defined( 'ABSPATH' ) || exit;

/**
 * Authentication entry, registration orchestration and password recovery.
 * File 00 remains the sole membership, identity, account-class, guardian,
 * role and verification owner.
 */
final class SA_Registration {
	const MIN_PASSWORD_LENGTH = 12;
	const MAX_PASSWORD_BYTES  = 4096;
	const MIN_MALE_AGE        = 15;
	const MIN_FEMALE_AGE      = 12;
	const POLICY_VERSION      = '2026-08-06';
	const RECOVERY_JOB_HOOK   = 'sauth_password_recovery_job';
	const RECOVERY_JOB_TTL    = 900;

	public function hooks() {
		add_action( 'admin_post_nopriv_sa_login', array( $this, 'login' ) );
		add_action( 'admin_post_sa_login', array( $this, 'login' ) );
		add_action( 'admin_post_nopriv_sauth_login', array( $this, 'login' ) );
		add_action( 'admin_post_sauth_login', array( $this, 'login' ) );
		add_action( 'admin_post_nopriv_sa_register', array( $this, 'register' ) );
		add_action( 'admin_post_sa_register', array( $this, 'register' ) );
		add_action( 'admin_post_nopriv_sauth_register', array( $this, 'register' ) );
		add_action( 'admin_post_sauth_register', array( $this, 'register' ) );
		add_action( 'admin_post_nopriv_sa_forgot_password', array( $this, 'forgot_password' ) );
		add_action( 'admin_post_sa_forgot_password', array( $this, 'forgot_password' ) );
		add_action( 'admin_post_nopriv_sauth_forgot_password', array( $this, 'forgot_password' ) );
		add_action( 'admin_post_sauth_forgot_password', array( $this, 'forgot_password' ) );
		add_action( 'admin_post_nopriv_sa_reset_password', array( $this, 'reset_password' ) );
		add_action( 'admin_post_sa_reset_password', array( $this, 'reset_password' ) );
		add_action( 'admin_post_nopriv_sauth_reset_password', array( $this, 'reset_password' ) );
		add_action( 'admin_post_sauth_reset_password', array( $this, 'reset_password' ) );
		add_action( 'admin_post_sa_logout', array( $this, 'logout' ) );
		add_action( 'admin_post_sauth_logout', array( $this, 'logout' ) );
		add_action( self::RECOVERY_JOB_HOOK, array( __CLASS__, 'run_recovery_job' ), 10, 1 );
	}

	/**
	 * File 00 currently exposes these provider values. R288/R294 separately
	 * blocks release until File 00 canonical taxonomy and provider vocabulary are
	 * harmonized; File 02 deliberately does not invent a lossy remap here.
	 */
	public static function account_types() {
		return array(
			'member'                     => 'Member / Patient / General User',
			'doctor'                     => 'Homeopathic Doctor',
			'student'                    => 'Student',
			'teacher'                    => 'Teacher / Trainer',
			'researcher'                 => 'Researcher / Author',
			'clinic_staff'                => 'Clinic Staff',
			'institution_representative' => 'Institution Representative',
		);
	}

	public function login() {
		check_admin_referer( 'sa_login', 'sa_nonce' );
		$redirect = isset( $_POST['redirect_to'] ) ? SA_Security::safe_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) ) : home_url( '/' );
		$login    = isset( $_POST['user_login'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$remember = ! empty( $_POST['rememberme'] );
		$trap     = isset( $_POST['website'] ) ? trim( (string) wp_unslash( $_POST['website'] ) ) : '';
		if ( '' !== $trap || '' === $login || strlen( $login ) > 320 || '' === $password || strlen( $password ) > self::MAX_PASSWORD_BYTES ) {
			$password = '';
			$this->login_failure( 0, $redirect, 'invalid_request' );
		}
		$subject = strtolower( $login );
		if ( SA_Security::rate_limited( 'password_login_ip', 20, 900 ) || SA_Security::rate_limited( 'password_login_account', 8, 900, $subject ) ) {
			$password = '';
			$this->login_failure( 0, $redirect, 'rate_limited' );
		}
		$user = false !== strpos( $login, '@' ) ? get_user_by( 'email', sanitize_email( $login ) ) : get_user_by( 'login', sanitize_user( $login ) );
		$hash = $user instanceof WP_User ? (string) $user->user_pass : (string) get_option( 'sauth_dummy_password_hash', '' );
		if ( '' === $hash && function_exists( 'wp_hash_password' ) ) {
			$hash = wp_hash_password( SA_Security::random_token( 32 ) );
		}
		$valid = '' !== $hash && function_exists( 'wp_check_password' ) && wp_check_password( $password, $hash, $user instanceof WP_User ? $user->ID : 0 );
		$password = '';
		unset( $_POST['password'] );
		if ( ! $valid || ! $user instanceof WP_User ) {
			$this->login_failure( 0, $redirect, 'credentials_invalid' );
		}
		if ( ! SAUTH_Provider_Health::allow_request( 'membership' ) ) {
			$this->login_failure( $user->ID, $redirect, 'membership_provider_circuit_open' );
		}
		$started = microtime( true );
		$completion = SAUTH_Account_Contract::completion_state( $user->ID, array( 'purpose' => 'password_sign_in' ) );
		/* File 00 has no authentication_sign_in capability; authentication is File
		 * 02-owned. Consume only the membership prerequisite it actually owns. */
		$assertion = SA_Membership_Adapter::membership_assertion( $user->ID, 'clinical_identity_link', 'authentication_sign_in' );
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );
		if ( 'unknown' === ( $assertion['result'] ?? 'unknown' ) || 'unknown' === ( $completion['result'] ?? 'unknown' ) ) {
			SAUTH_Provider_Health::record_failure( 'membership', 'authentication_assertion_unknown', $latency );
		} else {
			SAUTH_Provider_Health::record_success( 'membership', $latency );
		}
		if ( ! self::sign_in_allowed( $assertion, $completion ) ) {
			$this->login_failure( $user->ID, $redirect, 'membership_not_eligible' );
		}
		SA_Security::clear_rate_limit( 'password_login_account', $subject );
		SAUTH_Login_Risk::complete_password_login( $user, $remember, $redirect, $completion );
	}

	public function register() {
		check_admin_referer( 'sa_register', 'sa_nonce' );
		$trap = isset( $_POST['website'] ) ? trim( (string) wp_unslash( $_POST['website'] ) ) : '';
		if ( '' !== $trap ) { $this->registration_redirect( 'error', 'Registration could not be completed.' ); }
		if ( SAUTH_Operations::safe_mode() ) { $this->registration_redirect( 'error', 'Account registration is temporarily paused by Safe Mode. Public reading remains available.' ); }
		if ( ! SAUTH_Account_Contract::provider_available() || ! SAUTH_Provider_Health::allow_request( 'membership' ) ) {
			$this->registration_redirect( 'error', 'Account registration is temporarily unavailable. No account was created.' );
		}
		$google_token = isset( $_POST['google_registration_token'] ) ? sanitize_text_field( wp_unslash( $_POST['google_registration_token'] ) ) : '';
		if ( strlen( $google_token ) > 256 ) { $this->registration_redirect( 'error', 'The Google registration proof is invalid.' ); }
		$google_context = '' !== $google_token ? SAUTH_Google_Registration::context( $google_token ) : array();
		if ( '' !== $google_token && empty( $google_context ) ) { $this->registration_redirect( 'error', 'The Google registration proof expired or changed. Start Google registration again.' ); }
		$payload = self::registration_input( $_POST, $google_context );
		$valid = self::validate_registration( $payload );
		if ( is_wp_error( $valid ) ) { $this->registration_redirect( 'error', $valid->get_error_message() ); }
		$email_key = hash_hmac( 'sha256', strtolower( $payload['email'] ), wp_salt( 'nonce' ) );
		if ( SA_Security::rate_limited( 'registration_ip', 8, HOUR_IN_SECONDS ) || SA_Security::rate_limited( 'registration_email', 3, HOUR_IN_SECONDS, $email_key ) ) {
			$this->registration_redirect( 'error', 'Registration is temporarily limited. Please wait and try again.' );
		}

		$consumed_google = array();
		if ( 'google' === $payload['authentication_method'] ) {
			/* Consume immediately before the first account mutation so concurrent form
			 * submissions cannot reuse the same Google proof. */
			$consumed_google = SAUTH_Google_Registration::consume( $google_token );
			if ( empty( $consumed_google )
				|| ! hash_equals( strtolower( (string) $payload['email'] ), strtolower( (string) ( $consumed_google['email'] ?? '' ) )
				|| ! hash_equals( (string) $payload['google_subject'], (string) ( $consumed_google['sub'] ?? '' ) ) ) {
				$this->registration_redirect( 'error', 'The Google registration proof was already used or changed. Start Google registration again.' );
			}
		}

		$started = microtime( true );
		$result = SAUTH_Account_Contract::register_account(
			$payload,
			array(
				'purpose' => 'account_registration',
				'idempotency_key' => 'registration-' . substr( $email_key, 0, 24 ) . '-' . gmdate( 'YmdH' ),
			)
		);
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );
		if ( 'allow' !== ( $result['result'] ?? '' ) || empty( $result['user_id'] ) ) {
			SAUTH_Provider_Health::record_failure( 'membership', sanitize_key( (string) ( $result['reason_code'] ?? 'provider_rejected' ) ), $latency );
			SAUTH_Event_Outbox::emit( 'AccountAuthenticationFailed.v1', 0, 0, array( 'method' => 'registration', 'reason' => sanitize_key( (string) ( $result['reason_code'] ?? 'provider_rejected' ) ) ), 'security' );
			$this->registration_redirect( 'error', 'Registration could not be completed. The details may already belong to an account, or the membership service may require review.' );
		}
		SAUTH_Provider_Health::record_success( 'membership', $latency );
		$user_id = absint( $result['user_id'] );
		if ( 'google' === $payload['authentication_method'] ) {
			if ( ! SAUTH_Google_Registration::finalize_link( $user_id, $consumed_google ) ) {
				SAUTH_Session_Manager::revoke_user_sessions( $user_id, 'google_registration_link_failed' );
				SA_Membership_Adapter::audit( 'google_registration_link_failed', $user_id );
				$this->registration_redirect( 'error', 'The account was placed in protected incomplete state because the Google link could not be finalized. Use password recovery or contact support.' );
			}
			$verified = SAUTH_Account_Contract::mark_email_verified( $user_id, $payload['email'], array( 'purpose' => 'google_registration_email_ownership' ) );
			if ( 'allow' !== ( $verified['result'] ?? '' ) ) {
				SAUTH_Session_Manager::revoke_user_sessions( $user_id, 'google_registration_email_completion_failed' );
				$this->registration_redirect( 'error', 'The account was created but email ownership completion failed safely. Contact support.' );
			}
			SAUTH_Event_Outbox::emit( 'EmailVerified.v1', $user_id, $user_id, array( 'method' => 'google_oidc_registration' ), 'security' );
			wp_safe_redirect( SA_Security::message_url( 'complete', 'success', 'Your Google email was verified. Complete the remaining identity, profile photograph, phone, guardian and verification steps.' ) );
			exit;
		}
		$delivery = SAUTH_Email_Verification::issue( $user_id, $payload['email'], true );
		SA_Membership_Adapter::audit( 'account_registration_orchestrated', $user_id, array( 'contract_version' => SAUTH_ACCOUNT_CONTRACT_VERSION, 'account_type' => $payload['account_type'] ) );
		$payload['password'] = '';
		$payload['password_confirm'] = '';
		unset( $_POST['password'], $_POST['password_confirm'] );
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
		$login = isset( $_POST['user_login'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) ) : '';
		if ( strlen( $login ) > 320 ) { $login = ''; }
		$subject = strtolower( $login );
		$blocked = SA_Security::rate_limited( 'forgot_password_ip', 12, 1800 ) || SA_Security::rate_limited( 'forgot_password_account', 4, 1800, $subject );
		if ( ! $blocked && '' !== $login ) {
			$user = false !== strpos( $login, '@' ) ? get_user_by( 'email', sanitize_email( $login ) ) : get_user_by( 'login', sanitize_user( $login ) );
			$user_id = $user instanceof WP_User ? (int) $user->ID : 0;
			$job_user_id = 0;
			$job_epoch   = '';
			if ( $user_id && SAUTH_Privacy_Jobs::can_enqueue( $user_id ) ) {
				$job_epoch = SAUTH_Privacy_Jobs::snapshot( $user_id );
				if ( '' !== $job_epoch ) { $job_user_id = $user_id; }
			}
			$job_token = SA_Security::random_token( 16 );
			if ( '' !== $job_token ) {
				$job_key = self::recovery_job_key( $job_token );
				/* A no-match or erasure-blocked request still gets the same opaque job
				 * shape; the worker simply performs no account mutation. */
				$job = array( 'user_id' => $job_user_id, 'privacy_epoch' => $job_epoch, 'created_at' => time() );
				set_transient( $job_key, $job, self::RECOVERY_JOB_TTL );
				if ( get_transient( $job_key ) === $job && function_exists( 'wp_schedule_single_event' ) ) {
					wp_schedule_single_event( time() + 1, self::RECOVERY_JOB_HOOK, array( $job_token ) );
				} else {
					delete_transient( $job_key );
				}
			}
		}
		wp_safe_redirect( SA_Security::message_url( 'forgot', 'success', 'If the account exists and delivery is available, a reset email will be sent.' ) );
		exit;
	}

	public static function run_recovery_job( $job_token ) {
		$job_token = sanitize_text_field( (string) $job_token );
		if ( '' === $job_token || strlen( $job_token ) > 128 ) { return; }
		$key = self::recovery_job_key( $job_token );
		$job = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $job ) || absint( $job['created_at'] ?? 0 ) < time() - self::RECOVERY_JOB_TTL ) { return; }
		$user_id = absint( $job['user_id'] ?? 0 );
		$epoch   = (string) ( $job['privacy_epoch'] ?? '' );
		if ( ! $user_id || ! SAUTH_Privacy_Jobs::valid_snapshot( $user_id, $epoch ) ) { return; }
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) { return; }
		$started = microtime( true );
		$result = retrieve_password( $user->user_login );
		$latency = (int) round( ( microtime( true ) - $started ) * 1000 );
		if ( is_wp_error( $result ) ) { SAUTH_Provider_Health::record_failure( 'email', 'recovery_delivery_failed', $latency ); }
		else { SAUTH_Provider_Health::record_success( 'email', $latency ); }
	}

	public function reset_password() {
		$login = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ) ) : '';
		$key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		if ( strlen( $login ) > 128 || strlen( $key ) > 256 ) {
			wp_safe_redirect( SA_Security::message_url( 'reset', 'error', 'This reset link is invalid, expired or already used.' ) );
			exit;
		}
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
		if ( strlen( $password ) > self::MAX_PASSWORD_BYTES || strlen( $confirm ) > self::MAX_PASSWORD_BYTES || $password !== $confirm || strlen( $password ) < self::MIN_PASSWORD_LENGTH ) {
			$password = ''; $confirm = '';
			$url = add_query_arg( array( 'key' => rawurlencode( $key ), 'login' => rawurlencode( $login ) ), SA_Security::message_url( 'reset', 'error', 'Use matching passwords of at least 12 characters.' ) );
			wp_safe_redirect( $url );
			exit;
		}
		reset_password( $user, $password );
		$password = ''; $confirm = '';
		unset( $_POST['password'], $_POST['password_confirm'] );
		SAUTH_Session_Manager::revoke_user_sessions( $user->ID, 'password_reset' );
		SA_Security::clear_rate_limit( 'reset_password', strtolower( $login ) );
		SAUTH_Event_Outbox::emit( 'PasswordResetCompleted.v1', $user->ID, $user->ID, array( 'all_sessions_revoked' => true, 'method' => 'email_reset' ), 'security' );
		SA_Membership_Adapter::audit( 'password_reset_completed', $user->ID );
		wp_safe_redirect( SA_Security::message_url( 'login', 'success', 'Your password was changed. Sign in again on this device.' ) );
		exit;
	}

	public function logout() {
		check_admin_referer( 'sa_logout' );
		$user_id = get_current_user_id();
		$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/' );
		wp_logout();
		if ( $user_id ) { SA_Membership_Adapter::audit( 'authentication_session_logout', $user_id ); }
		wp_safe_redirect( SA_Security::safe_redirect( $redirect ) );
		exit;
	}

	public static function registration_input( array $input, array $google_context = array() ) {
		$google = ! empty( $google_context['email'] ) && ! empty( $google_context['sub'] );
		return array(
			'name' => $google && ! empty( $google_context['name'] ) ? sanitize_text_field( $google_context['name'] ) : ( isset( $input['name'] ) ? sanitize_text_field( wp_unslash( $input['name'] ) ) : '' ),
			'email' => $google ? sanitize_email( $google_context['email'] ) : ( isset( $input['email'] ) ? sanitize_email( wp_unslash( $input['email'] ) ) : '' ),
			'phone' => isset( $input['phone'] ) ? sanitize_text_field( wp_unslash( $input['phone'] ) ) : '',
			'password' => $google ? '' : ( isset( $input['password'] ) ? (string) wp_unslash( $input['password'] ) : '' ),
			'password_confirm' => $google ? '' : ( isset( $input['password_confirm'] ) ? (string) wp_unslash( $input['password_confirm'] ) : '' ),
			'authentication_method' => $google ? 'google' : 'password',
			'google_subject' => $google ? sanitize_text_field( $google_context['sub'] ) : '',
			'google_email_verified' => $google,
			'google_picture_candidate' => $google && ! empty( $google_context['picture'] ) ? esc_url_raw( $google_context['picture'] ) : '',
			'sex' => isset( $input['sex'] ) ? sanitize_key( wp_unslash( $input['sex'] ) ) : '',
			'date_of_birth' => isset( $input['date_of_birth'] ) ? sanitize_text_field( wp_unslash( $input['date_of_birth'] ) ) : '',
			'address' => isset( $input['address'] ) ? sanitize_textarea_field( wp_unslash( $input['address'] ) ) : '',
			'city' => isset( $input['city'] ) ? sanitize_text_field( wp_unslash( $input['city'] ) ) : '',
			'country' => isset( $input['country'] ) ? sanitize_text_field( wp_unslash( $input['country'] ) ) : '',
			'account_type' => isset( $input['account_type'] ) ? sanitize_key( wp_unslash( $input['account_type'] ) ) : '',
			'identity_type' => isset( $input['identity_type'] ) ? sanitize_key( wp_unslash( $input['identity_type'] ) ) : '',
			'identity_reference' => isset( $input['identity_reference'] ) ? sanitize_text_field( wp_unslash( $input['identity_reference'] ) ) : '',
			'guardian_reference' => isset( $input['guardian_reference'] ) ? sanitize_text_field( wp_unslash( $input['guardian_reference'] ) ) : '',
			'profile_photo_required' => ! empty( $input['profile_photo_required'] ),
			'terms_version' => ! empty( $input['accept_terms'] ) ? self::POLICY_VERSION : '',
			'privacy_version' => ! empty( $input['accept_privacy'] ) ? self::POLICY_VERSION : '',
			'ethical_conduct_version' => ! empty( $input['accept_ethics'] ) ? self::POLICY_VERSION : '',
		);
	}

	public static function validate_registration( array $payload ) {
		if ( strlen( trim( (string) $payload['name'] ) ) < 2 || strlen( (string) $payload['name'] ) > 100 ) { return new WP_Error( 'sauth_registration_name', 'Enter your complete name.' ); }
		if ( strlen( (string) $payload['email'] ) > 320 || ! is_email( (string) $payload['email'] ) ) { return new WP_Error( 'sauth_registration_email', 'Enter a valid email address.' ); }
		if ( strlen( (string) $payload['phone'] ) > 64 ) { return new WP_Error( 'sauth_registration_phone', 'Enter a valid phone number with country code.' ); }
		$phone_digits = preg_replace( '/\D+/', '', (string) $payload['phone'] );
		if ( strlen( $phone_digits ) < 8 || strlen( $phone_digits ) > 18 ) { return new WP_Error( 'sauth_registration_phone', 'Enter a valid phone number with country code.' ); }
		if ( 'password' === $payload['authentication_method'] && ( strlen( (string) $payload['password'] ) < self::MIN_PASSWORD_LENGTH || strlen( (string) $payload['password'] ) > self::MAX_PASSWORD_BYTES || strlen( (string) $payload['password_confirm'] ) > self::MAX_PASSWORD_BYTES || $payload['password'] !== $payload['password_confirm'] ) ) { return new WP_Error( 'sauth_registration_password', 'Use matching passwords of at least 12 characters.' ); }
		if ( 'google' === $payload['authentication_method'] && ( empty( $payload['google_email_verified'] ) || '' === trim( (string) $payload['google_subject'] ) || strlen( (string) $payload['google_subject'] ) > 255 ) ) { return new WP_Error( 'sauth_registration_google', 'The Google email-ownership proof is invalid or expired.' ); }
		if ( ! in_array( $payload['sex'], array( 'male', 'female' ), true ) ) { return new WP_Error( 'sauth_registration_sex', 'Select the applicable sex for the platform age rule.' ); }
		$age = self::age_from_date( (string) $payload['date_of_birth'] );
		if ( null === $age ) { return new WP_Error( 'sauth_registration_birth_date', 'Enter a valid date of birth.' ); }
		$minimum = 'male' === $payload['sex'] ? self::MIN_MALE_AGE : self::MIN_FEMALE_AGE;
		if ( $age < $minimum ) { return new WP_Error( 'sauth_registration_age', 'The account does not meet the platform minimum-age rule.' ); }
		if ( strlen( (string) $payload['guardian_reference'] ) > 200 || ( $age < 18 && '' === trim( (string) $payload['guardian_reference'] ) ) ) { return new WP_Error( 'sauth_registration_guardian', 'A verifiable guardian reference is required for every minor account.' ); }
		if ( strlen( (string) $payload['address'] ) > 1000 || strlen( (string) $payload['city'] ) > 120 || strlen( (string) $payload['country'] ) > 120 || '' === trim( (string) $payload['address'] ) || '' === trim( (string) $payload['city'] ) || '' === trim( (string) $payload['country'] ) ) { return new WP_Error( 'sauth_registration_address', 'Enter your full address, city and country.' ); }
		if ( ! array_key_exists( $payload['account_type'], self::account_types() ) ) { return new WP_Error( 'sauth_registration_account_type', 'Select the account type that truthfully describes your intended use.' ); }
		if ( in_array( $payload['account_type'], array( 'doctor', 'teacher', 'clinic_staff', 'institution_representative' ), true ) && $age < 18 ) { return new WP_Error( 'sauth_registration_professional_age', 'Professional and institutional account declarations require an adult account.' ); }
		if ( ! in_array( $payload['identity_type'], array( 'national_id', 'passport' ), true ) ) { return new WP_Error( 'sauth_registration_identity_type', 'Select National ID or Passport.' ); }
		if ( strlen( trim( (string) $payload['identity_reference'] ) ) < 5 || strlen( (string) $payload['identity_reference'] ) > 200 ) { return new WP_Error( 'sauth_registration_identity', 'Enter the selected National ID or Passport reference required by Membership Core.' ); }
		if ( empty( $payload['profile_photo_required'] ) ) { return new WP_Error( 'sauth_registration_profile_photo', 'Acknowledge that a profile photograph must be completed through the canonical profile workflow.' ); }
		if ( '' === (string) $payload['terms_version'] || '' === (string) $payload['privacy_version'] || '' === (string) $payload['ethical_conduct_version'] ) { return new WP_Error( 'sauth_registration_consent', 'Accept the current Terms, Privacy Notice and Ethical Conduct Charter to continue.' ); }
		return true;
	}

	private static function recovery_job_key( $token ) {
		return 'sauth_password_recovery_job_' . hash( 'sha256', (string) $token );
	}

	private static function age_from_date( $date ) {
		$birth = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		$errors = DateTimeImmutable::getLastErrors();
		if ( ! $birth || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $birth > new DateTimeImmutable( 'today' ) ) { return null; }
		return (int) $birth->diff( new DateTimeImmutable( 'today' ) )->y;
	}

	private static function sign_in_allowed( array $assertion, array $completion ) {
		if ( 'unknown' === ( $assertion['result'] ?? 'unknown' ) || ! empty( $assertion['membership']['suspended'] ) ) { return false; }
		if ( 'allow' === ( $assertion['result'] ?? '' ) ) { return true; }
		return 'allow' === ( $completion['result'] ?? '' ) && ! empty( $completion['missing_steps'] ) && ! empty( $completion['next_route'] );
	}

	private function login_failure( $user_id, $redirect, $reason ) {
		$user_id = absint( $user_id );
		SAUTH_Login_Risk::record_failure( $user_id, $reason );
		SAUTH_Event_Outbox::emit( 'AccountAuthenticationFailed.v1', $user_id, $user_id, array( 'method' => 'password', 'reason' => sanitize_key( (string) $reason ) ), 'security' );
		if ( $user_id ) { SA_Membership_Adapter::audit( 'password_authentication_failed', $user_id, array( 'reason' => sanitize_key( (string) $reason ) ) ); }
		$url = add_query_arg( 'redirect_to', rawurlencode( SA_Security::safe_redirect( $redirect ) ), SA_Security::message_url( 'login', 'error', 'The sign-in details were not accepted. Check your credentials or complete account verification.' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private function registration_redirect( $type, $message ) {
		wp_safe_redirect( SA_Security::message_url( 'signup', $type, $message ) );
		exit;
	}
}
