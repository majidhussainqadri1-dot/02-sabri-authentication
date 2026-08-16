<?php
$root = dirname( __DIR__ );
$lock = json_decode( file_get_contents( $root . '/RELEASE-LOCK.json' ), true );
$architecture = file_get_contents( $root . '/tests/architecture-check.py' );
$integration = file_get_contents( $root . '/.github/workflows/file00-1.2.44-real-integration.yml' );
$status = file_get_contents( $root . '/STATUS.md' );
$manifest = file_get_contents( $root . '/RELEASE-MANIFEST.md' );
$adapter = file_get_contents( $root . '/includes/class-sa-membership-adapter.php' );
$fail = array();
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };

$req( is_array( $lock ), 'release lock is invalid' );
$req( '1.3.2' === (string) ( $lock['release_version'] ?? '' ) && '1.3.0' === (string) ( $lock['database_version'] ?? '' ) && '1.0.1' === (string) ( $lock['passkey_schema_version'] ?? '' ), 'current release/schema identity is stale' );
$current = is_array( $lock['cross_file_integration_evidence'] ?? null ) ? $lock['cross_file_integration_evidence'] : array();
$prior = is_array( $lock['prior_cross_file_integration_evidence'] ?? null ) ? $lock['prior_cross_file_integration_evidence'] : array();
$pre_route = is_array( $lock['pre_route_fix_cross_file_integration_evidence'] ?? null ) ? $lock['pre_route_fix_cross_file_integration_evidence'] : array();
$req( in_array( (string) ( $current['status'] ?? '' ), array( 'repository_integration_green_on_pre_release_identity_head', 'repository_integration_green', 'pending_exact_head' ), true ), 'current cross-file integration status is invalid' );
$req( 'repository_integration_green' === (string) ( $pre_route['status'] ?? '' ) && 31953732443 === (int) ( $pre_route['workflow_run_id'] ?? 0 ), 'pre-route-fix File 02 1.3.1 integration evidence was not retained' );
$req( 'repository_integration_green' === (string) ( $prior['status'] ?? '' ), 'historical cross-file integration status was not retained' );
$req( 31850253635 === (int) ( $prior['workflow_run_id'] ?? 0 ), 'historical integration run ID is not the proven run' );
$req( 'f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9' === (string) ( $prior['file02_sha'] ?? '' ), 'historical File 02 integration SHA is stale' );
$req( '1d7f215193d778b0977c8e50d738c42e1e5f66c2' === (string) ( $current['file00_sha'] ?? '' ) && '1d7f215193d778b0977c8e50d738c42e1e5f66c2' === (string) ( $prior['file00_sha'] ?? '' ), 'File 00 exact pin is stale' );
$req( '1.2.44' === (string) ( $current['file00_runtime'] ?? '' ), 'current File 00 runtime pin is stale' );
$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'repository work falsely advances external gates' );
$req( false === ( $lock['live_1_3_0_activation_incident']['live_resolution_claimed'] ?? true ), 'passkey live incident was falsely marked resolved before deployment/retest' );
$req( false === ( $lock['live_1_3_1_membership_route_contract_incident']['live_resolution_claimed'] ?? true ), 'route-contract live incident was falsely marked resolved before deployment/retest' );
$req( false === ( $lock['live_1_3_1_membership_route_contract_incident']['exact_deployed_file02_source_parity_verified'] ?? true ), 'exact deployed File02 parity was falsely claimed for the route incident' );

$req( false !== strpos( $architecture, 'LOCK = json.loads((ROOT / "RELEASE-LOCK.json")' ), 'architecture guard does not consume RELEASE-LOCK identity' );
$req( false !== strpos( $architecture, 'RELEASE_VERSION = str(LOCK.get("release_version"' ), 'architecture guard does not derive release version' );
$req( false !== strpos( $architecture, 'DB_VERSION = str(LOCK.get("database_version"' ), 'architecture guard does not derive DB version' );
$req( false === strpos( $architecture, 'Version: 1.2.1' ) && false === strpos( $architecture, "SAUTH_DB_VERSION', '1.2.0" ), 'architecture guard retains obsolete hard-coded release identity' );

foreach ( array(
	'FILE00_REF: 1d7f215193d778b0977c8e50d738c42e1e5f66c2',
	"FILE00_VERSION: '1.2.44'",
	'FILE00_LIVE_ROUTE_REF: c4ab298b3ba2b870d507d32b36b1b4afd2771621',
	"FILE02_VERSION: '1.3.2'",
	"FILE02_DB_VERSION: '1.3.0'",
	'Complete supported File 00 deferred administrator bootstrap',
	'Prove material File 00 managed-page contract',
	'Prove File 02 resolves exact File 00 canonical membership routes',
	'Prove canonical account taxonomy parity on both runtimes',
	'Rehearse legacy 1.2.1 passkey-column upgrade on real MariaDB',
	'Rehearse exact deployed stale passkey user_status index on real MariaDB',
	'legacy-logical-session',
	'Prove active-router one-way legacy migration on real MariaDB',
) as $marker ) {
	$req( false !== strpos( $integration, $marker ), 'current exact integration workflow is missing: ' . $marker );
}

$req( false !== strpos( $adapter, "MEMBERSHIP_APPLICATION_KEY  = 'application'" ) && false !== strpos( $adapter, "MEMBERSHIP_SECURITY_KEY     = 'security'" ) && false !== strpos( $adapter, "MEMBERSHIP_STATUS_KEY       = 'status'" ), 'adapter no longer reflects File00 canonical route keys' );
$req( false === strpos( $adapter, 'sabri_profile' ) && false === strpos( $adapter, 'sabri_security_center' ) && false === strpos( $adapter, 'sabri_verification_status' ), 'adapter retains invented File00 membership route keys' );
$req( false !== strpos( $status, 'Historical paired evidence' ), 'status no longer separates historical integration evidence' );
$req( false !== strpos( $manifest, 'Historical run `31850253635`' ), 'release manifest no longer separates historical integration evidence' );
$req( file_exists( $root . '/review-evidence/R336-REVIEW-FROZEN.md' ), 'R336 frozen review evidence is missing' );
$req( file_exists( $root . '/tests/r339-file00-canonical-route-contract-regression.php' ), 'R339 route regression is missing' );

if ( $fail ) { fwrite( STDERR, "R336 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R336 post-integration evidence-preservation regression PASS.' . PHP_EOL;
