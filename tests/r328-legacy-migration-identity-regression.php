<?php
$root = dirname( __DIR__ );
$activator = file_get_contents( $root . '/includes/class-sa-activator.php' );
$migration = file_get_contents( $root . '/MIGRATION.md' );
$fail = array();
$checks = array(
    array( $activator, '$identity_columns = array(', 'legacy migration lacks logical identity map' ),
    array( $activator, "'auth_outbox' => 'event_id'", 'outbox logical identity missing' ),
    array( $activator, "'email_verifications' => 'user_id'", 'email-verification logical identity missing' ),
    array( $activator, "'auth_sessions' => 'public_id'", 'session logical identity missing' ),
    array( $activator, "'auth_devices' => 'public_id'", 'device logical identity missing' ),
    array( $activator, "'risk_challenges' => 'public_id'", 'risk-challenge logical identity missing' ),
    array( $activator, "'auth_attempts' => 'public_id'", 'attempt logical identity missing' ),
    array( $activator, 'LEFT JOIN {$canonical} AS c', 'post-copy logical reconciliation query missing' ),
    array( $activator, 'WHERE c.`{$identity}` IS NULL', 'migration does not prove every legacy identity is represented' ),
    array( $migration, 'stable logical identity', 'migration documentation lacks logical identity reconciliation rule' ),
);
foreach ( $checks as $check ) { if ( false === strpos( $check[0], $check[1] ) ) { $fail[] = $check[2]; } }
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
