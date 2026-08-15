<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'SAUTH_VERSION', '1.2.1' );
class WP_User { public $ID; public $user_pass; public function __construct( $id, $hash ) { $this->ID = $id; $this->user_pass = $hash; } }

$GLOBALS['sa_current_user'] = 7;
$GLOBALS['sa_logged_in'] = true;
$GLOBALS['sa_session_token'] = 'session-token';
$GLOBALS['sa_password_ok'] = true;
$GLOBALS['sa_user_pass'] = 'stored-hash';
$GLOBALS['sa_auth_result'] = array();
$GLOBALS['sa_transients'] = array();
$GLOBALS['sa_options'] = array();
$GLOBALS['sa_fingerprint'] = 'fingerprint-a';

function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function is_user_logged_in() { return (bool) $GLOBALS['sa_logged_in']; }
function get_current_user_id() { return (int) $GLOBALS['sa_current_user']; }
function wp_get_session_token() { return (string) $GLOBALS['sa_session_token']; }
function get_userdata( $user_id ) { return 7 === (int) $user_id ? new WP_User( 7, (string) $GLOBALS['sa_user_pass'] ) : false; }
function wp_check_password( $password, $hash, $user_id ) { return (bool) $GLOBALS['sa_password_ok'] && (string) $GLOBALS['sa_user_pass'] === (string) $hash && 7 === (int) $user_id; }
function wp_generate_uuid4() { return '123e4567-e89b-42d3-a456-426614174000'; }
function wp_salt( $scheme = 'auth' ) { return 'unit-test-' . $scheme; }
function add_action() { return true; }
function do_action_ref_array( $hook, $args ) { return null; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['sa_transients'][ $key ] = array( 'value' => $value, 'expires' => time() + max( 1, (int) $ttl ) ); return true; }
function get_transient( $key ) { if ( ! isset( $GLOBALS['sa_transients'][ $key ] ) ) { return false; } if ( $GLOBALS['sa_transients'][ $key ]['expires'] <= time() ) { unset( $GLOBALS['sa_transients'][ $key ] ); return false; } return $GLOBALS['sa_transients'][ $key ]['value']; }
function delete_transient( $key ) { unset( $GLOBALS['sa_transients'][ $key ] ); return true; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['sa_options'][ $key ] = $value; return true; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['sa_options'] ) ? $GLOBALS['sa_options'][ $key ] : $default; }
function delete_option( $key ) { unset( $GLOBALS['sa_options'][ $key ] ); return true; }

final class SA_Security {
	public static function client_fingerprint() { return (string) $GLOBALS['sa_fingerprint']; }
}

final class SA_Authentication_Assurance {
	public static function verify_and_record( $user_id, $code, $context ) {
		return $GLOBALS['sa_auth_result'];
	}
	public static function assertion( $user_id, $purpose, $scope ) {
		return $GLOBALS['sa_auth_result'];
	}
}

require dirname( __DIR__ ) . '/includes/class-sa-professional-reauthentication.php';

$tests = 0;
function sa_prof_assert( $condition, $message ) {
	global $tests;
	++$tests;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$scope = 'file09:reviewer-session:7';
$scope_hash = hash_hmac( 'sha256', $scope, wp_salt( 'nonce' ) );
$valid = array(
	'contract' => 'sa.cf01.authentication-assurance',
	'contract_version' => '1.0.0',
	'purpose' => 'clinical_sign_in',
	'subject_uuid' => '123e4567-e89b-42d3-a456-426614174000',
	'scope_hash' => $scope_hash,
	'method' => 'webauthn_passkey',
	'assurance_level' => 'aal2',
	'verified_at' => gmdate( 'c', time() - 5 ),
	'expires_at' => gmdate( 'c', time() + 300 ),
	'trace_id' => '123e4567-e89b-42d3-a456-426614174000',
	'result' => 'valid',
	'reason_code' => 'authentication_assurance_valid',
);
$GLOBALS['sa_auth_result'] = $valid;

$result = SA_Professional_Reauthentication::verify_and_record( 8, 'password', 'ignored', array( 'scope' => $scope ) );
sa_prof_assert( 'invalid' === $result['result'] && 'current_authenticated_session_required' === $result['reason_code'], 'different subject cannot bind current session' );

$GLOBALS['sa_password_ok'] = false;
$result = SA_Professional_Reauthentication::verify_and_record( 7, 'wrong', 'ignored', array( 'scope' => $scope ) );
sa_prof_assert( 'invalid' === $result['result'] && 'current_password_invalid' === $result['reason_code'], 'wrong current password fails closed' );

$GLOBALS['sa_password_ok'] = true;
$GLOBALS['sa_auth_result'] = array( 'contract' => 'wrong', 'contract_version' => '1.0.0', 'result' => 'valid' );
$result = SA_Professional_Reauthentication::verify_and_record( 7, 'password', 'ignored', array( 'scope' => $scope ) );
sa_prof_assert( 'unknown' === $result['result'] && 'authentication_contract_invalid' === $result['reason_code'], 'invalid downstream contract fails closed' );

$tampered = $valid;
$tampered['purpose'] = 'authentication_sign_in';
$GLOBALS['sa_auth_result'] = $tampered;
$result = SA_Professional_Reauthentication::verify_and_record( 7, 'password', 'ignored', array( 'scope' => $scope ) );
sa_prof_assert( 'unknown' === $result['result'] && 'authentication_evidence_invalid' === $result['reason_code'], 'provider purpose tampering fails closed' );

$tampered = $valid;
$tampered['scope_hash'] = str_repeat( '0', 64 );
$GLOBALS['sa_auth_result'] = $tampered;
$result = SA_Professional_Reauthentication::verify_and_record( 7, 'password', 'ignored', array( 'scope' => $scope ) );
sa_prof_assert( 'unknown' === $result['result'] && 'authentication_evidence_invalid' === $result['reason_code'], 'provider scope tampering fails closed' );

$GLOBALS['sa_auth_result'] = $valid;
$result = SA_Professional_Reauthentication::verify_and_record( 7, 'password', 'ignored', array( 'scope' => $scope ) );
sa_prof_assert( 'valid' === $result['result'], 'password plus File 02 AAL2 passkey evidence succeeds' );
sa_prof_assert( true === $result['password_verified'], 'fresh password verification is explicit' );
sa_prof_assert( 'professional_verification_review' === $result['purpose'], 'professional review purpose is explicit' );
sa_prof_assert( 'clinical_sign_in' === $result['authentication_purpose'], 'delegated authentication purpose remains explicit' );
sa_prof_assert( 'aal2' === $result['assurance_level'], 'AAL2 level is preserved' );
sa_prof_assert( 'webauthn_passkey' === $result['method'], 'professional receipt must retain File 02 passkey method' );
sa_prof_assert( 'sa.cf01.authentication-assurance' === $result['provider_contract'], 'provider contract provenance is retained' );
sa_prof_assert( '1.0.0' === $result['provider_version'], 'provider contract version provenance is retained' );
sa_prof_assert( $scope_hash === $result['provider_scope_hash'], 'provider scope provenance is retained' );
sa_prof_assert( $valid['trace_id'] === $result['provider_trace_id'], 'provider trace provenance is retained' );

$result = SA_Professional_Reauthentication::assertion( 7, $scope );
sa_prof_assert( 'valid' === $result['result'], 'existing session-bound professional receipt can be re-read' );
sa_prof_assert( true === $result['password_verified'], 'existing receipt retains verified-password evidence' );

$tampered = $valid;
$tampered['trace_id'] = '223e4567-e89b-42d3-a456-426614174000';
$GLOBALS['sa_auth_result'] = $tampered;
$result = SA_Professional_Reauthentication::assertion( 7, $scope );
sa_prof_assert( 'invalid' === $result['result'] && 'underlying_authentication_assurance_invalid' === $result['reason_code'], 'live provider trace change invalidates stored receipt' );
$GLOBALS['sa_auth_result'] = $valid;
SA_Professional_Reauthentication::verify_and_record( 7, 'password', 'ignored', array( 'scope' => $scope ) );

$GLOBALS['sa_user_pass'] = 'changed-hash';
$result = SA_Professional_Reauthentication::assertion( 7, $scope );
sa_prof_assert( 'invalid' === $result['result'], 'password-hash change revokes professional receipt even before session rotation' );
$GLOBALS['sa_user_pass'] = 'stored-hash';
$GLOBALS['sa_auth_result'] = $valid;
SA_Professional_Reauthentication::verify_and_record( 7, 'password', 'ignored', array( 'scope' => $scope ) );

$GLOBALS['sa_fingerprint'] = 'fingerprint-b';
$result = SA_Professional_Reauthentication::assertion( 7, $scope );
sa_prof_assert( 'invalid' === $result['result'], 'client fingerprint change revokes professional receipt' );

$GLOBALS['sa_fingerprint'] = 'fingerprint-a';
$GLOBALS['sa_auth_result'] = $valid;
SA_Professional_Reauthentication::verify_and_record( 7, 'password', 'ignored', array( 'scope' => $scope ) );
$GLOBALS['sa_session_token'] = 'rotated-session-token';
$result = SA_Professional_Reauthentication::assertion( 7, $scope );
sa_prof_assert( 'invalid' === $result['result'], 'session-token rotation revokes professional receipt' );

$result = SA_Professional_Reauthentication::verify_and_record( 7, str_repeat( 'x', 4097 ), 'ignored', array( 'scope' => $scope ) );
sa_prof_assert( 'invalid' === $result['result'] && 'current_password_invalid' === $result['reason_code'], 'oversized current password is rejected before verification' );

$result = SA_Professional_Reauthentication::verify_and_record( 7, 'password', 'ignored', array( 'scope' => str_repeat( 'x', 2049 ) ) );
sa_prof_assert( 'invalid' === $result['result'] && 'opaque_scope_invalid' === $result['reason_code'], 'oversized professional scope is rejected before hashing/provider use' );

echo "All {$tests} professional reauthentication checks passed.\n";
