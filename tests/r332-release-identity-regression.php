<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/sabri-authentication.php' );
$registration = file_get_contents( $root . '/includes/class-sa-registration.php' );
$lock = json_decode( file_get_contents( $root . '/RELEASE-LOCK.json' ), true );
$readme = file_get_contents( $root . '/readme.txt' );
$status = file_get_contents( $root . '/STATUS.md' );
$baseline = file_get_contents( $root . '/.github/workflows/baseline-integrity.yml' );
$docs = file_get_contents( $root . '/.github/workflows/canonical-storage-and-docs.yml' );
$integration = file_get_contents( $root . '/.github/workflows/file00-1.2.44-real-integration.yml' );
$fail = array();
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };
$req( false !== strpos( $main, 'Version: 1.3.0' ) && false !== strpos( $main, "SAUTH_VERSION', '1.3.0" ), 'runtime identity not 1.3.0' );
$req( false !== strpos( $main, "SAUTH_DB_VERSION', '1.3.0" ), 'DB identity not 1.3.0' );
$req( is_array( $lock ) && '1.3.0' === ( $lock['release_version'] ?? '' ), 'release lock not 1.3.0' );
$req( '1.3.0' === ( $lock['database_version'] ?? '' ) && '1.0.1' === ( $lock['passkey_schema_version'] ?? '' ), 'schema identities stale' );
$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'external completion falsely advanced' );
$req( false !== strpos( $readme, 'Stable tag: 1.3.0' ) && false !== strpos( $readme, '= 1.3.0 =' ) && false !== strpos( $readme, '= 1.2.3 =' ), 'WordPress readme identity/history invalid' );
$req( false !== strpos( $status, 'Version 1.3.0' ), 'status identity stale' );
$req( false !== strpos( $baseline, "RELEASE_VERSION: '1.3.0'" ), 'baseline workflow identity stale' );
$req( false !== strpos( $docs, "'release_version':'1.3.0'" ), 'docs workflow identity stale' );
$req( false !== strpos( $integration, "FILE02_VERSION: '1.3.0'" ) && false !== strpos( $integration, "FILE00_VERSION: '1.2.44'" ), 'real integration paired identity stale' );
$req( false !== strpos( $integration, '1d7f215193d778b0977c8e50d738c42e1e5f66c2' ), 'real integration File00 exact pin stale' );
$req( false !== strpos( $registration, "'patient'" ) && false !== strpos( $registration, "'pharmacy'" ) && false !== strpos( $registration, "'clinic'" ) && false !== strpos( $registration, "'publisher'" ), 'canonical account choices incomplete' );
$req( file_exists( $root . '/review-evidence/R332-REVIEW-FROZEN.md' ), 'R332 frozen review evidence missing' );
$req( ! file_exists( $root . '/.github/workflows/tmp-account-taxonomy-parity-apply.yml' ), 'temporary taxonomy workflow remains' );
if ( $fail ) { fwrite( STDERR, "R332 current invariants:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R332 release identity invariants PASS (14 assertions).' . PHP_EOL;
