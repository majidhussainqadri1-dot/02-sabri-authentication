<?php
$root = dirname( __DIR__ );
$passkeys = file_get_contents( $root . '/includes/class-sauth-passkeys.php' );
$main = file_get_contents( $root . '/sabri-authentication.php' );
$lock = json_decode( file_get_contents( $root . '/RELEASE-LOCK.json' ), true );
$integration = file_get_contents( $root . '/.github/workflows/file00-1.2.44-real-integration.yml' );
$fail = array();
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };
$req( false === strpos( $passkeys, 'PRIMARY KEY  (id), UNIQUE KEY public_id' ), 'combined dbDelta key-definition line remains' );
foreach ( array(
    "\n\t\t\tPRIMARY KEY  (id),\n",
    "\n\t\t\tUNIQUE KEY public_id (public_id),\n",
    "\n\t\t\tUNIQUE KEY credential_lookup_hash (credential_lookup_hash),\n",
    "\n\t\t\tKEY user_status (user_id,status),\n",
    "\n\t\t\tKEY revoked_at (revoked_at)\n",
) as $line ) { $req( false !== strpos( $passkeys, $line ), 'dbDelta-compatible passkey index line missing: ' . trim( $line ) ); }
$req( false !== strpos( $main, 'Version: 1.3.3' ) && false !== strpos( $main, "SAUTH_VERSION', '1.3.3" ), 'runtime identity not 1.3.3' );
$req( false !== strpos( $main, "SAUTH_DB_VERSION', '1.3.0" ), 'current DB identity is stale' );
$req( false !== strpos( $passkeys, "const SCHEMA_VERSION        = '1.0.1'" ), 'passkey schema identity changed during later runtime hotfix' );
$req( false !== strpos( $main, "SAUTH_PASSKEY_CONTRACT_VERSION', '1.0.0" ), 'passkey assurance contract changed during later runtime hotfix' );
$req( is_array( $lock ) && '1.3.3' === ( $lock['release_version'] ?? '' ), 'release lock runtime stale' );
$req( 'fix/file02-passkey-assurance-cycle-1.3.3-2026-08-19' === ( $lock['candidate_branch'] ?? '' ), 'release lock branch stale' );
$integration_evidence = is_array( $lock['cross_file_integration_evidence'] ?? null ) ? $lock['cross_file_integration_evidence'] : array();
$prior_evidence = is_array( $lock['prior_cross_file_integration_evidence'] ?? null ) ? $lock['prior_cross_file_integration_evidence'] : array();
$pre_route_evidence = is_array( $lock['pre_route_fix_cross_file_integration_evidence'] ?? null ) ? $lock['pre_route_fix_cross_file_integration_evidence'] : array();
$req( in_array( (string) ( $integration_evidence['status'] ?? '' ), array( 'repository_integration_green_on_pre_release_identity_head', 'repository_integration_green', 'pending_exact_head' ), true ), 'current exact-head integration evidence status is invalid' );
$req( '1.3.3' === (string) ( $integration_evidence['file02_runtime'] ?? '' ), 'current exact-head integration runtime identity is stale' );
$req( 'f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9' === (string) ( $prior_evidence['file02_sha'] ?? '' ), 'historical paired File 02 evidence was not preserved' );
$req( '1d7f215193d778b0977c8e50d738c42e1e5f66c2' === (string) ( $prior_evidence['file00_sha'] ?? '' ), 'paired File 00 integration pin stale' );
$req( 31953732443 === (int) ( $pre_route_evidence['workflow_run_id'] ?? 0 ) && '1.3.1' === (string) ( $pre_route_evidence['file02_runtime'] ?? '' ), 'pre-route-fix R338 integration evidence was not preserved' );
$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'external completion falsely advanced' );
$req( false !== strpos( $integration, "FILE00_VERSION: '1.2.44'" ) && false !== strpos( $integration, "FILE02_VERSION: '1.3.3'" ), 'paired integration identities stale' );
$req( false !== strpos( $integration, '1d7f215193d778b0977c8e50d738c42e1e5f66c2' ), 'File 00 exact integration pin stale' );
$req( false !== strpos( $integration, 'Prove File 02 resolves exact File 00 canonical membership routes' ), 'R339 route proof missing from exact integration' );
$req( file_exists( $root . '/review-evidence/R334-REVIEW-FROZEN.md' ), 'R334 frozen review evidence missing' );
if ( $fail ) { fwrite( STDERR, "R334 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R334 passkey dbDelta migration invariants PASS (23 assertions).' . PHP_EOL;
