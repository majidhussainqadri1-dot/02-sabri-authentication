<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/sabri-authentication.php' );
$lock = file_get_contents( $root . '/RELEASE-LOCK.json' );
$readme = file_get_contents( $root . '/readme.txt' );
$status = file_get_contents( $root . '/STATUS.md' );
$manifest = file_get_contents( $root . '/RELEASE-MANIFEST.md' );
$lineage = file_get_contents( $root . '/SOURCE-LINEAGE-LOCK.json' );
$baseline = file_get_contents( $root . '/.github/workflows/baseline-integrity.yml' );
$docs = file_get_contents( $root . '/.github/workflows/canonical-storage-and-docs.yml' );
$integration = file_get_contents( $root . '/.github/workflows/file00-1.2.44-real-integration.yml' );
$review = file_get_contents( $root . '/.github/workflows/review-branch-integrity.yml' );
$architecture = file_get_contents( $root . '/ARCHITECTURE.md' );
$fail = array();
$checks = array(
    array( $main, 'Version: 1.3.0', 'plugin header recoverable runtime identity stale' ),
    array( $main, "SAUTH_VERSION', '1.3.0", 'runtime identity stale' ),
    array( $main, "SAUTH_DB_VERSION', '1.3.0", 'DB identity unexpectedly changed' ),
    array( $lock, '"release_version": "1.3.0"', 'release lock recoverable runtime stale' ),
    array( $lock, 'review/file02-r338-fresh-review-fix-2026-08-16', 'release lock branch stale' ),
    array( $lock, '"packaging_authorized": false', 'release lock no longer blocks package authorization' ),
    array( $readme, 'Stable tag: 1.3.0', 'WordPress stable tag stale for recoverable source marker' ),
    array( $status, 'Recoverable Runtime Marker 1.3.0', 'status recoverable runtime identity stale' ),
    array( $status, 'Packaged | **BLOCKED**', 'status no longer blocks package creation' ),
    array( $manifest, 'Recoverable Runtime Marker 1.3.0', 'source-review manifest identity stale' ),
    array( $lineage, '"latest_approved_reviewed_runtime_evidence": "1.3.8"', 'later approved source-lineage evidence missing' ),
    array( $lineage, '"packaging_allowed": false', 'lineage lock no longer blocks packaging' ),
    array( $baseline, "RELEASE_VERSION: '1.3.0'", 'historical baseline workflow version stale' ),
    array( $baseline, 'tests/r33*-regression.php', 'baseline workflow omits final R33x regressions' ),
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
echo 'R329 release/source-lineage invariants PASS (' . count( $checks ) . ' assertions).' . PHP_EOL;
