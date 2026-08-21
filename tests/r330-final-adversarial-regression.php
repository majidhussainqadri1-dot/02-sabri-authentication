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
$adapter = file_get_contents( $root . '/includes/class-sa-membership-adapter.php' );
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };
$req( ! file_exists( $root . '/.github/workflows/round-ledger-apply.yml' ), 'temporary round-ledger workflow remains' );
$req( ! file_exists( $root . '/tools/apply-round-ledger.py' ), 'temporary round-ledger applicator remains' );
$req( false !== strpos( $review, 'round-ledger-apply.yml' ) && false !== strpos( $review, 'tools/apply-round-ledger.py' ), 'review integrity gate does not reject temporary round-ledger machinery' );
$req( false !== strpos( $baseline, 'round-ledger-apply.yml' ) && false !== strpos( $baseline, 'tools/apply-round-ledger.py' ), 'release integrity gate does not reject temporary round-ledger machinery' );
$req( false !== strpos( $baseline, 'tests/r330-final-adversarial-regression.php' ), 'release constitution does not require R330 final regression' );
$req( false !== strpos( $baseline, 'tests/r339-file00-canonical-route-contract-regression.php' ), 'release constitution does not require R339 route regression' );
$req( false !== strpos( $baseline, 'tests/r340-passkey-assurance-cycle-regression.php' ), 'release constitution does not require R340 passkey-assurance cycle regression' );
$req( false !== strpos( $main, 'Version: 1.3.4' ) && false !== strpos( $main, "SAUTH_VERSION', '1.3.4" ), 'current runtime release identity is not synchronized' );
$req( false !== strpos( $main, "SAUTH_DB_VERSION', '1.3.0" ), 'DB identity is not synchronized after later corrective work' );
$req( false !== strpos( $adapter, "MEMBERSHIP_APPLICATION_KEY  = 'application'" ) && false !== strpos( $adapter, "MEMBERSHIP_SECURITY_KEY     = 'security'" ) && false !== strpos( $adapter, "MEMBERSHIP_STATUS_KEY       = 'status'" ), 'File00 canonical membership route contract is not synchronized' );
$req( false === strpos( $adapter, 'sabri_profile' ) && false === strpos( $adapter, 'sabri_security_center' ) && false === strpos( $adapter, 'sabri_verification_status' ), 'invented File00 membership route keys remain' );

/* R330 is a permanent historical regression. Later corrective rounds may
 * legitimately advance the release-line label, so this guard proves the
 * line has not moved backwards instead of pinning an obsolete R335 string. */
$coded = is_array( $lock ) ? (string) ( $lock['status']['coded'] ?? '' ) : '';
$review_line = is_array( $lock ) ? (string) ( $lock['review_line'] ?? '' ) : '';
$coded_round = preg_match( '/r(\d+)/i', $coded, $coded_match ) ? (int) $coded_match[1] : 0;
$review_round = preg_match( '/R(\d+)/', $review_line, $review_match ) ? (int) $review_match[1] : 0;
$req( $coded_round >= 340, 'release lock coded status regressed below the R340 corrective baseline' );
$req( $review_round >= 340, 'release lock review line regressed below the R340 corrective baseline' );

$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'later corrective work falsely advances external completion gates' );
foreach ( array( $readme, $status, $manifest, $changelog, $report ) as $evidence ) { $req( false !== strpos( $evidence, '1.3.0' ), 'release-facing evidence lost the R337 1.3.0 base history' ); }
$wordpress_readme = file_get_contents( $root . '/readme.txt' );
$req( false !== strpos( $wordpress_readme, 'Stable tag: 1.3.4' ), 'current WordPress release identity is not 1.3.4' );
$req( false !== strpos( $wordpress_readme, '= 1.3.3 =' ), 'WordPress changelog does not record 1.3.3' );
$req( false !== strpos( $wordpress_readme, '= 1.3.2 =' ), 'WordPress changelog lost 1.3.2 history' );
$req( false !== strpos( $status, 'Live-Deployed | No' ), 'status must continue to deny live-deployed completion' );
if ( $fail ) { fwrite( STDERR, "R330 invariant regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R330 final adversarial invariants PASS (23 assertions).' . PHP_EOL;
