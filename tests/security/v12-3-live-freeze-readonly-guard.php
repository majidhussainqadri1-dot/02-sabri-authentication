<?php
/**
 * V12.3 P00 operator snapshot source guard. No live execution claimed.
 */
declare(strict_types=1);
$root = dirname( __DIR__, 2 );
$file = $root . '/tools/file02-live-reality-freeze-readonly.php';
$src = file_get_contents( $file );
if ( ! is_string( $src ) ) { fwrite( STDERR, "FAIL missing freeze helper\n" ); exit( 1 ); }
$must = array(
    'operator opt-in' => "SAUTH_FREEZE_READONLY",
    'explicit authorization' => "'AUTHORIZED'",
    'CLI-only guard' => "WP_CLI",
    'source byte evidence' => "hash_file( 'sha256'",
    'tree digest' => "tree_sha256",
    'version snapshot' => "'sauth_db_version'",
    'passkey version snapshot' => "'sauth_passkey_schema_version'",
    'File00 DB' => "'smc_db_version'",
    'column metadata' => "SHOW COLUMNS FROM",
    'index metadata' => "SHOW INDEX FROM",
    'redacted secret declaration' => "secrets_and_private_config",
    'no release claim' => "not_verified",
);
$prohibited = array(
    'update_option(', 'add_option(', 'delete_option(', 'update_user_meta(',
    'delete_user_meta(', 'wp_insert_user(', 'wp_create_user(', 'wp_update_user(',
    'DELETE FROM ', 'DROP TABLE ', 'ALTER TABLE ', 'INSERT INTO ', 'UPDATE wp_',
    'file_put_contents(', 'copy(', 'rename(', 'unlink(', 'wp_remote_get(',
    'wp_safe_remote_get(', 'wp_remote_post(', 'shell_exec(', 'exec(', 'system('
);
$failures = array();
foreach ( $must as $name => $fragment ) {
    if ( false === strpos( $src, $fragment ) ) { $failures[] = 'missing ' . $name; }
}
foreach ( $prohibited as $fragment ) {
    if ( false !== stripos( $src, $fragment ) ) { $failures[] = 'unsafe mutation/API marker ' . $fragment; }
}
if ( $failures ) {
    fwrite( STDERR, "FAIL V12.3 read-only freeze source guard:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}
echo "PASS V12.3 opt-in WP-CLI metadata/source-hash freeze guard; live execution remains unverified\n";
