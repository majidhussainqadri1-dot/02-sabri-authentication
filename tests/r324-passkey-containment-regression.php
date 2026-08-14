<?php
$root = dirname( __DIR__ );
$runtime = file_get_contents( $root . '/includes/class-sauth-passkey-runtime.php' );
$fail = array();
$checks = array(
    'contain_security_state_failure',
    'SAUTH_Session_Manager::revoke_user_sessions',
    'SAUTH_Operations::enter_safe_mode()',
    'passkey_quarantine',
    'passkey_assurance_invalidation',
    'sessions_revoked_verified',
    'safe_mode_verified',
);
foreach ( $checks as $needle ) {
    if ( false === strpos( $runtime, $needle ) ) { $fail[] = 'missing ' . $needle; }
}
if ( false !== strpos( $runtime, "update_option( SAUTH_Operations::SAFE_MODE_OPTION, '1', false )" ) ) { $fail[] = 'passkey runtime still writes Safe Mode option directly'; }
if ( $fail ) { fwrite( STDERR, "R324 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R324 passkey containment regression PASS (8 assertions).' . PHP_EOL;
