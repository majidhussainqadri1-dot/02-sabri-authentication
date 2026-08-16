<?php
/**
 * Minimal no-network unit checks for File 02 cryptography and Google claim rules.
 * This file supplies only the WordPress functions required by the tested methods.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'SA_MASTER_KEY', 'file02-unit-test-dedicated-master-key-2026-08-14' );

$GLOBALS['sa_test_options'] = array(
	'sa_google_client_id' => '123456789.apps.googleusercontent.com',
);
$GLOBALS['sa_test_meta'] = array();

class WP_User {
	public $ID;
	public $user_email;
	public function __construct( $user_id, $email ) {
		$this->ID = (int) $user_id;
		$this->user_email = (string) $email;
	}
}

function wp_salt( $scheme = 'auth' ) {
	return hash( 'sha256', 'file02-test-salt|' . $scheme );
}
function home_url( $path = '/' ) {
	return 'https://example.test' . ( '/' === $path ? '/' : $path );
}
function wp_validate_redirect( $url, $fallback = '' ) {
	return 0 === strpos( (string) $url, 'https://example.test/' ) ? $url : $fallback;
}
function apply_filters( $hook, $value ) {
	return $value;
}
function add_query_arg( $args, $url ) {
	$args = is_array( $args ) ? $args : array();
	$query = http_build_query( $args );
	if ( '' === $query ) { return $url; }
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query;
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
	return substr( str_repeat( 'aB3', (int) ceil( $length / 3 ) ), 0, $length );
}
function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['sa_test_options'] ) ? $GLOBALS['sa_test_options'][ $name ] : $default;
}
function get_user_meta( $user_id, $key, $single = false ) {
	return isset( $GLOBALS['sa_test_meta'][ $user_id ][ $key ] ) ? $GLOBALS['sa_test_meta'][ $user_id ][ $key ] : '';
}
function get_userdata( $user_id ) {
	return 7 === (int) $user_id ? new WP_User( 7, 'member@example.test' ) : false;
}
function is_email( $email ) {
	return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function sa_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-sa-security.php';
require_once dirname( __DIR__ ) . '/includes/class-sa-google-oauth.php';

sa_test_assert( SA_Security::master_key_ready(), 'dedicated File 02 master key was not recognized' );
$plain  = 'unit-test-google-client-secret';
$cipher = SA_Security::encrypt( $plain );
sa_test_assert( 0 === strpos( $cipher, 'v3:' ), 'encrypted value must use dedicated-key v3 envelope' );
sa_test_assert( $plain === SA_Security::decrypt( $cipher ), 'AES-256-GCM dedicated-key round trip failed' );
sa_test_assert( SA_Security::current_cipher_ready( $cipher ), 'v3 dedicated-key ciphertext was not accepted as current' );
$last = substr( $cipher, -1 );
$tampered = substr( $cipher, 0, -1 ) . ( 'A' === $last ? 'B' : 'A' );
sa_test_assert( '' === SA_Security::decrypt( $tampered ), 'tampered ciphertext must fail authentication' );
sa_test_assert( strlen( SA_Security::random_token( 32 ) ) >= 64, 'random token is unexpectedly short' );
sa_test_assert( 'https://example.test/member' === SA_Security::safe_redirect( 'https://example.test/member' ), 'valid local redirect rejected' );
sa_test_assert( 'https://example.test/' === SA_Security::safe_redirect( 'https://attacker.test/path' ), 'external redirect was not rejected' );

$notice = SA_Security::message_url( 'login', 'success', 'Verified server notice' );
parse_str( (string) parse_url( $notice, PHP_URL_QUERY ), $notice_args );
sa_test_assert( isset( $notice_args['sa_sig'] ), 'server notice signature missing' );
sa_test_assert( SA_Security::notice_valid( $notice_args['sa_notice'] ?? '', $notice_args['sa_msg'] ?? '', $notice_args['sa_sig'] ?? '', $notice_args['sa_iat'] ?? 0 ), 'valid server notice signature was rejected' );
sa_test_assert( ! SA_Security::notice_valid( 'success', 'Forged success', $notice_args['sa_sig'] ?? '', $notice_args['sa_iat'] ?? 0 ), 'forged success notice was accepted' );

$oauth  = new SA_Google_OAuth();
$method = new ReflectionMethod( 'SA_Google_OAuth', 'valid_claims' );
$method->setAccessible( true );
$now = time();
$data = array( 'nonce' => 'nonce-123' );
$claims = array(
	'iss'            => 'https://accounts.google.com',
	'aud'            => '123456789.apps.googleusercontent.com',
	'sub'            => 'google-subject-123',
	'email'          => 'member@example.test',
	'email_verified' => 'true',
	'nonce'          => 'nonce-123',
	'iat'            => $now,
	'exp'            => $now + 300,
);
sa_test_assert( true === $method->invoke( $oauth, $claims, $data ), 'valid Google claims were rejected' );

$bad = $claims;
$bad['nonce'] = 'wrong';
sa_test_assert( false === $method->invoke( $oauth, $bad, $data ), 'bad nonce was accepted' );
$bad = $claims;
$bad['aud'] = 'other-client.apps.googleusercontent.com';
sa_test_assert( false === $method->invoke( $oauth, $bad, $data ), 'bad audience was accepted' );
$bad = $claims;
$bad['iss'] = 'https://issuer.invalid';
sa_test_assert( false === $method->invoke( $oauth, $bad, $data ), 'bad issuer was accepted' );
$bad = $claims;
$bad['exp'] = $now - 1;
sa_test_assert( false === $method->invoke( $oauth, $bad, $data ), 'expired identity token was accepted' );
$bad = $claims;
$bad['iat'] = $now - 601;
sa_test_assert( false === $method->invoke( $oauth, $bad, $data ), 'stale identity token was accepted' );
$multiple = $claims;
$multiple['aud'] = array( '123456789.apps.googleusercontent.com', 'secondary.apps.googleusercontent.com' );
sa_test_assert( false === $method->invoke( $oauth, $multiple, $data ), 'multi-audience token without azp was accepted' );
$multiple['azp'] = '123456789.apps.googleusercontent.com';
sa_test_assert( true === $method->invoke( $oauth, $multiple, $data ), 'valid azp was rejected for multi-audience token' );

$GLOBALS['sa_test_meta'][7] = array(
	'_sa_google_account'        => '1',
	'_sa_google_email_verified' => '1',
	'_sa_google_link_version'   => '2',
	'_sa_google_sub'            => 'google-subject-123',
	'_sa_google_email'          => 'member@example.test',
);
sa_test_assert( true === SA_Google_OAuth::explicitly_linked( 7 ), 'complete explicit link was not recognized' );
$GLOBALS['sa_test_meta'][7]['_sa_google_link_version'] = '1';
sa_test_assert( false === SA_Google_OAuth::explicitly_linked( 7 ), 'legacy link was accepted as explicit' );

echo "File 02 security unit checks passed.\n";
