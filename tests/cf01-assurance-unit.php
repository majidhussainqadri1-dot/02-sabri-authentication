<?php
/**
 * No-network unit checks for File 02 session-bound authentication assurance.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SA_VERSION', '0.3.0' );
define( 'SMC_VERSION', '1.2.7' );
define( 'SMC_CF01_CONTRACT_VERSION', '1.0.0' );

$GLOBALS['sa_cf01_users']       = array( 7 => (object) array( 'ID' => 7 ) );
$GLOBALS['sa_cf01_transients']  = array();
$GLOBALS['sa_cf01_logged_in']   = false;
$GLOBALS['sa_cf01_current_id']  = 0;
$GLOBALS['sa_cf01_token']       = '';
$GLOBALS['sa_cf01_provider']    = array();
$GLOBALS['sa_cf01_membership']  = array();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function get_userdata( $user_id ) { return $GLOBALS['sa_cf01_users'][ (int) $user_id ] ?? false; }
function is_user_logged_in() { return (bool) $GLOBALS['sa_cf01_logged_in']; }
function get_current_user_id() { return (int) $GLOBALS['sa_cf01_current_id']; }
function wp_get_session_token() { return (string) $GLOBALS['sa_cf01_token']; }
function wp_salt( $scheme = 'auth' ) { return hash( 'sha256', 'file02-cf01-test|' . $scheme ); }
function wp_generate_uuid4() { static $n = 1; return sprintf( '00000000-0000-4000-8000-%012d', $n++ ); }
function set_transient( $key, $value, $expiration ) { $GLOBALS['sa_cf01_transients'][ $key ] = array( 'value' => $value, 'expires' => time() + max( 1, (int) $expiration ) ); return true; }
function get_transient( $key ) {
	if ( ! isset( $GLOBALS['sa_cf01_transients'][ $key ] ) ) { return false; }
	if ( $GLOBALS['sa_cf01_transients'][ $key ]['expires'] <= time() ) { unset( $GLOBALS['sa_cf01_transients'][ $key ] ); return false; }
	return $GLOBALS['sa_cf01_transients'][ $key ]['value'];
}
function delete_transient( $key ) { unset( $GLOBALS['sa_cf01_transients'][ $key ] ); return true; }

final class SA_Security {
	public static function client_fingerprint() { return hash( 'sha256', 'fixed-client' ); }
}
final class SMC_CF01_Contract {
	public static function verify_step_up( $user_id, $code, $context = array() ) {
		return $GLOBALS['sa_cf01_provider'];
	}
	public static function membership_assertion( $user_id, $context = array() ) {
		return $GLOBALS['sa_cf01_membership'];
	}
}

function sa_cf01_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-sa-authentication-assurance.php';

$GLOBALS['sa_cf01_provider'] = array(
	'contract'         => 'smc.cf01.membership-assurance.step-up',
	'contract_version' => '1.0.0',
	'subject_uuid'     => '11111111-1111-4111-8111-111111111111',
	'scope_hash'       => hash( 'sha256', 'provider-scope' ),
	'method'           => 'totp',
	'verified_at'      => gmdate( 'c' ),
	'result'           => 'allow',
	'reason_code'      => 'second_factor_verified',
);
$GLOBALS['sa_cf01_membership'] = array(
	'contract' => 'smc.cf01.membership-assurance',
	'contract_version' => '1.0.0',
	'result' => 'allow',
	'reason_code' => 'capability_allowed',
	'subject' => array( 'platform_uuid' => '11111111-1111-4111-8111-111111111111' ),
);

$pending = SA_Authentication_Assurance::verify_and_record(
	7,
	'123456',
	array( 'purpose' => 'clinical_sign_in', 'scope' => 'google-login|opaque' )
);
sa_cf01_assert( 'valid' === $pending['result'], 'pre-login File 00 evidence must create a bounded pending assurance' );
sa_cf01_assert( 'authentication_verified_pending_session' === $pending['reason_code'], 'pending assurance reason is incorrect' );
sa_cf01_assert( ! isset( $pending['code'], $pending['secret'], $pending['session_token'], $pending['provider_scope_hash'] ), 'public assertion leaked protected evidence' );

$before = SA_Authentication_Assurance::assertion( 7, 'clinical_sign_in', 'google-login|opaque' );
sa_cf01_assert( 'invalid' === $before['result'], 'pending evidence must not be usable without an authenticated session' );

$GLOBALS['sa_cf01_logged_in']  = true;
$GLOBALS['sa_cf01_current_id'] = 7;
$GLOBALS['sa_cf01_token']      = 'session-token-a';
SA_Authentication_Assurance::promote_pending_for_cookie( '', 0, 0, 7, 'logged_in', 'session-token-a' );
$valid = SA_Authentication_Assurance::assertion( 7, 'clinical_sign_in', 'google-login|opaque' );
sa_cf01_assert( 'valid' === $valid['result'], 'promoted evidence must be valid only in the bound session' );
sa_cf01_assert( 'aal2' === $valid['assurance_level'], 'File 00 second-factor evidence must map to AAL2' );

$GLOBALS['sa_cf01_token'] = 'session-token-b';
$rotated = SA_Authentication_Assurance::assertion( 7, 'clinical_sign_in', 'google-login|opaque' );
sa_cf01_assert( 'invalid' === $rotated['result'], 'session-token rotation must invalidate the old assurance' );

$GLOBALS['sa_cf01_token'] = 'session-token-a';
$wrong_scope = SA_Authentication_Assurance::assertion( 7, 'clinical_sign_in', 'google-login|other' );
sa_cf01_assert( 'invalid' === $wrong_scope['result'], 'scope mismatch must invalidate assurance' );
$wrong_purpose = SA_Authentication_Assurance::assertion( 7, 'clinical_export', 'google-login|opaque' );
sa_cf01_assert( 'invalid' === $wrong_purpose['result'], 'purpose mismatch must invalidate assurance' );

$link = SA_Authentication_Assurance::verify_and_record(
	7,
	'123456',
	array( 'purpose' => 'authentication_link', 'scope' => 'google-link|opaque' )
);
sa_cf01_assert( 'valid' === $link['result'], 'logged-in link verification must bind directly to the current session' );
$link_assertion = SA_Authentication_Assurance::assertion( 7, 'authentication_link', 'google-link|opaque' );
sa_cf01_assert( 'valid' === $link_assertion['result'], 'current link assurance was not retrievable' );

$GLOBALS['sa_cf01_provider']['result'] = 'deny';
$GLOBALS['sa_cf01_provider']['reason_code'] = 'second_factor_invalid_or_replayed';
$denied = SA_Authentication_Assurance::verify_and_record(
	7,
	'000000',
	array( 'purpose' => 'authentication_unlink', 'scope' => 'google-unlink|opaque' )
);
sa_cf01_assert( 'invalid' === $denied['result'], 'provider denial must remain invalid in File 02' );

$GLOBALS['sa_cf01_provider'] = array( 'result' => 'allow' );
$malformed = SA_Authentication_Assurance::verify_and_record(
	7,
	'123456',
	array( 'purpose' => 'authentication_link', 'scope' => 'google-link|malformed' )
);
sa_cf01_assert( 'unknown' === $malformed['result'] && 'provider_contract_invalid' === $malformed['reason_code'], 'malformed provider evidence must fail unknown' );

SA_Authentication_Assurance::clear_current_session();
$cleared = SA_Authentication_Assurance::assertion( 7, 'authentication_link', 'google-link|opaque' );
sa_cf01_assert( 'invalid' === $cleared['result'], 'logout/session clearing must revoke session-bound receipts' );

echo "File 02 CF-01 assurance checks: 14 PASS, 0 FAIL\n";
