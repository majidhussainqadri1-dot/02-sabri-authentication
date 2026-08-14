<?php
$root = dirname( __DIR__ );
$fail = array();
$main = file_get_contents( $root . '/sabri-authentication.php' );
$lock = json_decode( file_get_contents( $root . '/RELEASE-LOCK.json' ), true );
$readme = file_get_contents( $root . '/README.md' );
$status = file_get_contents( $root . '/STATUS.md' );
$manifest = file_get_contents( $root . '/RELEASE-MANIFEST.md' );
$changelog = file_get_contents( $root . '/CHANGELOG.md' );
$report = file_get_contents( $root . '/REVIEW-REPORT.md' );
$baseline = file_get_contents( $root . '/.github/workflows/baseline-integrity.yml' );
$review = file_get_contents( $root . '/.github/workflows/review-branch-integrity.yml' );
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };
$req( ! file_exists( $root . '/.github/workflows/round-ledger-apply.yml' ), 'temporary round-ledger workflow remains' );
$req( ! file_exists( $root . '/tools/apply-round-ledger.py' ), 'temporary round-ledger applicator remains' );
$req( false !== strpos( $review, 'round-ledger-apply.yml' ) && false !== strpos( $review, 'tools/apply-round-ledger.py' ), 'review integrity gate does not reject temporary round-ledger machinery' );
$req( false !== strpos( $baseline, 'round-ledger-apply.yml' ) && false !== strpos( $baseline, 'tools/apply-round-ledger.py' ), 'release integrity gate does not reject temporary round-ledger machinery' );
$req( false !== strpos( $baseline, 'tests/r330-final-adversarial-regression.php' ), 'release constitution does not require R330 final regression' );
$req( false !== strpos( $main, 'Version: 1.2.4' ) && false !== strpos( $main, "SAUTH_VERSION', '1.2.4" ), 'current runtime release identity is not synchronized' );
$req( false !== strpos( $main, "SAUTH_DB_VERSION', '1.2.1" ), 'DB identity changed after R330' );
$req( is_array( $lock ) && 'taxonomy_parity_candidate_r332_corrected' === ( $lock['status']['coded'] ?? '' ), 'release lock coded status is not synchronized to the post-R330 corrective line' );
$req( 'R331-R332-corrective' === ( $lock['review_line'] ?? '' ), 'release lock review line is not synchronized to the post-R330 corrective line' );
$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'later corrective work falsely advances external completion gates' );
foreach ( array( $readme, $status, $manifest, $changelog, $report ) as $evidence ) { $req( false !== strpos( $evidence, '1.2.4' ), 'release-facing evidence is not synchronized to the current runtime identity' ); }
$req( false !== strpos( $status, 'Live-Deployed | No' ), 'status must continue to deny live-deployed completion' );
if ( $fail ) { fwrite( STDERR, "R330 invariant regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R330 final adversarial invariants PASS (16 assertions).' . PHP_EOL;
