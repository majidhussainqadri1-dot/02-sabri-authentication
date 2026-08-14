<?php
$root = dirname( __DIR__ );
$lock = json_decode( file_get_contents( $root . '/RELEASE-LOCK.json' ), true );
$architecture = file_get_contents( $root . '/tests/architecture-check.py' );
$integration = file_get_contents( $root . '/.github/workflows/file00-1.2.44-real-integration.yml' );
$status = file_get_contents( $root . '/STATUS.md' );
$manifest = file_get_contents( $root . '/RELEASE-MANIFEST.md' );
$fail = array();
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };

$req( is_array( $lock ), 'release lock is invalid' );
$req( '1.2.6' === (string) ( $lock['release_version'] ?? '' ) && '1.2.1' === (string) ( $lock['database_version'] ?? '' ) && '1.0.1' === (string) ( $lock['passkey_schema_version'] ?? '' ), 'current release/schema identity is stale' );
$req( empty( $lock['cross_file_blockers'] ?? array( 'missing' ) ), 'proven File 00 taxonomy/provider blocker remains open' );
$evidence = is_array( $lock['cross_file_integration_evidence'] ?? null ) ? $lock['cross_file_integration_evidence'] : array();
$req( 'repository_integration_green' === (string) ( $evidence['status'] ?? '' ), 'cross-file integration status is not green' );
$req( 31850253635 === (int) ( $evidence['workflow_run_id'] ?? 0 ), 'cross-file integration run ID is not the proven run' );
$req( 'f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9' === (string) ( $evidence['file02_sha'] ?? '' ), 'proven File 02 integration SHA is stale' );
$req( '1d7f215193d778b0977c8e50d738c42e1e5f66c2' === (string) ( $evidence['file00_sha'] ?? '' ), 'proven File 00 integration SHA is stale' );
$req( '1.2.44' === (string) ( $evidence['file00_runtime'] ?? '' ), 'proven File 00 runtime is stale' );
$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'repository closure falsely advances external gates' );

$req( false !== strpos( $architecture, 'LOCK = json.loads((ROOT / "RELEASE-LOCK.json")' ), 'architecture guard does not consume RELEASE-LOCK identity' );
$req( false !== strpos( $architecture, 'RELEASE_VERSION = str(LOCK.get("release_version"' ), 'architecture guard does not derive release version' );
$req( false !== strpos( $architecture, 'DB_VERSION = str(LOCK.get("database_version"' ), 'architecture guard does not derive DB version' );
$req( false === strpos( $architecture, 'Version: 1.2.1' ) && false === strpos( $architecture, "SAUTH_DB_VERSION', '1.2.0" ), 'architecture guard retains obsolete hard-coded release identity' );

foreach ( array(
    'FILE00_REF: 1d7f215193d778b0977c8e50d738c42e1e5f66c2',
    "FILE00_VERSION: '1.2.44'",
    "FILE02_VERSION: '1.2.6'",
    'Complete supported File 00 deferred administrator bootstrap',
    'Prove canonical account taxonomy parity on both runtimes',
    'Rehearse legacy 1.2.1 passkey-column upgrade on real MariaDB',
    'legacy-logical-session',
) as $marker ) { $req( false !== strpos( $integration, $marker ), 'current exact integration workflow is missing: ' . $marker ); }

$req( false !== strpos( $status, '31850253635' ) && false !== strpos( $status, 'closed at repository/integration level' ), 'status does not record exact repository integration closure' );
$req( false !== strpos( $manifest, '31850253635' ) && false !== strpos( $manifest, 'closed at repository/integration level' ), 'release manifest does not record exact repository integration closure' );
$req( file_exists( $root . '/review-evidence/R336-REVIEW-FROZEN.md' ), 'R336 frozen review evidence is missing' );

if ( $fail ) { fwrite( STDERR, "R336 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R336 post-integration release closure regression PASS (24 assertions).' . PHP_EOL;
