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
$fail = array();
$checks = array(
    array( $main, 'Version: 1.2.6', 'plugin header release identity stale' ),
    array( $main, "SAUTH_VERSION', '1.2.6", 'runtime release identity stale' ),
    array( $main, "SAUTH_DB_VERSION', '1.2.1", 'DB identity unexpectedly changed' ),
    array( $lock, '"release_version": "1.2.6"', 'release lock runtime stale' ),
    array( $lock, 'fix/file02-passkey-index-reconciliation-1.2.6', 'release lock branch stale' ),
    array( $readme, 'Stable tag: 1.2.6', 'WordPress stable tag stale' ),
    array( $status, 'Version 1.2.6', 'status runtime stale' ),
    array( $manifest, '1.2.6 / 1.2.1', 'release manifest identity stale' ),
    array( $baseline, "RELEASE_VERSION: '1.2.6'", 'baseline release workflow version stale' ),
    array( $baseline, 'tests/r33*-regression.php', 'baseline release workflow omits final R33x regressions' ),
    array( $docs, 'tests/r33*-regression.php', 'documentation workflow omits final R33x regressions' ),
    array( $review, 'tests/r33*-regression.php', 'review workflow omits final R33x regressions' ),
    array( $baseline, 'shivammathur/setup-php@2.37.2', 'baseline workflow uses unresolved setup-php form' ),
    array( $docs, 'shivammathur/setup-php@2.37.2', 'documentation workflow uses unresolved setup-php form' ),
    array( $integration, 'shivammathur/setup-php@2.37.2', 'integration workflow uses unresolved setup-php form' ),
    array( $integration, '1d7f215193d778b0977c8e50d738c42e1e5f66c2', 'integration workflow is not exact-pinned to corrected File00 1.2.44 candidate' ),
    array( $integration, 'legacy logical-identity collision migration', 'integration lacks R328 logical-identity migration rehearsal' ),
    array( $integration, 'legacy-logical-session', 'integration does not prove colliding legacy logical identity survives' ),
    array( $integration, 'Prove canonical account taxonomy parity on both runtimes', 'integration lacks canonical taxonomy parity proof' ),
    array( $architecture, 'stable logical identity', 'architecture omits R328 migration invariant' ),
    array( $baseline, 'round-ledger-apply.yml', 'release source policy does not reject temporary round ledger workflow' ),
    array( $baseline, 'tools/apply-round-ledger.py', 'release source policy does not reject temporary ledger applicator' ),
);
foreach ( $checks as $check ) { if ( false === strpos( $check[0], $check[1] ) ) { $fail[] = $check[2]; } }
foreach ( array( $baseline, $docs, $integration ) as $workflow ) {
    if ( false !== strpos( $workflow, 'shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240' ) ) { $fail[] = 'permanent workflow retains setup-php resolver-failing SHA form'; }
}
if ( $fail ) { fwrite( STDERR, "R329 current release invariants:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R329 release invariants PASS (' . count( $checks ) . ' assertions).' . PHP_EOL;
