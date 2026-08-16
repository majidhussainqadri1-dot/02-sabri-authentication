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
$req( false !== strpos( $main, 'Version: 1.2.6' ) && false !== strpos( $main, "SAUTH_VERSION', '1.2.6" ), 'current runtime release identity is not synchronized' );
$req( false !== strpos( $main, "SAUTH_DB_VERSION', '1.2.1" ), 'DB identity changed after R330' );

/* R330 is a permanent historical regression. Later corrective rounds may
 * legitimately advance both the release-line label and its wording. Extract
 * the round from either historical `rNNN_corrected` or current
 * `post_rNNN_corrective_source` forms instead of pinning obsolete copy. */
$coded = is_array( $lock ) ? (string) ( $lock['status']['coded'] ?? '' ) : '';
$review_line = is_array( $lock ) ? (string) ( $lock['review_line'] ?? '' ) : '';
$coded_round = 0;
if ( preg_match( '/(?:^|_)r(\d+)(?:_corrected|_corrective_source)$/i', $coded, $coded_match ) ) {
    $coded_round = (int) $coded_match[1];
}
$review_round = preg_match( '/R331-R(\d+)-corrective$/', $review_line, $review_match ) ? (int) $review_match[1] : 0;
$req( $coded_round >= 335, 'release lock coded status regressed below the R335 corrective baseline' );
$req( $review_round >= 335, 'release lock review line regressed below the R335 corrective baseline' );

$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'later corrective work falsely advances external completion gates' );
foreach ( array( $readme, $status, $manifest, $changelog, $report ) as $evidence ) { $req( false !== strpos( $evidence, '1.2.6' ), 'release-facing evidence is not synchronized to the current runtime identity' ); }
$req( false !== strpos( $status, 'Live-Deployed | No' ), 'status must continue to deny live-deployed completion' );
if ( $fail ) { fwrite( STDERR, "R330 invariant regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R330 final adversarial invariants PASS (16 assertions).' . PHP_EOL;
