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
function is_wp_error( $value ) { return $value instanceof WP_Error; }

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

function sauth_test_cbor_head( $major, $length ) {
	$length = (int) $length;
	if ( $length < 24 ) {
		return chr( ( $major << 5 ) | $length );
	}
	if ( $length <= 0xff ) {
		return chr( ( $major << 5 ) | 24 ) . chr( $length );
	}
	if ( $length <= 0xffff ) {
		return chr( ( $major << 5 ) | 25 ) . pack( 'n', $length );
	}
	return chr( ( $major << 5 ) | 26 ) . pack( 'N', $length );
}

function sauth_test_cbor_int( $value ) {
	$value = (int) $value;
	return $value >= 0 ? sauth_test_cbor_head( 0, $value ) : sauth_test_cbor_head( 1, -1 - $value );
}
function sauth_test_cbor_bytes( $value ) { return sauth_test_cbor_head( 2, strlen( $value ) ) . $value; }
function sauth_test_cbor_text( $value ) { return sauth_test_cbor_head( 3, strlen( $value ) ) . $value; }
function sauth_test_cbor_map( array $pairs ) {
	$out = sauth_test_cbor_head( 5, count( $pairs ) );
	foreach ( $pairs as $pair ) {
		$out .= $pair[0] . $pair[1];
	}
	return $out;
}

$binary = "\x00\x01\x02\xFA\xFB\xFCpasskey";
$encoded = SAUTH_Passkeys::base64url_encode( $binary );
sauth_pk_assert( false !== SAUTH_Passkeys::base64url_decode( $encoded ), 'base64url decode accepted canonical value' );
sauth_pk_assert( hash_equals( $binary, SAUTH_Passkeys::base64url_decode( $encoded ) ), 'base64url round trip' );
sauth_pk_assert( false === SAUTH_Passkeys::base64url_decode( '../bad+' ), 'base64url rejects non-canonical characters' );

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

$invalid_backup = $rp_hash . chr( 0x15 ) . pack( 'N', 1 );
sauth_pk_assert( SAUTH_Passkeys::parse_authenticator_data( $invalid_backup, false ) instanceof WP_Error, 'backup-state without backup-eligibility rejected' );

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
sauth_pk_assert( is_array( $details ) && ! empty( $details['key'] ) && ! empty( $details['ec']['x'] ) && ! empty( $details['ec']['y'] ), 'test EC public coordinates obtained' );

$cose = sauth_test_cbor_map(
	array(
		array( sauth_test_cbor_int( 1 ), sauth_test_cbor_int( 2 ) ),
		array( sauth_test_cbor_int( 3 ), sauth_test_cbor_int( -7 ) ),
		array( sauth_test_cbor_int( -1 ), sauth_test_cbor_int( 1 ) ),
		array( sauth_test_cbor_int( -2 ), sauth_test_cbor_bytes( $details['ec']['x'] ) ),
		array( sauth_test_cbor_int( -3 ), sauth_test_cbor_bytes( $details['ec']['y'] ) ),
	)
);
$credential_id = random_bytes( 32 );
$registration_auth_data = $rp_hash . chr( 0x45 ) . pack( 'N', 0 ) . str_repeat( "\x00", 16 ) . pack( 'n', strlen( $credential_id ) ) . $credential_id . $cose;
$registration = SAUTH_Passkeys::parse_authenticator_data( $registration_auth_data, true );
sauth_pk_assert( is_array( $registration ), 'registration authenticatorData with COSE key parsed' );
sauth_pk_assert( hash_equals( $credential_id, $registration['credential_id'] ), 'credential ID extracted exactly' );
sauth_pk_assert( is_array( $registration['credential_public_key'] ), 'COSE public key retained from authenticatorData' );

$converted = SAUTH_Passkeys::cose_public_key_to_pem( $registration['credential_public_key'] );
sauth_pk_assert( is_array( $converted ) && -7 === $converted['algorithm'], 'COSE ES256 converted to server-side SPKI' );
sauth_pk_assert( false !== openssl_pkey_get_public( $converted['pem'] ), 'server-derived SPKI is OpenSSL-readable' );

$attestation = sauth_test_cbor_map(
	array(
		array( sauth_test_cbor_text( 'fmt' ), sauth_test_cbor_text( 'none' ) ),
		array( sauth_test_cbor_text( 'authData' ), sauth_test_cbor_bytes( $registration_auth_data ) ),
		array( sauth_test_cbor_text( 'attStmt' ), sauth_test_cbor_map( array() ) ),
	)
);
$attested = SAUTH_Passkeys::parse_attestation_object( $attestation );
sauth_pk_assert( is_array( $attested ) && 'none' === $attested['fmt'], 'attestation=none object accepted and parsed' );
sauth_pk_assert( hash_equals( $registration_auth_data, $attested['auth_data'] ), 'attestation authData preserved exactly' );
sauth_pk_assert( SAUTH_Passkeys::parse_attestation_object( $attestation . "\x00" ) instanceof WP_Error, 'trailing CBOR bytes rejected' );
sauth_pk_assert( SAUTH_Passkeys::parse_attestation_object( "\xBF" ) instanceof WP_Error, 'indefinite-length CBOR rejected' );

$signed_data = $auth_data . hash( 'sha256', $client_json, true );
$signature = '';
sauth_pk_assert( openssl_sign( $signed_data, $signature, $key, OPENSSL_ALGO_SHA256 ), 'test assertion signed' );
sauth_pk_assert( SAUTH_Passkeys::verify_signature( $signed_data, $signature, $converted['pem'], -7 ), 'server-derived ES256 key verifies assertion signature' );
sauth_pk_assert( ! SAUTH_Passkeys::verify_signature( $signed_data . 'x', $signature, $converted['pem'], -7 ), 'modified signed data rejected' );
sauth_pk_assert( ! SAUTH_Passkeys::verify_signature( $signed_data, $signature, $converted['pem'], -8 ), 'unapproved algorithm rejected' );

fwrite( STDOUT, "File 02 WebAuthn unit suite passed: {$passed} assertions.\n" );
