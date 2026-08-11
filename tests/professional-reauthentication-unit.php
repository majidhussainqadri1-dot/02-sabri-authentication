<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'SA_VERSION', '0.3.0' );

$GLOBALS['sa_current_user'] = 7;
$GLOBALS['sa_logged_in'] = true;
$GLOBALS['sa_session_token'] = 'session-token';
$GLOBALS['sa_password_ok'] = true;
$GLOBALS['sa_auth_result'] = array();
$GLOBALS['sa_transients'] = array();
$GLOBALS['sa_fingerprint'] = 'fingerprint-a';

function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function is_user_logged_in() { return (bool) $GLOBALS['sa_logged_in']; }
function get_current_user_id() { return (int) $GLOBALS['sa_current_user']; }
function wp_get_session_token() { return (string) $GLOBALS['sa_session_token']; }
function get_userdata( $user_id ) { return 7 === (int) $user_id ? (object) array( 'ID' => 7, 'user_pass' => 'stored-hash' ) : false; }
function wp_check_password( $password, $hash, $user_id ) { return (bool) $GLOBALS['sa_password_ok'] && 'stored-hash' === $hash && 7 === (int) $user_id; }
function wp_generate_uuid4() { return '123e4567-e89b-42d3-a456-426614174000'; }
function wp_salt( $scheme = 'auth' ) { return 'unit-test-' . $scheme; }
function do_action() {}
function add_action() {}
function set_transient( $key, $value, $ttl ) { $GLOBALS['sa_transients'][ $key ] = array( 'value' => $value, 'expires' => time() + max( 1, (int) $ttl ) ); return true; }
function get_transient( $key ) { if ( ! isset( $GLOBALS['sa_transients'][ $key ] ) ) { return false; } if ( $GLOBALS['sa_transients'][ $key ]['expires'] <= time() ) { unset( $GLOBALS['sa_transients'][ $key ] ); return false; } return $GLOBALS['sa_transients'][ $key ]['value']; }
function delete_transient( $key ) { unset( $GLOBALS['sa_transients'][ $key ] ); return true; }

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
	'subject_uuid' => '123e4567-e89b-42d3-a456-426614174000',
	'scope_hash' => $scope_hash,
	'method' => 'totp',
	'assurance_level' => 'aal2',
	'verified_at' => gmdate( 'c', time() - 5 ),
	'expires_at' => gmdate( 'c', time() + 300 ),
	'trace_id' => '123e4567-e89b-42d3-a456-426614174000',
	'result' => 'valid',
	'reason_code' => 'session_assurance_valid',
);
$GLOBALS['sa_auth_result'] = $valid;

$result = SA_Professional_Reauthentication::verify_and_record( 8, 'password', '123456', array( 'scope' => $scope ) );
sa_prof_assert( 'invalid' === $result['result'] && 'current_authenticated_session_required' === $result['reason_code'], 'different subject cannot bind current session' );

$GLOBALS['sa_password_ok'] = false;
$result = SA_Professional_Reauthentication::verify_and_record( 7, 'wrong', '123456', array( 'scope' => $scope ) );
sa_prof_assert( 'invalid' === $result['result'] && 'current_password_invalid' === $result['reason_code'], 'wrong current password fails closed' );

$GLOBALS['sa_password_ok'] = true;
$GLOBALS['sa_auth_result'] = array( 'contract' => 'wrong', 'contract_version' => '1.0.0', 'result' => 'valid' );
$result = SA_Professional_Reauthentication::verify_and_record( 7, 'password', '123456', array( 'scope' => $scope ) );
sa_prof_assert( 'unknown' === $result['result'] && 'authentication_contract_invalid' === $result['reason_code'], 'invalid downstream contract fails closed' );

$GLOBALS['sa_auth_result'] = $valid;
$result = SA_Professional_Reauthentication::verify_and_record( 7, 'password', '123456', array( 'scope' => $scope ) );
sa_prof_assert( 'valid' === $result['result'], 'password plus AAL2 evidence succeeds' );
sa_prof_assert( true === $result['password_verified'], 'fresh password verification is explicit' );
sa_prof_assert( 'professional_verification_review' === $result['purpose'], 'professional review purpose is explicit' );
sa_prof_assert( 'clinical_sign_in' === $result['authentication_purpose'], 'delegated authentication purpose remains explicit' );
sa_prof_assert( 'aal2' === $result['assurance_level'], 'AAL2 level is preserved' );

$result = SA_Professional_Reauthentication::assertion( 7, $scope );
sa_prof_assert( 'valid' === $result['result'], 'existing session-bound professional receipt can be re-read' );
sa_prof_assert( true === $result['password_verified'], 'existing receipt retains verified-password evidence' );

$GLOBALS['sa_fingerprint'] = 'fingerprint-b';
$result = SA_Professional_Reauthentication::assertion( 7, $scope );
sa_prof_assert( 'invalid' === $result['result'], 'client fingerprint change revokes professional receipt' );

$GLOBALS['sa_fingerprint'] = 'fingerprint-a';
$GLOBALS['sa_auth_result'] = $valid;
SA_Professional_Reauthentication::verify_and_record( 7, 'password', '123456', array( 'scope' => $scope ) );
$GLOBALS['sa_session_token'] = 'rotated-session-token';
$result = SA_Professional_Reauthentication::assertion( 7, $scope );
sa_prof_assert( 'invalid' === $result['result'], 'session-token rotation revokes professional receipt' );

echo "All {$tests} professional reauthentication checks passed.\n";
