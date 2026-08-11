<?php
/**
 * No-network policy checks for F02-FR-001 through F02-FR-003 and the
 * Definitive Master Plan registration constitution.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function is_email( $email ) { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function wp_salt( $scheme = 'auth' ) { return hash( 'sha256', 'file02-registration-test|' . $scheme ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }

function sauth_registration_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-sa-registration.php';
require_once dirname( __DIR__ ) . '/includes/class-sauth-email-verification.php';

$adult = array(
	'name'                     => 'Test Member',
	'email'                    => 'member@example.test',
	'phone'                    => '+92 300 0000000',
	'password'                 => 'StrongPassword123',
	'password_confirm'         => 'StrongPassword123',
	'authentication_method'    => 'password',
	'google_subject'           => '',
	'google_email_verified'    => false,
	'google_picture_candidate' => '',
	'sex'                      => 'male',
	'date_of_birth'            => '2000-01-01',
	'address'                  => 'Street 1, Gujrat',
	'city'                     => 'Gujrat',
	'country'                  => 'Pakistan',
	'account_type'             => 'member',
	'identity_type'            => 'national_id',
	'identity_reference'       => 'ID-REFERENCE',
	'guardian_reference'       => '',
	'profile_photo_required'   => true,
	'terms_version'            => '2026-08-06',
	'privacy_version'          => '2026-08-06',
	'ethical_conduct_version'  => '2026-08-06',
);
sauth_registration_assert( true === SA_Registration::validate_registration( $adult ), 'valid adult registration was rejected' );

$passport = $adult;
$passport['identity_type'] = 'passport';
sauth_registration_assert( true === SA_Registration::validate_registration( $passport ), 'passport registration was rejected' );

$google = $adult;
$google['password'] = '';
$google['password_confirm'] = '';
$google['authentication_method'] = 'google';
$google['google_subject'] = 'google-subject-123456789';
$google['google_email_verified'] = true;
sauth_registration_assert( true === SA_Registration::validate_registration( $google ), 'valid Google-first registration was rejected' );
$unverified_google = $google;
$unverified_google['google_email_verified'] = false;
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $unverified_google ) ), 'unverified Google email was accepted' );

$unsupported_identity = $adult;
$unsupported_identity['identity_type'] = 'driver_license';
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $unsupported_identity ) ), 'unsupported identity type was accepted' );

$minor = $adult;
$minor['sex'] = 'female';
$minor['date_of_birth'] = ( new DateTimeImmutable( 'today - 13 years' ) )->format( 'Y-m-d' );
$minor['account_type'] = 'student';
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $minor ) ), 'minor without guardian reference was accepted' );
$minor['guardian_reference'] = 'GUARDIAN-REFERENCE';
sauth_registration_assert( true === SA_Registration::validate_registration( $minor ), 'eligible minor with guardian reference was rejected' );

$minor_professional = $minor;
$minor_professional['account_type'] = 'doctor';
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $minor_professional ) ), 'minor professional declaration was accepted' );

$too_young = $adult;
$too_young['date_of_birth'] = ( new DateTimeImmutable( 'today - 14 years' ) )->format( 'Y-m-d' );
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $too_young ) ), 'male below platform minimum age was accepted' );

$bad_password = $adult;
$bad_password['password'] = 'short';
$bad_password['password_confirm'] = 'short';
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $bad_password ) ), 'short password was accepted' );

$mismatch = $adult;
$mismatch['password_confirm'] = 'DifferentPassword123';
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $mismatch ) ), 'mismatched passwords were accepted' );

$missing_identity = $adult;
$missing_identity['identity_reference'] = '';
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $missing_identity ) ), 'missing identity handoff was accepted' );

$missing_city = $adult;
$missing_city['city'] = '';
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $missing_city ) ), 'missing city was accepted' );

$missing_account_type = $adult;
$missing_account_type['account_type'] = '';
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $missing_account_type ) ), 'missing account type was accepted' );

$privileged_account_type = $adult;
$privileged_account_type['account_type'] = 'administrator';
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $privileged_account_type ) ), 'unapproved privileged account type was accepted' );

$missing_photo = $adult;
$missing_photo['profile_photo_required'] = false;
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $missing_photo ) ), 'missing profile-photo completion acknowledgement was accepted' );

$missing_ethics = $adult;
$missing_ethics['ethical_conduct_version'] = '';
sauth_registration_assert( is_wp_error( SA_Registration::validate_registration( $missing_ethics ) ), 'missing Ethical Conduct consent was accepted' );

$token = str_repeat( 'a', 64 );
$hash1 = SAUTH_Email_Verification::token_hash( $token );
$hash2 = SAUTH_Email_Verification::token_hash( $token );
$hash3 = SAUTH_Email_Verification::token_hash( str_repeat( 'b', 64 ) );
sauth_registration_assert( 64 === strlen( $hash1 ), 'verification token hash is not SHA-256 length' );
sauth_registration_assert( hash_equals( $hash1, $hash2 ), 'verification token hashing is not deterministic for verification' );
sauth_registration_assert( ! hash_equals( $hash1, $hash3 ), 'different verification tokens produced the same hash' );

$policy = new ReflectionMethod( 'SA_Registration', 'sign_in_allowed' );
$policy->setAccessible( true );
$allow_assertion = array( 'result' => 'allow', 'membership' => array( 'suspended' => false ) );
$deny_assertion = array( 'result' => 'deny', 'membership' => array( 'suspended' => false ) );
$suspended_assertion = array( 'result' => 'allow', 'membership' => array( 'suspended' => true ) );
$completion = array( 'result' => 'allow', 'missing_steps' => array( 'email' ), 'next_route' => 'https://example.test/verify-email/' );
sauth_registration_assert( true === $policy->invoke( null, $allow_assertion, array() ), 'active membership assertion was denied' );
sauth_registration_assert( true === $policy->invoke( null, $deny_assertion, $completion ), 'safe completion-only sign-in was denied' );
sauth_registration_assert( false === $policy->invoke( null, $suspended_assertion, $completion ), 'suspended membership was allowed to sign in' );
sauth_registration_assert( false === $policy->invoke( null, array( 'result' => 'unknown' ), $completion ), 'unknown membership provider was allowed to sign in' );

echo "File 02 registration, email verification, Google-first and password policy checks passed.\n";
