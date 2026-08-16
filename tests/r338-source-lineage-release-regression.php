<?php
$root = dirname( __DIR__ );
$lineage = json_decode( file_get_contents( $root . '/SOURCE-LINEAGE-LOCK.json' ), true );
$release = json_decode( file_get_contents( $root . '/RELEASE-LOCK.json' ), true );
$main = file_get_contents( $root . '/sabri-authentication.php' );
$adapter = file_get_contents( $root . '/includes/class-sa-membership-adapter.php' );
$activator = file_get_contents( $root . '/includes/class-sa-activator.php' );
$access = file_get_contents( $root . '/includes/class-sa-access-control.php' );
$readme = file_get_contents( $root . '/readme.txt' );
$sbom = json_decode( file_get_contents( $root . '/SBOM.spdx.json' ), true );
$builder = file_get_contents( $root . '/tools/build-package.sh' );
$routes = file_get_contents( $root . '/includes/class-sauth-canonical-routes.php' );
$passkeys = file_get_contents( $root . '/includes/class-sauth-passkeys.php' );
$fail = array();
$req = static function ( $ok, $message ) use ( &$fail ) { if ( ! $ok ) { $fail[] = $message; } };

$req( is_array( $lineage ), 'source-lineage lock is invalid JSON' );
$req( false === ( $lineage['exact_1_3_8_source_bytes_recovered'] ?? true ), 'unrecovered exact 1.3.8 source is falsely marked recovered' );
$req( false === ( $lineage['current_source_has_complete_x24_scope'] ?? true ), 'current source is falsely marked X24 complete' );
$req( false === ( $lineage['packaging_allowed'] ?? true ) && false === ( $lineage['staging_allowed'] ?? true ) && false === ( $lineage['deployment_allowed'] ?? true ), 'lineage block does not fail closed across package/staging/deploy' );
$req( '1.3.8' === (string) ( $lineage['latest_approved_reviewed_runtime_evidence'] ?? '' ), 'approved reviewed runtime lineage evidence is stale' );
$req( '1.1.0' === (string) ( $lineage['latest_approved_reviewed_passkey_schema_evidence'] ?? '' ), 'approved reviewed passkey schema evidence is stale' );
$req( '9e93cc0282ec407a7efc5bb37559a6d6e87f9e20c9e9453e33b338e49f494e2f' === (string) ( $lineage['known_historical_1_3_8_installable_zip_sha256'] ?? '' ), 'approved historical 1.3.8 ZIP hash evidence is stale' );
$req( "const MIN_VERSION     = '1.2.44';" !== '' && false !== strpos( $adapter, "const MIN_VERSION     = '1.2.44';" ), 'File 00 minimum is not 1.2.44' );
$req( false !== strpos( $main, 'Requires at least: 6.4' ) && false !== strpos( $readme, 'Requires at least: 6.4' ), 'WordPress minimum is not 6.4' );
$req( false !== strpos( $main, 'Membership Core 1.2.44+' ) && false !== strpos( $activator, 'Membership Core 1.2.44 or later' ), 'File 00 activation diagnostics are stale' );
$req( false !== strpos( $access, "'confirm_admin_email'" ), 'WordPress confirm_admin_email is still intercepted' );
$req( false !== strpos( $builder, 'Packaging blocked by SOURCE-LINEAGE-LOCK.json' ), 'builder does not enforce source-lineage package block' );
$req( 'review/file02-r338-fresh-review-fix-2026-08-16' === (string) ( $release['candidate_branch'] ?? '' ), 'release lock branch is stale' );
$req( false === ( $release['packaging_authorized'] ?? true ) && false === ( $release['approved_scope_source_complete'] ?? true ), 'release lock overclaims packaging/source completeness' );

$required_x24_files = array(
    'includes/class-sauth-modern-auth.php',
    'includes/class-sauth-security-orchestrator.php',
    'includes/class-sauth-shared-signals.php',
    'includes/class-sauth-password-safety.php',
    'includes/class-sauth-dpop.php',
    'includes/class-sauth-fido-trust.php',
);
$missing = array_filter( $required_x24_files, static function ( $path ) use ( $root ) { return ! file_exists( $root . '/' . $path ); } );
$approved_routes_missing = false === strpos( $routes, '/account-security/' ) || false === strpos( $routes, '/resolve-account/' );
$approved_passkey_schema_missing = false === strpos( $passkeys, "SCHEMA_VERSION        = '1.1.0'" );
$x24_incomplete = ! empty( $missing ) || $approved_routes_missing || $approved_passkey_schema_missing;
$req( $x24_incomplete, 'R338 lineage lock is stale: source now appears X24-complete and requires a new full reconciliation review before unblocking' );
$req( false === ( $lineage['packaging_allowed'] ?? true ), 'known-incomplete X24 source is package-authorized' );

$wp_ok = false;
foreach ( (array) ( $sbom['packages'] ?? array() ) as $pkg ) {
    if ( 'SPDXRef-Package-WordPress' === (string) ( $pkg['SPDXID'] ?? '' ) && 0 === strpos( (string) ( $pkg['versionInfo'] ?? '' ), '6.4-or-later' ) ) { $wp_ok = true; }
}
$req( $wp_ok, 'SBOM WordPress dependency is stale' );

if ( $fail ) { fwrite( STDERR, "R338 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R338 source-lineage/dependency/release containment PASS.' . PHP_EOL;
