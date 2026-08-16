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
$req( false !== strpos( $main, 'Version: 1.2.6' ) && false !== strpos( $main, "SAUTH_VERSION', '1.2.6" ), 'runtime identity not 1.2.6' );
$req( false !== strpos( $main, "SAUTH_DB_VERSION', '1.2.1" ), 'DB identity changed during R334' );
$req( false !== strpos( $passkeys, "const SCHEMA_VERSION        = '1.0.1'" ), 'passkey schema identity changed during R334' );
$req( false !== strpos( $main, "SAUTH_PASSKEY_CONTRACT_VERSION', '1.0.0" ), 'passkey assurance contract changed during R334' );
$req( is_array( $lock ) && '1.2.6' === ( $lock['release_version'] ?? '' ), 'release lock runtime stale' );
$req( 'review/file02-r337-fresh-audit-2026-08-16' === ( $lock['candidate_branch'] ?? '' ), 'release lock branch stale' );
$integration_evidence = is_array( $lock['cross_file_integration_evidence'] ?? null ) ? $lock['cross_file_integration_evidence'] : array();
$req( empty( $lock['cross_file_blockers'] ) && 31850253635 === (int) ( $integration_evidence['workflow_run_id'] ?? 0 ), 'historical cross-file blocker closure lacks exact successful integration evidence' );
$req( 'historical_pre_r337' === (string) ( $integration_evidence['evidence_scope'] ?? '' ), 'historical paired integration is not scoped to its exact pre-R337 source inputs' );
$req( true === ( $integration_evidence['current_r337_revalidation_required'] ?? false ), 'current R337 paired revalidation is not required' );
$req( 'f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9' === (string) ( $integration_evidence['file02_sha'] ?? '' ), 'paired File 02 historical integration SHA evidence stale' );
$req( '1d7f215193d778b0977c8e50d738c42e1e5f66c2' === (string) ( $integration_evidence['file00_sha'] ?? '' ), 'paired File 00 integration SHA evidence stale' );
$req( false === ( $lock['status']['staging_accepted'] ?? true ) && false === ( $lock['status']['live_deployed'] ?? true ) && false === ( $lock['status']['operational'] ?? true ), 'external completion falsely advanced' );
$req( false !== strpos( $integration, "FILE00_VERSION: '1.2.44'" ) && false !== strpos( $integration, "FILE02_VERSION: '1.2.6'" ), 'paired integration identities stale' );
$req( false !== strpos( $integration, '1d7f215193d778b0977c8e50d738c42e1e5f66c2' ), 'File 00 exact integration pin stale' );
$req( file_exists( $root . '/review-evidence/R334-REVIEW-FROZEN.md' ), 'R334 frozen review evidence missing' );
if ( $fail ) { fwrite( STDERR, "R334 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R334 passkey dbDelta migration invariants PASS (22 assertions).' . PHP_EOL;
