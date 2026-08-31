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

	/** File 00 canonical account taxonomy; File 02 performs no aliases or lossy remap. */
	public static function account_types() {
		return array(
			'member'     => 'Member / General User',
			'patient'    => 'Patient',
			'student'    => 'Student',
			'doctor'     => 'Homeopathic Doctor',
			'teacher'    => 'Teacher / Trainer',
			'researcher' => 'Researcher / Author',
			'pharmacy'   => 'Pharmacy',
			'clinic'     => 'Clinic',
			'publisher'  => 'Publisher',
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
		$google_locks = array();
		if ( 'google' === $payload['authentication_method'] ) {
			/* Consume immediately before the first account mutation so concurrent form
			 * submissions cannot reuse the same Google proof. */
			$consumed_google = SAUTH_Google_Registration::consume( $google_token );
			if ( empty( $consumed_google )
				|| ! hash_equals( strtolower( (string) $payload['email'] ), strtolower( (string) ( $consumed_google['email'] ?? '' ) ) )
				|| ! hash_equals( (string) $payload['google_subject'], (string) ( $consumed_google['sub'] ?? '' ) ) ) {
				$this->registration_redirect( 'error', 'The Google registration proof was already used or changed. Start Google registration again.' );
			}
			$google_locks = SA_Google_OAuth::acquire_link_locks( $payload['google_subject'] );
			if ( empty( $google_locks ) ) {
				$this->registration_redirect( 'error', 'Google registration is busy or could not be serialized safely. Start Google registration again.' );
			}
			if ( ! SA_Google_OAuth::subject_available_for_registration( $payload['google_subject'], $google_locks ) ) {
				self::release_google_registration_locks( $google_locks, 0, 'google_registration_subject_check_failed' );
				$this->registration_redirect( 'error', 'This Google account is already linked, or its ownership could not be proved safely. Use Google sign-in or contact support.' );
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
		/* The canonical owner call is the last operation that needs plaintext
		 * registration passwords. Remove them before any mail, audit or error path. */
		$payload['password'] = '';
		$payload['password_confirm'] = '';
		unset( $_POST['password'], $_POST['password_confirm'] );
		if ( 'allow' !== ( $result['result'] ?? '' ) || empty( $result['user_id'] ) ) {
			self::release_google_registration_locks( $google_locks, 0, 'google_registration_provider_rejected_lock_release_failed' );
			SAUTH_Provider_Health::record_failure( 'membership', sanitize_key( (string) ( $result['reason_code'] ?? 'provider_rejected' ) ), $latency );
			SAUTH_Event_Outbox::emit( 'AccountAuthenticationFailed.v1', 0, 0, array( 'method' => 'registration', 'reason' => sanitize_key( (string) ( $result['reason_code'] ?? 'provider_rejected' ) ) ), 'security' );
			$this->registration_redirect( 'error', 'Registration could not be completed. The details may already belong to an account, or the membership service may require review.' );
		}
		SAUTH_Provider_Health::record_success( 'membership', $latency );
		$user_id = absint( $result['user_id'] );
		if ( 'google' === $payload['authentication_method'] ) {
			$extended_locks = SA_Google_OAuth::acquire_link_locks( $payload['google_subject'], $user_id, $google_locks );
			if ( empty( $extended_locks ) ) {
				SA_Google_OAuth::contain_linkage_failure( $user_id, 'google_registration_user_lock_failed' );
				self::release_google_registration_locks( $google_locks, $user_id, 'google_registration_subject_lock_release_failed' );
				$this->registration_redirect( 'error', 'The account was placed in protected incomplete state because Google ownership could not be serialized safely. Use password recovery or contact support.' );
			}
			$google_locks = $extended_locks;
			if ( ! self::registration_subject_matches( $user_id, (string) ( $result['subject_uuid'] ?? '' ) ) ) {
				SA_Google_OAuth::contain_linkage_failure( $user_id, 'google_registration_subject_mismatch' );
				self::release_google_registration_locks( $google_locks, $user_id, 'google_registration_subject_mismatch_lock_release_failed' );
				$this->registration_redirect( 'error', 'The account was placed in protected incomplete state because its membership identity could not be verified. Contact support.' );
			}
			if ( ! SAUTH_Google_Registration::finalize_link( $user_id, $consumed_google, $google_locks ) ) {
				SA_Google_OAuth::contain_linkage_failure( $user_id, 'google_registration_link_failed' );
				self::release_google_registration_locks( $google_locks, $user_id, 'google_registration_link_failure_lock_release_failed' );
				$this->registration_redirect( 'error', 'The account was placed in protected incomplete state because the Google link could not be finalized. Use password recovery or contact support.' );
			}
			$verified = SAUTH_Account_Contract::mark_email_verified( $user_id, $payload['email'], array( 'purpose' => 'google_registration_email_ownership' ) );
			if ( 'allow' !== ( $verified['result'] ?? '' ) ) {
				SA_Google_OAuth::contain_linkage_failure( $user_id, 'google_registration_email_completion_failed' );
				self::release_google_registration_locks( $google_locks, $user_id, 'google_registration_email_failure_lock_release_failed' );
				$this->registration_redirect( 'error', 'The account was created but email ownership completion failed safely. Contact support.' );
			}
			if ( ! self::release_google_registration_locks( $google_locks, $user_id, 'google_registration_success_lock_release_failed' ) ) {
				$this->registration_redirect( 'error', 'The account was placed in protected incomplete state because the final Google ownership lock could not be verified. Contact support.' );
			}
			SAUTH_Event_Outbox::emit( 'EmailVerified.v1', $user_id, $user_id, array( 'method' => 'google_oidc_registration' ), 'security' );
			wp_safe_redirect( SA_Security::page_url( 'login', home_url( '/account-login/' ) ) );
			exit;
		}
		SAUTH_Email_Verification::send( $user_id, $payload['email'] );
		wp_safe_redirect( SA_Security::page_url( 'verify_email', home_url( '/verify-email/' ) ) );
		exit;
	}

	public function forgot_password() {
		check_admin_referer( 'sa_forgot_password', 'sa_nonce' );
		$login = isset( $_POST['user_login'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) ) : '';
		$generic = 'If the account exists and delivery is available, a reset email will be sent.';
		if ( '' === $login || strlen( $login ) > 320 ) { $this->recovery_redirect( 'success', $generic ); }
		if ( SA_Security::rate_limited( 'password_recovery_ip', 10, HOUR_IN_SECONDS ) ) { $this->recovery_redirect( 'success', $generic ); }
		$user = false !== strpos( $login, '@' ) ? get_user_by( 'email', sanitize_email( $login ) ) : get_user_by( 'login', sanitize_user( $login ) );
		if ( ! $user instanceof WP_User ) { $this->recovery_redirect( 'success', $generic ); }
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) { $this->recovery_redirect( 'success', $generic ); }
		$token = SA_Security::random_token( 24 );
		if ( '' === $token ) { $this->recovery_redirect( 'success', $generic ); }
		$payload = array(
			'user_id' => (int) $user->ID,
			'user_login' => (string) $user->user_login,
			'user_email' => (string) $user->user_email,
			'reset_key' => (string) $key,
			'token_hash' => hash( 'sha256', $token ),
			'expires_at' => time() + self::RECOVERY_JOB_TTL,
		);
		set_transient( self::recovery_job_key( $token ), $payload, self::RECOVERY_JOB_TTL );
		$scheduled = wp_schedule_single_event( time() + 1, self::RECOVERY_JOB_HOOK, array( $token ) );
		if ( is_wp_error( $scheduled ) || false === $scheduled ) {
			delete_transient( self::recovery_job_key( $token ) );
			$payload = array();
		}
		$this->recovery_redirect( 'success', $generic );
	}

	public static function run_recovery_job( $token ) {
		$token = sanitize_text_field( (string) $token );
		if ( strlen( $token ) < 24 || strlen( $token ) > 128 ) { return; }
		$key = self::recovery_job_key( $token );
		$payload = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $payload ) || ! hash_equals( (string) ( $payload['token_hash'] ?? '' ), hash( 'sha256', $token ) ) || time() > absint( $payload['expires_at'] ?? 0 ) ) { return; }
		$user_id = absint( $payload['user_id'] ?? 0 );
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user instanceof WP_User || ! hash_equals( (string) $user->user_login, (string) ( $payload['user_login'] ?? '' ) ) || ! hash_equals( strtolower( (string) $user->user_email ), strtolower( (string) ( $payload['user_email'] ?? '' ) ) ) ) { return; }
		$url = SA_Security::page_url( 'reset_password', wp_login_url() );
		$url = add_query_arg( array( 'key' => rawurlencode( (string) $payload['reset_key'] ), 'login' => rawurlencode( (string) $user->user_login ) ), $url );
		$sent = wp_mail( $user->user_email, 'Reset your Sabri Homeopathy password', "A password reset was requested for your account.\n\nReset password: " . $url . "\n\nIf you did not request this, ignore this message." );
		SAUTH_Event_Outbox::emit( 'AccountRecoveryRequested.v1', $user_id, $user_id, array( 'delivery' => $sent ? 'accepted' : 'failed' ), 'security' );
	}

	public function reset_password() {
		check_admin_referer( 'sa_reset_password', 'sa_nonce' );
		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$login = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$confirm = isset( $_POST['password_confirm'] ) ? (string) wp_unslash( $_POST['password_confirm'] ) : '';
		if ( strlen( $key ) < 20 || strlen( $key ) > 128 || '' === $login || strlen( $login ) > 60 || strlen( $password ) > self::MAX_PASSWORD_BYTES || ! hash_equals( $password, $confirm ) || strlen( $password ) < self::MIN_PASSWORD_LENGTH ) {
			$password = $confirm = '';
			$this->reset_redirect( 'error', 'The reset request or new password is invalid.' );
		}
		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) || ! $user instanceof WP_User ) { $password = $confirm = ''; $this->reset_redirect( 'error', 'This password reset link is invalid or expired.' ); }
		reset_password( $user, $password );
		$password = $confirm = '';
		SA_Membership_Adapter::audit( 'password_reset_completed', $user->ID );
		SAUTH_Event_Outbox::emit( 'CredentialChanged.v1', $user->ID, $user->ID, array( 'method' => 'password' ), 'security' );
		SAUTH_Event_Outbox::emit( 'SessionRevoked.v1', $user->ID, $user->ID, array( 'scope' => 'all', 'reason' => 'password_reset' ), 'security' );
		$this->reset_redirect( 'success', 'Password updated. Sign in with your new password.' );
	}

	public function logout() {
		check_admin_referer( 'sa_logout', 'sa_nonce' );
		$user_id = get_current_user_id();
		if ( $user_id ) {
			SAUTH_Session_Manager::revoke_current_session( $user_id, 'user_logout' );
		}
		wp_logout();
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	private function login_failure( $user_id, $redirect, $reason ) {
		$user_id = absint( $user_id );
		SAUTH_Login_Risk::record_failure( $user_id, $reason, 100 );
		SAUTH_Event_Outbox::emit( 'AccountAuthenticationFailed.v1', $user_id, $user_id, array( 'method' => 'password', 'reason' => sanitize_key( $reason ) ), 'security' );
		$url = SA_Membership_Adapter::login_url( $redirect );
		$url = add_query_arg( SA_Security::notice_query_args( 'error', 'The sign-in details were not accepted. Check your credentials or complete account verification.' ), $url );
		wp_safe_redirect( $url );
		exit;
	}

	private function registration_redirect( $type, $message ) {
		$url = SA_Membership_Adapter::register_url();
		$url = add_query_arg( SA_Security::notice_query_args( $type, $message ), $url );
		wp_safe_redirect( $url );
		exit;
	}

	private function recovery_redirect( $type, $message ) {
		$url = SA_Security::page_url( 'forgot_password', wp_login_url() );
		$url = add_query_arg( SA_Security::notice_query_args( $type, $message ), $url );
		wp_safe_redirect( $url );
		exit;
	}

	private function reset_redirect( $type, $message ) {
		$url = SA_Security::page_url( 'reset_password', wp_login_url() );
		$url = add_query_arg( SA_Security::notice_query_args( $type, $message ), $url );
		wp_safe_redirect( $url );
		exit;
	}

	private static function registration_input( $source, $google_context = array() ) {
		$google_context = is_array( $google_context ) ? $google_context : array();
		$is_google = ! empty( $google_context );
		return array(
			'name' => isset( $source['name'] ) ? sanitize_text_field( wp_unslash( $source['name'] ) ) : '',
			'email' => $is_google ? sanitize_email( (string) ( $google_context['email'] ?? '' ) ) : ( isset( $source['email'] ) ? sanitize_email( wp_unslash( $source['email'] ) ) : '' ),
			'phone' => isset( $source['phone'] ) ? sanitize_text_field( wp_unslash( $source['phone'] ) ) : '',
			'account_type' => isset( $source['account_type'] ) ? sanitize_key( wp_unslash( $source['account_type'] ) ) : '',
			'country' => isset( $source['country'] ) ? sanitize_text_field( wp_unslash( $source['country'] ) ) : '',
			'city' => isset( $source['city'] ) ? sanitize_text_field( wp_unslash( $source['city'] ) ) : '',
			'sex' => isset( $source['sex'] ) ? sanitize_key( wp_unslash( $source['sex'] ) ) : '',
			'date_of_birth' => isset( $source['date_of_birth'] ) ? sanitize_text_field( wp_unslash( $source['date_of_birth'] ) ) : '',
			'address' => isset( $source['address'] ) ? sanitize_textarea_field( wp_unslash( $source['address'] ) ) : '',
			'identity_type' => isset( $source['identity_type'] ) ? sanitize_key( wp_unslash( $source['identity_type'] ) ) : '',
			'identity_reference' => isset( $source['identity_reference'] ) ? sanitize_text_field( wp_unslash( $source['identity_reference'] ) ) : '',
			'guardian_reference' => isset( $source['guardian_reference'] ) ? sanitize_text_field( wp_unslash( $source['guardian_reference'] ) ) : '',
			'password' => $is_google ? '' : ( isset( $source['password'] ) ? (string) wp_unslash( $source['password'] ) : '' ),
			'password_confirm' => $is_google ? '' : ( isset( $source['password_confirm'] ) ? (string) wp_unslash( $source['password_confirm'] ) : '' ),
			'terms' => ! empty( $source['terms'] ),
			'privacy' => ! empty( $source['privacy'] ),
			'ethics' => ! empty( $source['ethics'] ),
			'profile_photo_required' => ! empty( $source['profile_photo_required'] ),
			'authentication_method' => $is_google ? 'google' : 'password',
			'google_subject' => $is_google ? sanitize_text_field( (string) ( $google_context['sub'] ?? '' ) ) : '',
			'google_email_verified' => $is_google && ! empty( $google_context['email_verified'] ),
			'google_picture_candidate' => $is_google ? esc_url_raw( (string) ( $google_context['picture'] ?? '' ) ) : '',
			'ethical_conduct_version' => self::POLICY_VERSION,
			'terms_version' => self::POLICY_VERSION,
			'privacy_version' => self::POLICY_VERSION,
		);
	}

	private static function validate_registration( array $payload ) {
		$name = trim( (string) $payload['name'] );
		$email = (string) $payload['email'];
		$phone = trim( (string) $payload['phone'] );
		$country = trim( (string) $payload['country'] );
		$city = trim( (string) $payload['city'] );
		$address = trim( (string) $payload['address'] );
		$dob = trim( (string) $payload['date_of_birth'] );
		$sex = sanitize_key( (string) $payload['sex'] );
		$type = sanitize_key( (string) $payload['account_type'] );
		$identity_type = sanitize_key( (string) $payload['identity_type'] );
		$identity_reference = trim( (string) $payload['identity_reference'] );
		$guardian = trim( (string) $payload['guardian_reference'] );
		$is_google = 'google' === sanitize_key( (string) ( $payload['authentication_method'] ?? 'password' ) );
		$valid_name = function_exists( 'mb_strlen' ) ? mb_strlen( $name, 'UTF-8' ) : strlen( $name );
		$valid_country = function_exists( 'mb_strlen' ) ? mb_strlen( $country, 'UTF-8' ) : strlen( $country );
		$valid_city = function_exists( 'mb_strlen' ) ? mb_strlen( $city, 'UTF-8' ) : strlen( $city );
		$valid_address = function_exists( 'mb_strlen' ) ? mb_strlen( $address, 'UTF-8' ) : strlen( $address );
		$valid_identity = function_exists( 'mb_strlen' ) ? mb_strlen( $identity_reference, 'UTF-8' ) : strlen( $identity_reference );
		if ( $valid_name < 2 || $valid_name > 120 ) { return new WP_Error( 'registration_name_invalid', 'Enter your complete name.' ); }
		if ( ! is_email( $email ) ) { return new WP_Error( 'registration_email_invalid', 'Enter a valid email address.' ); }
		if ( ! preg_match( '/^\+[1-9][0-9]{7,14}$/', $phone ) ) { return new WP_Error( 'registration_phone_invalid', 'Enter a valid mobile number with country code, for example +923001234567.' ); }
		if ( ! isset( self::account_types()[ $type ] ) ) { return new WP_Error( 'registration_account_type_invalid', 'Select a valid declared account type.' ); }
		if ( $valid_country < 2 || $valid_country > 100 || $valid_city < 2 || $valid_city > 120 ) { return new WP_Error( 'registration_eligibility_invalid', 'Enter a valid country and city.' ); }
		if ( ! in_array( $sex, array( 'male', 'female' ), true ) ) { return new WP_Error( 'registration_gender_invalid', 'Select male or female for the platform age rule.' ); }
		$age = self::age_from_date( $dob );
		if ( null === $age ) { return new WP_Error( 'registration_eligibility_invalid', 'Enter a valid date of birth.' ); }
		$minimum = 'male' === $sex ? self::MIN_MALE_AGE : self::MIN_FEMALE_AGE;
		if ( $age < $minimum ) { return new WP_Error( 'registration_age_ineligible', 'This account does not meet the platform minimum age rule.' ); }
		if ( $age < 18 && strlen( $guardian ) < 3 ) { return new WP_Error( 'registration_guardian_required', 'A guardian reference is required for every minor account.' ); }
		if ( $valid_address < 5 || $valid_address > 500 || $valid_identity < 5 || $valid_identity > 120 ) { return new WP_Error( 'registration_identity_invalid', 'Complete the address and identity document reference.' ); }
		if ( ! in_array( $identity_type, array( 'national_id', 'passport' ), true ) ) { return new WP_Error( 'registration_identity_type_invalid', 'Select National ID or Passport.' ); }
		if ( ! $is_google ) {
			$password = (string) $payload['password'];
			$confirm = (string) $payload['password_confirm'];
			if ( strlen( $password ) < self::MIN_PASSWORD_LENGTH || strlen( $password ) > self::MAX_PASSWORD_BYTES || ! hash_equals( $password, $confirm ) ) { return new WP_Error( 'registration_password_invalid', 'Use matching passwords of at least 12 characters.' ); }
		} elseif ( empty( $payload['google_email_verified'] ) || strlen( (string) $payload['google_subject'] ) < 6 ) {
			return new WP_Error( 'registration_google_proof_invalid', 'The Google registration proof is invalid.' );
		}
		if ( empty( $payload['terms'] ) || empty( $payload['privacy'] ) || empty( $payload['ethics'] ) || empty( $payload['profile_photo_required'] ) ) { return new WP_Error( 'registration_consent_missing', 'Accept the Terms, Privacy Notice, profile-photo requirement and Ethical Conduct Charter to continue.' ); }
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
		return SA_Membership_Adapter::sign_in_allowed( $assertion, $completion );
	}

	private static function registration_subject_matches( $user_id, $expected_uuid ) {
		$expected_uuid = strtolower( (string) $expected_uuid );
		if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $expected_uuid ) ) { return false; }
		$assertion = SA_Membership_Adapter::membership_assertion( absint( $user_id ), 'clinical_identity_link', 'account_registration' );
		$live_uuid = strtolower( (string) ( $assertion['subject']['platform_uuid'] ?? '' ) );
		return 'smc.cf01.membership-assurance' === (string) ( $assertion['contract'] ?? '' )
			&& version_compare( (string) ( $assertion['contract_version'] ?? '' ), '1.1.0', '>=' )
			&& in_array( (string) ( $assertion['result'] ?? '' ), array( 'allow', 'deny' ), true )
			&& 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $live_uuid )
			&& hash_equals( $expected_uuid, $live_uuid );
	}

	private static function release_google_registration_locks( array &$locks, $user_id, $reason ) {
		if ( empty( $locks ) ) { return true; }
		$released = SA_Google_OAuth::release_link_locks( $locks, absint( $user_id ) );
		$locks = array();
		if ( ! $released && $user_id ) {
			SA_Google_OAuth::contain_linkage_failure( absint( $user_id ), sanitize_key( $reason ) );
		}
		return (bool) $released;
	}
}
