<?php

defined( 'ABSPATH' ) || exit;

/**
 * Converts internal authentication failure reason codes into safe, specific,
 * actionable user-facing notices without exposing private account ownership.
 *
 * File 00 remains the sole owner of membership and identity truth. This class
 * does not re-query or duplicate that truth: it observes the reason already
 * emitted by File 02 in the same request and rewrites only File 02's known
 * generic signed notice immediately before redirect.
 */
final class SAUTH_User_Error_Messages {
	private static $last_failure = array();

	public static function init() {
		add_action( 'sauth_event_recorded', array( __CLASS__, 'capture_failure' ), 10, 1 );
		add_filter( 'wp_redirect', array( __CLASS__, 'rewrite_failure_notice' ), 99, 2 );
	}

	public static function capture_failure( $event ) {
		if ( ! is_array( $event ) || 'AccountAuthenticationFailed.v1' !== (string) ( $event['event_name'] ?? '' ) ) {
			return;
		}
		$payload = isset( $event['payload'] ) && is_array( $event['payload'] ) ? $event['payload'] : array();
		$method  = sanitize_key( (string) ( $payload['method'] ?? '' ) );
		$reason  = sanitize_key( (string) ( $payload['reason'] ?? '' ) );
		if ( '' === $method || '' === $reason ) {
			return;
		}
		self::$last_failure = array( 'method' => $method, 'reason' => $reason );
	}

	public static function rewrite_failure_notice( $location, $status ) {
		if ( empty( self::$last_failure ) || ! is_string( $location ) || '' === $location || ! class_exists( 'SA_Security' ) ) {
			return $location;
		}

		$query = wp_parse_url( $location, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return $location;
		}
		$args = array();
		parse_str( $query, $args );
		if ( 'error' !== sanitize_key( (string) ( $args['sa_notice'] ?? '' ) ) ) {
			return $location;
		}

		$method  = (string) self::$last_failure['method'];
		$reason  = (string) self::$last_failure['reason'];
		$current = sanitize_text_field( (string) ( $args['sa_msg'] ?? '' ) );
		if ( ! self::is_generic_notice( $method, $current ) ) {
			return $location;
		}

		$message = self::message_for_failure( $method, $reason );
		if ( '' === $message ) {
			return $location;
		}

		self::$last_failure = array();
		$base = remove_query_arg( array( 'sa_notice', 'sa_msg', 'sa_iat', 'sa_sig' ), $location );
		return add_query_arg( SA_Security::notice_query_args( 'error', $message ), $base );
	}

	public static function message_for_failure( $method, $reason ) {
		$method = sanitize_key( (string) $method );
		$reason = sanitize_key( (string) $reason );

		if ( 'registration' === $method ) {
			$messages = array(
				'email_collision'                    => 'This email address cannot be used for a new account. If it is yours, sign in or use Account Recovery; otherwise use a different email address.',
				'phone_collision'                    => 'This mobile number cannot be used for a new account. If it is yours, sign in or use Account Recovery; otherwise use a different mobile number.',
				'identity_collision'                 => 'This identity document cannot be used for a new account. If it is yours, sign in or use Account Recovery; otherwise enter a different valid National ID or Passport.',
				'account_collision'                  => 'These registration details conflict with an existing account record. If they are yours, sign in or use Account Recovery; otherwise correct the account details and try again.',
				'registration_name_invalid'          => 'The complete name is invalid. Enter your full name and try again.',
				'registration_email_invalid'         => 'The email address is invalid. Enter a valid email address and try again.',
				'registration_phone_invalid'         => 'The mobile number is invalid. Enter a valid number with country code and try again.',
				'registration_password_invalid'      => 'The password is invalid. Use matching passwords of at least 12 characters.',
				'registration_gender_invalid'        => 'The selected sex value is invalid for the platform age rule. Select the correct value and try again.',
				'registration_eligibility_invalid'   => 'The date of birth or country could not be validated. Correct those fields and try again.',
				'registration_age_ineligible'        => 'This account does not meet the applicable minimum-age rule.',
				'registration_guardian_required'     => 'A verifiable guardian reference is required for this minor account.',
				'registration_identity_invalid'      => 'The address or identity-document reference is invalid. Correct the highlighted registration details and try again.',
				'registration_identity_type_invalid' => 'The identity-document type is invalid. Select National ID or Passport and try again.',
				'registration_consent_missing'       => 'Accept the current Terms, Privacy Notice and required conduct consent before creating the account.',
				'provider_safe_mode'                 => 'Account registration is temporarily paused for a security or maintenance check. No account was created; please try again later.',
				'provider_unavailable'               => 'Account registration is temporarily unavailable because the membership service cannot be reached. No account was created; please try again later.',
				'provider_exception'                 => 'Account registration stopped because the membership service returned an unexpected error. No account was created; please try again later or contact support.',
				'provider_contract_invalid'          => 'Account registration stopped because the membership verification service returned an invalid response. No account was created; please contact support if this continues.',
				'provider_subject_invalid'           => 'The account could not be completed because its membership identity could not be confirmed. No usable account was created; please contact support.',
				'identity_index_unavailable'         => 'Account registration is temporarily unavailable because secure identity matching cannot be completed. No account was created; please try again later.',
				'username_unavailable'               => 'A secure account username could not be created from these details. Try a different email address or contact support.',
				'wordpress_account_create_failed'    => 'The WordPress account could not be created. No completed account was created; please try again or contact support.',
				'registration_encryption_failed'     => 'Account registration stopped because sensitive identity details could not be stored securely. The account was protected from use; please contact support.',
				'membership_application_missing'     => 'The account could not be connected to its required membership application. The account was protected from use; please contact support.',
				'registration_initialization_failed' => 'The account could not finish its required security and membership setup. The account was protected from use; please contact support.',
				'idempotency_key_invalid'            => 'This registration request could not be safely processed. Refresh the registration page and submit the form again.',
			);
			return isset( $messages[ $reason ] ) ? $messages[ $reason ] : 'Registration could not be completed because the membership service rejected or could not verify the submitted details. Review the form and try again; if the problem continues, contact support.';
		}

		if ( 'password' === $method ) {
			$messages = array(
				'invalid_request'                   => 'Enter a valid email or username and password, then try again.',
				'rate_limited'                      => 'Too many sign-in attempts were made. Wait about 15 minutes, then try again or use Account Recovery.',
				'credentials_invalid'               => 'The email or username and password combination is incorrect. Check both and try again, or use Account Recovery.',
				'membership_provider_circuit_open' => 'Sign-in is temporarily unavailable because account-verification services are unavailable. Please try again later.',
				'membership_not_eligible'           => 'Your credentials were accepted, but this account is not currently eligible to sign in. Complete the required account verification or status review, or contact support.',
				'passkey_step_up_required'          => 'This sign-in requires an additional passkey security check. Continue with your passkey on a trusted device.',
			);
			return isset( $messages[ $reason ] ) ? $messages[ $reason ] : 'Sign-in could not be completed. Check your credentials and required account verification, then try again or contact support.';
		}

		return '';
	}

	private static function is_generic_notice( $method, $message ) {
		if ( 'registration' === $method ) {
			return 'Registration could not be completed. The details may already belong to an account, or the membership service may require review.' === $message;
		}
		if ( 'password' === $method ) {
			return 'The sign-in details were not accepted. Check your credentials or complete account verification.' === $message;
		}
		return false;
	}
}
