<?php
/**
 * No-network checks for the File 02 v1.0 harmonization boundaries.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SA_VERSION', '0.4.0' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'SMC_AUTHENTICATION_CONTRACT_VERSION', '1.0.0' );

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) {
		$this->code = $code;
		$this->message = $message;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ) { return strtolower( filter_var( (string) $value, FILTER_SANITIZE_EMAIL ) ); }
function wp_generate_uuid4() { return '11111111-2222-4333-8444-555555555555'; }

final class SA_Security {
	public static function client_fingerprint() { return str_repeat( 'a', 64 ); }
	public static function safe_redirect( $url, $fallback = '' ) {
		return 0 === strpos( (string) $url, 'https://example.test/' ) ? $url : $fallback;
	}
}

final class SMC_Authentication_Contract {
	public static function register_account( $payload, $context ) {
		return array(
			'contract' => 'smc.authentication-account',
			'contract_version' => '1.0.0',
			'result' => 'allow',
			'reason_code' => 'registration_created',
			'user_id' => 19,
			'subject_uuid' => '11111111-1111-4111-8111-111111111111',
		);
	}
	public static function mark_email_verified( $user_id, $email, $context ) {
		return array(
			'contract' => 'smc.authentication-account',
			'contract_version' => '1.0.0',
			'result' => 'allow',
			'reason_code' => 'email_verified',
			'user_id' => $user_id,
		);
	}
	public static function get_completion_state( $user_id, $context ) {
		return array(
			'contract' => 'smc.authentication-account',
			'contract_version' => '1.0.0',
			'result' => 'allow',
			'reason_code' => 'completion_required',
			'missing_steps' => array( 'guardian', 'profile', 'guardian' ),
			'next_route' => 'https://example.test/complete-profile/',
		);
	}
}

function sauth_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-sauth-account-contract.php';
require_once dirname( __DIR__ ) . '/includes/class-sauth-event-outbox.php';

sauth_test_assert( SAUTH_Account_Contract::provider_available(), 'valid File 00 account provider was not detected' );
$registration = SAUTH_Account_Contract::register_account(
	array(
		'name' => 'Test Member',
		'email' => 'MEMBER@example.test',
		'phone' => '+92 300 0000000',
		'password' => 'not-returned-to-caller',
		'sex' => 'male',
		'date_of_birth' => '2000-01-01',
		'address' => 'Gujrat',
	),
	array( 'idempotency_key' => 'registration-1' )
);
sauth_test_assert( 'allow' === $registration['result'], 'valid registration provider result was rejected' );
sauth_test_assert( 19 === $registration['user_id'], 'provider user ID was not normalized' );
sauth_test_assert( ! array_key_exists( 'password', $registration ), 'password leaked into public registration result' );

$completion = SAUTH_Account_Contract::completion_state( 19 );
sauth_test_assert( array( 'guardian', 'profile' ) === $completion['missing_steps'], 'completion steps were not normalized and deduplicated' );
sauth_test_assert( 'https://example.test/complete-profile/' === $completion['next_route'], 'safe completion route was rejected' );

$event = SAUTH_Event_Outbox::build_envelope(
	'AccountAuthenticationSucceeded.v1',
	19,
	19,
	array(
		'method' => 'password',
		'password' => 'must-not-appear',
		'password_digest' => 'must-not-appear',
		'reset_token_hash' => 'must-not-appear',
		'nested' => array( 'token' => 'must-not-appear', 'session_verifier_hash' => 'must-not-appear', 'result' => 'success' ),
	),
	'security',
	'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'
);
sauth_test_assert( ! is_wp_error( $event ), 'valid event was rejected' );
sauth_test_assert( ! isset( $event['payload']['password'] ), 'password leaked into event payload' );
sauth_test_assert( ! isset( $event['payload']['password_digest'] ), 'password derivative leaked into event payload' );
sauth_test_assert( ! isset( $event['payload']['reset_token_hash'] ), 'reset-token derivative leaked into event payload' );
sauth_test_assert( ! isset( $event['payload']['nested']['token'] ), 'nested token leaked into event payload' );
sauth_test_assert( ! isset( $event['payload']['nested']['session_verifier_hash'] ), 'session verifier leaked into event payload' );
sauth_test_assert( 'success' === $event['payload']['nested']['result'], 'safe nested event payload was removed' );
sauth_test_assert( '0.4.0' === $event['producer_version'], 'producer version is not bound to event' );

$invalid = SAUTH_Event_Outbox::build_envelope( 'UnknownEvent.v1', 0, 0 );
sauth_test_assert( is_wp_error( $invalid ), 'unsupported event name was accepted' );

echo "File 02 plan harmonization unit checks passed.\n";
