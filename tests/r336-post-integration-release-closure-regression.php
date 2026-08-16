<?php
$root = dirname( __DIR__ );
$lock = json_decode( file_get_contents( $root . '/RELEASE-LOCK.json' ), true );
$lineage = json_decode( file_get_contents( $root . '/SOURCE-LINEAGE-LOCK.json' ), true );
$architecture = file_get_contents( $root . '/tests/architecture-check.py' );
$integration = file_get_contents( $root . '/.github/workflows/file00-1.2.44-real-integration.yml' );
$status = file_get_contents( $root . '/STATUS.md' );
$manifest = file_get_contents( $root . '/RELEASE-MANIFEST.md' );
$fail = array();
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };

$req( is_array( $lock ), 'release lock is invalid' );
$req( '1.3.0' === (string) ( $lock['release_version'] ?? '' ) && '1.3.0' === (string) ( $lock['database_version'] ?? '' ) && '1.0.1' === (string) ( $lock['passkey_schema_version'] ?? '' ), 'recoverable release/schema identity is stale' );
$req( ! empty( $lock['cross_file_blockers'] ?? array() ), 'current File 02 integration/source-lineage blocker was falsely closed' );
$current = is_array( $lock['cross_file_integration_evidence'] ?? null ) ? $lock['cross_file_integration_evidence'] : array();
$prior = is_array( $lock['prior_cross_file_integration_evidence'] ?? null ) ? $lock['prior_cross_file_integration_evidence'] : array();
$req( 'pending_exact_head_review_source_only' === (string) ( $current['status'] ?? '' ), 'current cross-file integration status is not pending review-source exact head' );
$req( 'repository_integration_green' === (string) ( $prior['status'] ?? '' ), 'historical cross-file integration status was not retained' );
$req( 31850253635 === (int) ( $prior['workflow_run_id'] ?? 0 ), 'historical integration run ID is not the proven run' );
$req( 'f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9' === (string) ( $prior['file02_sha'] ?? '' ), 'historical File 02 integration SHA is stale' );
$req( '1d7f215193d778b0977c8e50d738c42e1e5f66c2' === (string) ( $current['file00_sha'] ?? '' ) && '1d7f215193d778b0977c8e50d738c42e1e5f66c2' === (string) ( $prior['file00_sha'] ?? '' ), 'File 00 exact pin is stale' );
$req( '1.2.44' === (string) ( $current['file00_runtime'] ?? '' ), 'current File 00 runtime pin is stale' );
$req( is_array( $lineage ) && false === ( $lineage['packaging_allowed'] ?? true ) && false === ( $lineage['staging_allowed'] ?? true ) && false === ( $lineage['deployment_allowed'] ?? true ), 'source-lineage release containment is not fail-closed' );
$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'repository work falsely advances external gates' );

$req( false !== strpos( $architecture, 'LOCK = json.loads((ROOT / "RELEASE-LOCK.json")' ), 'architecture guard does not consume RELEASE-LOCK identity' );
$req( false !== strpos( $architecture, 'RELEASE_VERSION = str(LOCK.get("release_version"' ), 'architecture guard does not derive release version' );
$req( false !== strpos( $architecture, 'DB_VERSION = str(LOCK.get("database_version"' ), 'architecture guard does not derive DB version' );
$req( false === strpos( $architecture, 'Version: 1.2.1' ) && false === strpos( $architecture, "SAUTH_DB_VERSION', '1.2.0" ), 'architecture guard retains obsolete hard-coded release identity' );

foreach ( array(
	'FILE00_REF: 1d7f215193d778b0977c8e50d738c42e1e5f66c2',
	"FILE00_VERSION: '1.2.44'",
	"FILE02_VERSION: '1.3.0'",
	"FILE02_DB_VERSION: '1.3.0'",
	'Complete supported File 00 deferred administrator bootstrap',
	'Prove canonical account taxonomy parity on both runtimes',
	'Rehearse legacy 1.2.1 passkey-column upgrade on real MariaDB',
	'legacy-logical-session',
	'Prove active-router one-way legacy migration on real MariaDB',
) as $marker ) {
	$req( false !== strpos( $integration, $marker ), 'current exact integration workflow is missing: ' . $marker );
}

$req( false !== strpos( $status, 'Historical paired evidence' ) && false !== strpos( $status, 'not yet exact-head proven' ) && false !== strpos( $status, 'does not prove this branch' ), 'status does not separate historical and current integration truth' );
$req( false !== strpos( $manifest, 'Historical run `31850253635`' ) && false !== strpos( $manifest, 'does not prove the current R338 branch' ) && false !== strpos( $manifest, 'cannot') && false !== strpos( $manifest, 'source-lineage blocker' ), 'source-review manifest does not separate historical/current integration from the lineage blocker' );
$req( file_exists( $root . '/review-evidence/R336-REVIEW-FROZEN.md' ), 'R336 frozen review evidence is missing' );

if ( $fail ) { fwrite( STDERR, "R336 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R336 historical-integration/source-lineage evidence-preservation PASS.' . PHP_EOL;
