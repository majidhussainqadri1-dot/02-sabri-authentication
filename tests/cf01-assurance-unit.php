<?php
/**
 * No-network unit checks for File 02 session-bound authentication assurance.
 * File 00 supplies membership eligibility only; File 02 WebAuthn/passkeys own
 * strong authentication evidence.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SAUTH_VERSION', '1.2.1' );
define( 'SAUTH_PASSKEY_CONTRACT_VERSION', '1.0.0' );

$GLOBALS['sa_cf01_users']       = array( 7 => (object) array( 'ID' => 7 ) );
$GLOBALS['sa_cf01_transients']  = array();
$GLOBALS['sa_cf01_logged_in']   = false;
$GLOBALS['sa_cf01_current_id']  = 0;
$GLOBALS['sa_cf01_token']       = '';
$GLOBALS['sa_cf01_membership']  = array();
$GLOBALS['sa_cf01_passkey']     = array();

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
final class SA_Membership_Adapter {
	public static function available() { return true; }
	public static function membership_assertion( $user_id, $action = 'clinical_identity_link', $purpose = 'authentication' ) {
		return $GLOBALS['sa_cf01_membership'];
	}
}
final class SAUTH_Passkey_Runtime {
	public static function current_assurance( $user_id = 0 ) {
		return $GLOBALS['sa_cf01_passkey'];
	}
}

function sa_cf01_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-sa-authentication-assurance.php';

$GLOBALS['sa_cf01_membership'] = array(
	'contract' => 'smc.cf01.membership-assurance',
	'contract_version' => '1.1.0',
	'result' => 'allow',
	'reason_code' => 'capability_allowed',
	'subject' => array( 'platform_uuid' => '11111111-1111-4111-8111-111111111111' ),
);
$GLOBALS['sa_cf01_passkey'] = array(
	'contract_version' => '1.0.0',
	'owner' => 'file02',
	'level' => 3,
	'method' => 'webauthn_passkey',
	'passkey_asserted' => true,
	'hardware_backed' => false,
	'verified_at' => time() - 5,
);

$prelogin = SA_Authentication_Assurance::verify_and_record(
	7,
	'legacy-code-is-ignored',
	array( 'purpose' => 'clinical_sign_in', 'scope' => 'google-login|opaque' )
);
sa_cf01_assert( 'invalid' === $prelogin['result'], 'pre-login compatibility call must not fabricate strong-auth evidence' );
sa_cf01_assert( 'passkey_ceremony_required' === $prelogin['reason_code'], 'pre-login flow must require the dedicated passkey ceremony' );

$GLOBALS['sa_cf01_logged_in']  = true;
$GLOBALS['sa_cf01_current_id'] = 7;
$GLOBALS['sa_cf01_token']      = 'session-token-a';
$verified = SA_Authentication_Assurance::verify_and_record(
	7,
	'ignored',
	array( 'purpose' => 'clinical_sign_in', 'scope' => 'google-login|opaque' )
);
sa_cf01_assert( 'valid' === $verified['result'], 'fresh File 02 passkey evidence plus membership must create a session-bound assurance' );
sa_cf01_assert( 'webauthn_passkey' === $verified['method'], 'strong-auth method must be File 02 WebAuthn/passkey' );
sa_cf01_assert( 'aal2' === $verified['assurance_level'], 'File 02 passkey evidence must map to AAL2' );
sa_cf01_assert( ! isset( $verified['session_binding'], $verified['fingerprint'] ), 'public assertion leaked session/fingerprint binding material' );

$current = SA_Authentication_Assurance::assertion( 7, 'clinical_sign_in', 'google-login|opaque' );
sa_cf01_assert( 'valid' === $current['result'], 'current session-bound assurance was not retrievable' );

$GLOBALS['sa_cf01_token'] = 'session-token-b';
$rotated = SA_Authentication_Assurance::assertion( 7, 'clinical_sign_in', 'google-login|opaque' );
sa_cf01_assert( 'invalid' === $rotated['result'], 'session-token rotation must invalidate the old assurance' );

$GLOBALS['sa_cf01_token'] = 'session-token-a';
$wrong_scope = SA_Authentication_Assurance::assertion( 7, 'clinical_sign_in', 'google-login|other' );
sa_cf01_assert( 'invalid' === $wrong_scope['result'], 'scope mismatch must invalidate assurance' );
$wrong_purpose = SA_Authentication_Assurance::assertion( 7, 'clinical_export', 'google-login|opaque' );
sa_cf01_assert( 'invalid' === $wrong_purpose['result'], 'purpose mismatch must invalidate assurance' );

$GLOBALS['sa_cf01_passkey']['passkey_asserted'] = false;
$underlying_revoked = SA_Authentication_Assurance::assertion( 7, 'clinical_sign_in', 'google-login|opaque' );
sa_cf01_assert( 'invalid' === $underlying_revoked['result'], 'revoked underlying passkey assurance must invalidate the derived receipt' );

$GLOBALS['sa_cf01_passkey']['passkey_asserted'] = true;
$GLOBALS['sa_cf01_membership']['result'] = 'deny';
$membership_denied = SA_Authentication_Assurance::verify_and_record(
	7,
	'',
	array( 'purpose' => 'authentication_link', 'scope' => 'google-link|opaque' )
);
sa_cf01_assert( 'unknown' === $membership_denied['result'], 'membership denial must not become valid authentication assurance' );
sa_cf01_assert( 'membership_subject_unavailable' === $membership_denied['reason_code'], 'membership denial reason must fail closed at the eligibility boundary' );

$GLOBALS['sa_cf01_membership']['result'] = 'allow';
$oversized = SA_Authentication_Assurance::verify_and_record( 7, '', array( 'purpose' => 'authentication_link', 'scope' => str_repeat( 'x', 2049 ) ) );
sa_cf01_assert( 'invalid' === $oversized['result'] && 'purpose_or_scope_invalid' === $oversized['reason_code'], 'oversized scope must be rejected before provider use' );

echo "File 02 CF-01/passkey assurance checks: 13 PASS, 0 FAIL\n";
