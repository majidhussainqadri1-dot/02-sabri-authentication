<?php
$root = dirname( __DIR__ );
$routes = file_get_contents( $root . '/includes/class-sauth-canonical-routes.php' );
$access = file_get_contents( $root . '/includes/class-sa-access-control.php' );
$security = file_get_contents( $root . '/includes/class-sa-security.php' );
$notice = file_get_contents( $root . '/templates/partials/notice.php' );
$admin = file_get_contents( $root . '/admin/account-settings.php' );
$css = file_get_contents( $root . '/assets/css/authentication.css' );
$login = file_get_contents( $root . '/templates/login.php' );
$fail = array();
$checks = array(
    array( $routes, 'Runtime/schema version markers are intentionally not written here', 'canonical-route init can forge migration version markers' ),
    array( $routes, "get_option( 'sauth_page_map', get_option( 'sa_page_map', array() ) )", 'legacy session redirect ignores canonical page map' ),
    array( $access, 'SAUTH_Canonical_Routes::SESSIONS === (string) get_query_var', 'canonical sessions route is not recognized as private File 02 surface' ),
    array( $security, "'sa_sig'", 'server notice URLs are unsigned' ),
    array( $notice, 'SA_Security::notice_valid', 'user query strings can forge authoritative notices' ),
    array( $security, 'public static function master_key_ready()', 'Google secret encryption does not require a dedicated key' ),
    array( $security, "'v3:'", 'new Google secrets are not versioned to dedicated-key ciphertext' ),
    array( $admin, 'updated_token', 'admin settings success is forgeable from updated=1' ),
    array( $admin, 'Retired File 00 authenticator/recovery codes are not File 02 authentication factors.', 'admin ownership copy is stale' ),
    array( $css, 'min-height:44px', '44px touch-target gate is absent' ),
    array( $css, 'min-height:44px;padding:10px 4px', 'checkbox label touch target remains undersized' ),
    array( $login, 'maxlength="320"', 'login identifier client bound is missing' ),
    array( $login, 'maxlength="4096"', 'login password client bound is missing' ),
);
foreach ( $checks as $c ) { if ( false === strpos( $c[0], $c[1] ) ) { $fail[] = $c[2]; } }
if ( $fail ) { fwrite( STDERR, "R308 regressions:\n- " . implode( "\n- ", $fail ) . "\n" ); exit( 1 ); }
echo 'R308 route/UI regression PASS (' . count( $checks ) . " assertions).\n";
