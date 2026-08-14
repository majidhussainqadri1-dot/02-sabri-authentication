#!/usr/bin/env python3
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]

def rep(path, old, new, count=1):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    n = text.count(old)
    if n != count:
        raise SystemExit(f'{path}: expected {count}, found {n}: {old[:120]!r}')
    p.write_text(text.replace(old, new, count), encoding='utf-8')

# R304-A: Google/OIDC success must not bypass the same device/network/failure
# risk model used by password sign-in. Evaluate before creating a WP session.
rep(
    'includes/class-sa-google-oauth.php',
    "\t\t$completion = SAUTH_Account_Contract::completion_state( $user->ID, array( 'purpose' => 'google_sign_in' ) );\n\t\tif ( ! is_array( $completion ) || 'allow' !== ( $completion['result'] ?? '' ) ) {\n\t\t\t$this->fail( 'Account completion status could not be verified for Google sign-in.', 'login' );\n\t\t}\n\t\twp_set_current_user( $user->ID );",
    "\t\t$completion = SAUTH_Account_Contract::completion_state( $user->ID, array( 'purpose' => 'google_sign_in' ) );\n\t\tif ( ! is_array( $completion ) || 'allow' !== ( $completion['result'] ?? '' ) ) {\n\t\t\t$this->fail( 'Account completion status could not be verified for Google sign-in.', 'login' );\n\t\t}\n\t\t$risk = SAUTH_Login_Risk::evaluate( $user->ID, $completion );\n\t\tif ( 'challenge' === ( $risk['action'] ?? '' ) || 'deny' === ( $risk['action'] ?? '' ) ) {\n\t\t\tSAUTH_Login_Risk::record_failure( $user->ID, 'google_' . sanitize_key( (string) ( $risk['reason_code'] ?? 'risk_rejected' ) ), absint( $risk['score'] ?? 100 ) );\n\t\t\tSAUTH_Event_Outbox::emit( 'AccountAuthenticationFailed.v1', $user->ID, $user->ID, array( 'method' => 'google_oidc', 'reason' => 'passkey_step_up_required', 'risk_score' => absint( $risk['score'] ?? 100 ) ), 'security' );\n\t\t\t$this->fail( 'This Google sign-in needs stronger verification for the current device or network. Use your registered File 02 passkey to sign in.', 'login' );\n\t\t}\n\t\twp_set_current_user( $user->ID );"
)
rep(
    'includes/class-sa-google-oauth.php',
    "\t\tSAUTH_Login_Risk::record_successful_login( $user->ID, 'google', 0 );\n\t\tSAUTH_Event_Outbox::emit( 'AccountAuthenticationSucceeded.v1', $user->ID, $user->ID, array( 'method' => 'google_oidc', 'risk' => 'provider_authenticated' ), 'security' );",
    "\t\tSAUTH_Login_Risk::record_successful_login( $user->ID, 'google', absint( $risk['score'] ?? 0 ) );\n\t\tSAUTH_Event_Outbox::emit( 'AccountAuthenticationSucceeded.v1', $user->ID, $user->ID, array( 'method' => 'google_oidc', 'risk_score' => absint( $risk['score'] ?? 0 ) ), 'security' );"
)

# R304-B: partial unlink must prove the disabled-account containment marker.
rep(
    'includes/class-sa-google-oauth.php',
    "\t\tif ( ! empty( $remaining ) ) {\n\t\t\t/* Fail closed even if the underlying store partially rejected deletion. */\n\t\t\tupdate_user_meta( $user_id, '_sauth_google_account', '0' );\n\t\t\tupdate_user_meta( $user_id, '_sa_google_account', '0' );\n\t\t\tif ( class_exists( 'WP_Session_Tokens' ) ) { WP_Session_Tokens::get_instance( $user_id )->destroy_all(); }\n\t\t\tSA_Membership_Adapter::audit( 'google_account_unlink_incomplete', $user_id, array( 'remaining_count' => count( $remaining ) ) );",
    "\t\tif ( ! empty( $remaining ) ) {\n\t\t\t/* Fail closed even if the underlying store partially rejected deletion. */\n\t\t\tself::contain_linkage_failure( $user_id, 'google_account_unlink_incomplete' );\n\t\t\tSA_Membership_Adapter::audit( 'google_account_unlink_incomplete', $user_id, array( 'remaining_count' => count( $remaining ) ) );"
)

# R304-C: a failed transactional link restore gets a verified disabled marker;
# if even that cannot persist, global File 02 Safe Mode stops Google auth.
rep(
    'includes/class-sa-google-oauth.php',
    "\t\t\t\tif ( ! hash_equals( $expected, $stored ) ) {\n\t\t\t\t\tforeach ( $before as $restore_key => $restore_value ) {\n\t\t\t\t\t\tif ( '' === (string) $restore_value ) { delete_user_meta( $user_id, $restore_key ); }\n\t\t\t\t\t\telse { update_user_meta( $user_id, $restore_key, $restore_value ); }\n\t\t\t\t\t}\n\t\t\t\t\treturn false;\n\t\t\t\t}",
    "\t\t\t\tif ( ! hash_equals( $expected, $stored ) ) {\n\t\t\t\t\tforeach ( $before as $restore_key => $restore_value ) {\n\t\t\t\t\t\tif ( '' === (string) $restore_value ) { delete_user_meta( $user_id, $restore_key ); }\n\t\t\t\t\t\telse { update_user_meta( $user_id, $restore_key, $restore_value ); }\n\t\t\t\t\t}\n\t\t\t\t\t$restored = true;\n\t\t\t\t\tforeach ( $before as $restore_key => $restore_value ) {\n\t\t\t\t\t\t$restored = $restored && hash_equals( (string) $restore_value, (string) get_user_meta( $user_id, $restore_key, true ) );\n\t\t\t\t\t}\n\t\t\t\t\tif ( ! $restored ) { self::contain_linkage_failure( $user_id, 'google_link_rollback_failed' ); }\n\t\t\t\t\treturn false;\n\t\t\t\t}"
)

insert_before = "\n\tprivate static function fresh_passkey( $user_id ) {"
helper = r'''

	/**
	 * Disable Google authentication after an uncertain link/unlink mutation. If
	 * the disable markers themselves cannot be proven durable, Safe Mode becomes
	 * the higher-level containment barrier. All sessions are revoked either way.
	 */
	public static function contain_linkage_failure( $user_id, $reason = 'google_linkage_uncertain' ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) { return false; }
		update_user_meta( $user_id, '_sauth_google_account', '0' );
		update_user_meta( $user_id, '_sa_google_account', '0' );
		$disabled = '0' === (string) get_user_meta( $user_id, '_sauth_google_account', true )
			&& '0' === (string) get_user_meta( $user_id, '_sa_google_account', true );
		if ( ! $disabled ) {
			update_option( SAUTH_Operations::SAFE_MODE_OPTION, '1', false );
		}
		if ( class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		}
		SA_Membership_Adapter::audit( sanitize_key( (string) $reason ), $user_id, array( 'disabled_marker_verified' => $disabled ) );
		return $disabled;
	}
'''
p = ROOT / 'includes/class-sa-google-oauth.php'
text = p.read_text(encoding='utf-8')
if insert_before not in text:
    raise SystemExit('fresh_passkey insertion marker missing')
text = text.replace(insert_before, helper + insert_before, 1)
p.write_text(text, encoding='utf-8')

# R304-D: Google-first registration has the same rollback uncertainty and must
# use the common containment barrier if restoring the prior projection fails.
rep(
    'includes/class-sauth-google-registration.php',
    "\t\t\t\t\tif ( ! hash_equals( $expected, (string) get_user_meta( $user_id, $key, true ) ) ) {\n\t\t\t\t\t\tforeach ( $before as $restore_key => $restore_value ) {\n\t\t\t\t\t\t\tif ( '' === (string) $restore_value ) { delete_user_meta( $user_id, $restore_key ); }\n\t\t\t\t\t\t\telse { update_user_meta( $user_id, $restore_key, $restore_value ); }\n\t\t\t\t\t\t}\n\t\t\t\t\t\treturn false;\n\t\t\t\t\t}",
    "\t\t\t\t\tif ( ! hash_equals( $expected, (string) get_user_meta( $user_id, $key, true ) ) ) {\n\t\t\t\t\t\tforeach ( $before as $restore_key => $restore_value ) {\n\t\t\t\t\t\t\tif ( '' === (string) $restore_value ) { delete_user_meta( $user_id, $restore_key ); }\n\t\t\t\t\t\t\telse { update_user_meta( $user_id, $restore_key, $restore_value ); }\n\t\t\t\t\t\t}\n\t\t\t\t\t\t$restored = true;\n\t\t\t\t\t\tforeach ( $before as $restore_key => $restore_value ) {\n\t\t\t\t\t\t\t$restored = $restored && hash_equals( (string) $restore_value, (string) get_user_meta( $user_id, $restore_key, true ) );\n\t\t\t\t\t\t}\n\t\t\t\t\t\tif ( ! $restored ) { SA_Google_OAuth::contain_linkage_failure( $user_id, 'google_registration_link_rollback_failed' ); }\n\t\t\t\t\t\treturn false;\n\t\t\t\t\t}"
)

(ROOT / 'tests/r304-google-auth-regression.php').write_text(r'''<?php
$root = dirname( __DIR__ );
$google = file_get_contents( $root . '/includes/class-sa-google-oauth.php' );
$registration = file_get_contents( $root . '/includes/class-sauth-google-registration.php' );
$fail = array();
$checks = array(
    array( $google, 'SAUTH_Login_Risk::evaluate( $user->ID, $completion )', 'Google sign-in bypasses risk evaluation' ),
    array( $google, "'challenge' === ( $risk['action'] ?? '' )", 'Google risk challenge path missing' ),
    array( $google, "record_successful_login( $user->ID, 'google', absint( $risk['score'] ?? 0 ) )", 'Google success records a fake zero risk score' ),
    array( $google, 'public static function contain_linkage_failure', 'uncertain Google linkage has no common containment barrier' ),
    array( $google, "SAUTH_Operations::SAFE_MODE_OPTION", 'failed Google disable marker does not escalate to Safe Mode' ),
    array( $google, 'google_link_rollback_failed', 'Google link rollback is not postcondition checked' ),
    array( $google, "self::contain_linkage_failure( $user_id, 'google_account_unlink_incomplete' )", 'partial unlink is not contained' ),
    array( $registration, 'google_registration_link_rollback_failed', 'Google-first registration rollback is not postcondition checked' ),
);
foreach ( $checks as $c ) { if ( false === strpos( $c[0], $c[1] ) ) { $fail[] = $c[2]; } }
if ( $fail ) { fwrite( STDERR, "R304 Google regression failures:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R304 Google authentication regression PASS (' . count( $checks ) . " assertions).\n";
''', encoding='utf-8')
print('R304 corrections staged.')
