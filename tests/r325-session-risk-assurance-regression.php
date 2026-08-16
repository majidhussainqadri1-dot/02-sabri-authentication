<?php
$root = dirname( __DIR__ );
$sessions = file_get_contents( $root . '/includes/class-sauth-session-manager.php' );
$risk = file_get_contents( $root . '/includes/class-sauth-login-risk.php' );
$assurance = file_get_contents( $root . '/includes/class-sa-authentication-assurance.php' );
$professional = file_get_contents( $root . '/includes/class-sa-professional-reauthentication.php' );
$fail = array();
$checks = array(
    array( $sessions, 'is_wp_error( $sessions )', 'session UI does not distinguish storage failure' ),
    array( $sessions, "new WP_Error( 'session_registry_unavailable'", 'session list does not surface registry failure' ),
    array( $sessions, "return 'unknown';", 'session risk projection cannot represent unknown storage state' ),
    array( $risk, "SAUTH_Provider_Health::available_for_ui( 'membership' )", 'login risk still consumes a Membership provider probe' ),
    array( $assurance, 'SAUTH_Passkey_Runtime::current_assurance', 'CF-01 assurance bypasses hardened passkey runtime' ),
    array( $professional, 'password_binding', 'professional reauthentication lost password-state binding' ),
);
foreach ( $checks as $check ) {
    if ( false === strpos( $check[0], $check[1] ) ) { $fail[] = $check[2]; }
}
if ( false !== strpos( $risk, "SAUTH_Provider_Health::allow_request( 'membership' )" ) ) { $fail[] = 'login-risk heuristic still claims half-open Membership lease'; }
if ( $fail ) { fwrite( STDERR, "R325 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R325 session/risk/assurance regression PASS (7 assertions).' . PHP_EOL;
