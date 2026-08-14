<?php
$root = dirname( __DIR__ );
$google = file_get_contents( $root . '/includes/class-sa-google-oauth.php' );
$registration = file_get_contents( $root . '/includes/class-sauth-google-registration.php' );
$fail = array();
$checks = array(
    array( $google, 'SAUTH_Login_Risk::evaluate( $user->ID, $completion )', 'Google sign-in bypasses risk evaluation' ),
    array( $google, '\'challenge\' === ( $risk[\'action\'] ?? \'\' )', 'Google risk challenge path missing' ),
    array( $google, 'record_successful_login( $user->ID, \'google\', absint( $risk[\'score\'] ?? 0 ) )', 'Google success records a fake zero risk score' ),
    array( $google, 'public static function contain_linkage_failure', 'uncertain Google linkage has no common containment barrier' ),
    array( $google, 'SAUTH_Operations::enter_safe_mode()', 'failed Google disable marker does not escalate through the centralized Safe Mode authority' ),
    array( $google, 'google_link_rollback_failed', 'Google link rollback is not postcondition checked' ),
    array( $google, 'self::contain_linkage_failure( $user_id, \'google_account_unlink_incomplete\' )', 'partial unlink is not contained' ),
    array( $registration, 'google_registration_link_rollback_failed', 'Google-first registration rollback is not postcondition checked' ),
);
foreach ( $checks as $c ) { if ( false === strpos( $c[0], $c[1] ) ) { $fail[] = $c[2]; } }
if ( $fail ) { fwrite( STDERR, "R304 Google regression failures:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R304 Google authentication regression PASS (' . count( $checks ) . " assertions).\n";
