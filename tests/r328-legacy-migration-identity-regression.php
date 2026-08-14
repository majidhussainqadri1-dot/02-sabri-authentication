<?php
$root = dirname( __DIR__ );
$activator = file_get_contents( $root . '/includes/class-sa-activator.php' );
$migration = file_get_contents( $root . '/MIGRATION.md' );
$fail = array();
if ( false === strpos( $activator, '$identity_columns = array(' ) ) { $fail[] = 'legacy migration lacks logical identity map'; }
$identities = array(
    'rate_limits' => 'bucket_hash',
    'auth_outbox' => 'event_id',
    'email_verifications' => 'user_id',
    'auth_sessions' => 'public_id',
    'auth_devices' => 'public_id',
    'risk_challenges' => 'public_id',
    'auth_attempts' => 'public_id',
);
foreach ( $identities as $key => $identity ) {
    $pattern = "/'" . preg_quote( $key, '/' ) . "'\\s*=>\\s*'" . preg_quote( $identity, '/' ) . "'/";
    if ( 1 !== preg_match( $pattern, $activator ) ) { $fail[] = 'logical identity missing for ' . $key; }
}
if ( false === strpos( $activator, 'LEFT JOIN `{$canonical}` AS c' ) ) { $fail[] = 'post-copy logical reconciliation query missing'; }
if ( false === strpos( $activator, 'WHERE c.`{$identity}` IS NULL' ) ) { $fail[] = 'migration does not prove every legacy identity is represented'; }
if ( false === strpos( $migration, 'stable logical identity' ) ) { $fail[] = 'migration documentation lacks logical identity reconciliation rule'; }
foreach ( array(
    "'auth_outbox'         => 'id,event_id",
    "'auth_sessions'       => 'id,public_id",
    "'auth_devices'        => 'id,public_id",
    "'risk_challenges'     => 'id,public_id",
    "'auth_attempts'       => 'id,public_id",
) as $obsolete ) {
    if ( false !== strpos( $activator, $obsolete ) ) { $fail[] = 'legacy auto-increment ID is still copied: ' . $obsolete; }
}
if ( $fail ) { fwrite( STDERR, "R328 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R328 legacy migration logical-identity regression PASS (15 assertions).' . PHP_EOL;
