<?php
/**
 * No-network pure WebAuthn tests for File 02 passkey ceremony.
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code = $code;
		$this->message = $message;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

function home_url( $path = '/' ) {
	return 'https://sabrihomeopathy.test' . ( '/' === $path ? '/' : $path );
}
function wp_parse_url( $url ) { return parse_url( $url ); }
function absint( $value ) { return abs( (int) $value ); }

require dirname( __DIR__ ) . '/includes/class-sauth-passkeys.php';

$passed = 0;
function sauth_pk_assert( $condition, $label ) {
	global $passed;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$label}\n" );
		exit( 1 );
	}
	$passed++;
}

$binary = "\x00\x01\x02\xFA\xFB\xFCpasskey";
$encoded = SAUTH_Passkeys::base64url_encode( $binary );
sauth_pk_assert( false !== SAUTH_Passkeys::base64url_decode( $encoded ), 'base64url decode accepted canonical value' );
sauth_pk_assert( hash_equals( $binary, SAUTH_Passkeys::base64url_decode( $encoded ) ), 'base64url round trip' );
sauth_pk_assert( false === SAUTH_Passkeys::base64url_decode( '../bad+' ), 'base64url rejects non canonical characters' );

$challenge = SAUTH_Passkeys::base64url_encode( random_bytes( 32 ) );
$client_json = json_encode(
	array(
		'type' => 'webauthn.get',
		'challenge' => $challenge,
		'origin' => 'https://sabrihomeopathy.test',
		'crossOrigin' => false,
	)
);
$client = SAUTH_Passkeys::validate_client_data( $client_json, 'webauthn.get', $challenge );
sauth_pk_assert( is_array( $client ), 'valid clientData accepted' );

$wrong_origin = json_encode(
	array(
		'type' => 'webauthn.get',
		'challenge' => $challenge,
		'origin' => 'https://evil.example',
		'crossOrigin' => false,
	)
);
sauth_pk_assert( SAUTH_Passkeys::validate_client_data( $wrong_origin, 'webauthn.get', $challenge ) instanceof WP_Error, 'wrong origin rejected' );

$replayed = json_encode(
	array(
		'type' => 'webauthn.get',
		'challenge' => SAUTH_Passkeys::base64url_encode( random_bytes( 32 ) ),
		'origin' => 'https://sabrihomeopathy.test',
		'crossOrigin' => false,
	)
);
sauth_pk_assert( SAUTH_Passkeys::validate_client_data( $replayed, 'webauthn.get', $challenge ) instanceof WP_Error, 'wrong/replayed challenge rejected' );

$cross_origin = json_encode(
	array(
		'type' => 'webauthn.get',
		'challenge' => $challenge,
		'origin' => 'https://sabrihomeopathy.test',
		'crossOrigin' => true,
	)
);
sauth_pk_assert( SAUTH_Passkeys::validate_client_data( $cross_origin, 'webauthn.get', $challenge ) instanceof WP_Error, 'cross-origin assertion rejected' );

$rp_hash = hash( 'sha256', 'sabrihomeopathy.test', true );
$auth_data = $rp_hash . chr( 0x05 ) . pack( 'N', 7 );
$parsed = SAUTH_Passkeys::parse_authenticator_data( $auth_data, false );
sauth_pk_assert( is_array( $parsed ), 'assertion authenticatorData parsed' );
sauth_pk_assert( ! empty( $parsed['user_present'] ) && ! empty( $parsed['user_verified'] ), 'UP and UV flags required/visible' );
sauth_pk_assert( 7 === $parsed['sign_count'], 'signature counter parsed' );

$weak_auth_data = $rp_hash . chr( 0x01 ) . pack( 'N', 1 );
$weak = SAUTH_Passkeys::parse_authenticator_data( $weak_auth_data, false );
sauth_pk_assert( is_array( $weak ) && empty( $weak['user_verified'] ), 'UV absence is detectable for fail-closed caller' );

$credential_id = random_bytes( 32 );
$registration_auth_data = $rp_hash . chr( 0x4D ) . pack( 'N', 0 ) . str_repeat( "\x00", 16 ) . pack( 'n', strlen( $credential_id ) ) . $credential_id . "\xA0";
$registration = SAUTH_Passkeys::parse_authenticator_data( $registration_auth_data, true );
sauth_pk_assert( is_array( $registration ), 'registration authenticatorData parsed' );
sauth_pk_assert( hash_equals( $credential_id, $registration['credential_id'] ), 'credential ID extracted exactly' );
sauth_pk_assert( ! empty( $registration['backup_eligible'] ), 'backup-eligible flag parsed' );

if ( ! function_exists( 'openssl_pkey_new' ) || ! defined( 'OPENSSL_KEYTYPE_EC' ) ) {
	fwrite( STDERR, "FAIL: OpenSSL EC support is required for passkey tests.\n" );
	exit( 1 );
}
$key = openssl_pkey_new(
	array(
		'private_key_type' => OPENSSL_KEYTYPE_EC,
		'curve_name' => 'prime256v1',
	)
);
sauth_pk_assert( false !== $key, 'test EC key generated' );
$details = openssl_pkey_get_details( $key );
sauth_pk_assert( is_array( $details ) && ! empty( $details['key'] ), 'test public key obtained' );
$signed_data = $auth_data . hash( 'sha256', $client_json, true );
$signature = '';
sauth_pk_assert( openssl_sign( $signed_data, $signature, $key, OPENSSL_ALGO_SHA256 ), 'test assertion signed' );
sauth_pk_assert( SAUTH_Passkeys::verify_signature( $signed_data, $signature, $details['key'], -7 ), 'ES256 signature accepted' );
sauth_pk_assert( ! SAUTH_Passkeys::verify_signature( $signed_data . 'x', $signature, $details['key'], -7 ), 'modified signed data rejected' );
sauth_pk_assert( ! SAUTH_Passkeys::verify_signature( $signed_data, $signature, $details['key'], -8 ), 'unapproved algorithm rejected' );

fwrite( STDOUT, "File 02 WebAuthn unit suite passed: {$passed} assertions.\n" );
