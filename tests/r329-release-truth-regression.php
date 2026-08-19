<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/sabri-authentication.php' );
$lock = file_get_contents( $root . '/RELEASE-LOCK.json' );
$readme = file_get_contents( $root . '/readme.txt' );
$status = file_get_contents( $root . '/STATUS.md' );
$manifest = file_get_contents( $root . '/RELEASE-MANIFEST.md' );
$baseline = file_get_contents( $root . '/.github/workflows/baseline-integrity.yml' );
$docs = file_get_contents( $root . '/.github/workflows/canonical-storage-and-docs.yml' );
$integration = file_get_contents( $root . '/.github/workflows/file00-1.2.44-real-integration.yml' );
$review = file_get_contents( $root . '/.github/workflows/review-branch-integrity.yml' );
$architecture = file_get_contents( $root . '/ARCHITECTURE.md' );
$adapter = file_get_contents( $root . '/includes/class-sa-membership-adapter.php' );
$fail = array();
$checks = array(
    array( $main, 'Version: 1.3.3', 'plugin header release identity stale' ),
    array( $main, "SAUTH_VERSION', '1.3.3", 'runtime release identity stale' ),
    array( $main, "SAUTH_DB_VERSION', '1.3.0", 'DB identity unexpectedly changed' ),
    array( $lock, '"release_version": "1.3.3"', 'release lock runtime stale' ),
    array( $lock, 'fix/file02-passkey-assurance-cycle-1.3.3-2026-08-19', 'release lock branch stale' ),
    array( $lock, 'R340-passkey-assurance-cycle', 'release lock review line stale' ),
    array( $readme, 'Stable tag: 1.3.3', 'WordPress stable tag stale' ),
    array( $readme, '= 1.3.3 =', 'WordPress changelog omits current passkey-assurance cycle correction' ),
    array( $status, 'Version 1.3.0', 'status no longer preserves R337 base identity' ),
    array( $manifest, '1.3.0 / 1.3.0', 'release manifest no longer preserves the R337 base identity' ),
    array( $baseline, "RELEASE_VERSION: '1.3.3'", 'baseline release workflow version stale' ),
    array( $baseline, 'tests/r33*-regression.php', 'baseline release workflow omits prior R33x regressions' ),
    array( $baseline, 'tests/r34*-regression.php', 'baseline release workflow omits current R34x regressions' ),
    array( $baseline, 'tests/r339-file00-canonical-route-contract-regression.php', 'baseline release workflow does not require R339' ),
    array( $baseline, 'tests/r340-passkey-assurance-cycle-regression.php', 'baseline release workflow does not require R340' ),
    array( $docs, 'tests/r33*-regression.php', 'documentation workflow omits prior R33x regressions' ),
    array( $review, 'tests/r33*-regression.php', 'review workflow omits prior R33x regressions' ),
    array( $baseline, 'shivammathur/setup-php@2.37.2', 'baseline workflow uses unresolved setup-php form' ),
    array( $docs, 'shivammathur/setup-php@2.37.2', 'documentation workflow uses unresolved setup-php form' ),
    array( $integration, 'shivammathur/setup-php@2.37.2', 'integration workflow uses unresolved setup-php form' ),
    array( $integration, '1d7f215193d778b0977c8e50d738c42e1e5f66c2', 'integration workflow is not exact-pinned to corrected File00 1.2.44 candidate' ),
    array( $integration, 'c4ab298b3ba2b870d507d32b36b1b4afd2771621', 'integration workflow does not pin the exact File00 1.2.43 live-route baseline' ),
    array( $integration, 'legacy logical-identity collision migration', 'integration lacks R328 logical-identity migration rehearsal' ),
    array( $integration, 'legacy-logical-session', 'integration does not prove colliding legacy logical identity survives' ),
    array( $integration, 'Prove canonical account taxonomy parity on both runtimes', 'integration lacks canonical taxonomy parity proof' ),
    array( $integration, 'Prove File 02 resolves exact File 00 canonical membership routes', 'integration lacks canonical File00 route resolution proof' ),
    array( $integration, 'Rehearse exact deployed stale passkey user_status index on real MariaDB', 'integration lacks exact live stale-index recovery rehearsal' ),
    array( $adapter, "MEMBERSHIP_APPLICATION_KEY  = 'application'", 'adapter lost canonical File00 application key' ),
    array( $adapter, "MEMBERSHIP_SECURITY_KEY     = 'security'", 'adapter lost canonical File00 security key' ),
    array( $adapter, "MEMBERSHIP_STATUS_KEY       = 'status'", 'adapter lost canonical File00 status key' ),
    array( $architecture, 'stable logical identity', 'architecture omits R328 migration invariant' ),
    array( $baseline, 'round-ledger-apply.yml', 'release source policy does not reject temporary round ledger workflow' ),
    array( $baseline, 'tools/apply-round-ledger.py', 'release source policy does not reject temporary ledger applicator' ),
);
foreach ( $checks as $check ) { if ( false === strpos( $check[0], $check[1] ) ) { $fail[] = $check[2]; } }
foreach ( array( $baseline, $docs, $integration ) as $workflow ) {
    if ( false !== strpos( $workflow, 'shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240' ) ) { $fail[] = 'permanent workflow retains setup-php resolver-failing SHA form'; }
}
foreach ( array( 'sabri_profile', 'sabri_security_center', 'sabri_verification_status' ) as $forbidden ) {
    if ( false !== strpos( $adapter, $forbidden ) ) { $fail[] = 'invented File00 membership route remains: ' . $forbidden; }
}
if ( $fail ) { fwrite( STDERR, "R329 current release invariants:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R329 release invariants PASS (' . ( count( $checks ) + 6 ) . ' assertions).' . PHP_EOL;
