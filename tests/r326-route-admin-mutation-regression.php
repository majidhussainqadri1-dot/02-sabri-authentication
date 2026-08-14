<?php
$root = dirname( __DIR__ );
$plugin = file_get_contents( $root . '/includes/class-sa-plugin.php' );
$admin = file_get_contents( $root . '/admin/account-settings.php' );
$access = file_get_contents( $root . '/includes/class-sa-access-control.php' );
$routes = file_get_contents( $root . '/includes/class-sauth-canonical-routes.php' );
$fail = array();
$checks = array(
    array( $plugin, "SAUTH_Operations::safe_mode()", 'settings mutation lacks Safe Mode gate' ),
    array( $plugin, "settings_rollback_failed", 'rollback-integrity error path missing' ),
    array( $plugin, "SAUTH_Operations::enter_safe_mode()", 'rollback failure does not enter centralized Safe Mode' ),
    array( $admin, "! SAUTH_Operations::safe_mode() && SA_Google_OAuth::configured()", 'admin readiness ignores Safe Mode' ),
    array( $access, "block_unmanaged_password_authentication", 'unmanaged password-authentication boundary missing' ),
    array( $routes, "X-Robots-Tag: noindex", 'canonical private route lost noindex header' ),
);
foreach ( $checks as $check ) { if ( false === strpos( $check[0], $check[1] ) ) { $fail[] = $check[2]; } }
if ( $fail ) { fwrite( STDERR, "R326 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R326 route/admin mutation regression PASS (6 assertions).' . PHP_EOL;
