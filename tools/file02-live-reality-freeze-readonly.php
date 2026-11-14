<?php
/**
 * File02 V12.3 P00: privileged, read-only live/staging reality snapshot.
 *
 * Run manually ONLY by an authorized Hostinger operator from WP-CLI:
 * SAUTH_FREEZE_READONLY=AUTHORIZED wp eval-file tools/file02-live-reality-freeze-readonly.php
 *
 * No mutation, user-record, option-content dump, file-copy, log read or key export.
 * Output is a source/DB metadata snapshot, NOT a deployment-parity verdict.
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI
     || 'AUTHORIZED' !== getenv( 'SAUTH_FREEZE_READONLY' ) ) {
    fwrite( STDERR, "DENIED: explicit authorized read-only WP-CLI freeze is required.\n" );
    exit( 1 );
}
global $wpdb;
function f02_freeze_file_tree( $root, $main_file ) {
    $out = array(
        'present' => false, 'main_php_sha256' => null, 'plugin_header_version' => null,
        'file_count' => 0, 'tree_sha256' => null, 'symlink_count_skipped' => 0,
        'read_error' => false,
    );
    if ( ! is_dir( $root ) || ! is_file( $root . '/' . $main_file ) ) { return $out; }
    $out['present'] = true;
    $out['main_php_sha256'] = hash_file( 'sha256', $root . '/' . $main_file );
    $header = get_file_data( $root . '/' . $main_file, array( 'Version'=>'Version' ), 'plugin' );
    $out['plugin_header_version'] = (string) ( $header['Version'] ?? '' );
    $entries = array();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ( $iterator as $entry ) {
        if ( $entry->isLink() ) { $out['symlink_count_skipped']++; continue; }
        if ( ! $entry->isFile() ) { continue; }
        $absolute = $entry->getPathname();
        $relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $absolute, strlen( $root ) + 1 ) );
        $digest = hash_file( 'sha256', $absolute );
        if ( false === $digest ) { $out['read_error'] = true; continue; }
        $entries[ $relative ] = $digest;
    }
    ksort( $entries, SORT_STRING );
    $ctx = hash_init( 'sha256' );
    foreach ( $entries as $relative => $digest ) {
        hash_update( $ctx, $relative . "\0" . $digest . "\n" );
    }
    $out['file_count'] = count( $entries );
    $out['tree_sha256'] = hash_final( $ctx );
    return $out;
}
$active = (array) get_option( 'active_plugins', array() );
$network = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
$check_plugins = array(
    'File02-standard' => array( 'sabri-authentication', 'sabri-authentication.php' ),
    'File02-package' => array( '02-sabri-authentication', 'sabri-authentication.php' ),
    'File00' => array( 'sabri-membership-core', 'sabri-membership-core.php' ),
);
$plugins = array();
foreach ( $check_plugins as $label => $pair ) {
    $rel = $pair[0] . '/' . $pair[1];
    $plugins[ $label ] = array(
        'active_site' => in_array( $rel, $active, true ),
        'active_network' => array_key_exists( $rel, $network ),
        'source' => f02_freeze_file_tree( WP_PLUGIN_DIR . '/' . $pair[0], $pair[1] ),
    );
}
$safe_options = array(
    'sauth_version', 'sauth_db_version', 'sauth_passkey_schema_version',
    'sauth_legacy_table_migration_version', 'sauth_rewrite_version',
    'sauth_safety_route_ready', 'sauth_safe_mode',
    'smc_version', 'smc_db_version', 'smc_safe_mode',
);
$options = array();
foreach ( $safe_options as $key ) {
    $v = get_option( $key, null );
    // Do not serialize unexpected/complex option values: they may include secrets.
    $options[ $key ] = is_scalar( $v ) || null === $v ? $v : '[non-scalar-redacted]';
}
$bootstrap = get_option( 'smc_activation_bootstrap_state_v2', null );
$options['smc_activation_bootstrap_state_v2.status_only'] =
    is_array( $bootstrap ) && isset( $bootstrap['status'] ) && is_scalar( $bootstrap['status'] )
    ? (string) $bootstrap['status'] : null;
$tables = array(
    'sauth_rate_limits', 'sauth_auth_outbox', 'sauth_email_verifications',
    'sauth_auth_sessions', 'sauth_auth_devices', 'sauth_auth_risk_challenges',
    'sauth_auth_attempts', 'sauth_passkeys', 'sauth_security_timeline',
    'sauth_recovery_changes', 'sauth_shared_signals',
);
$schema = array();
$exact = 'YES' === getenv( 'SAUTH_FREEZE_EXACT_COUNTS' );
foreach ( $tables as $suffix ) {
    $table = $wpdb->prefix . $suffix;
    if ( ! preg_match( '/^[A-Za-z0-9_]+$/D', $table ) ) {
        $schema[ $suffix ] = array( 'error'=>'unsafe_prefix' );
        continue;
    }
    $exists = $wpdb->get_var( $wpdb->prepare(
        'SHOW TABLES LIKE %s', $wpdb->esc_like( $table )
    ) );
    if ( (string) $exists !== $table ) {
        $schema[ $suffix ] = array( 'exists'=>false );
        continue;
    }
    $columns = $wpdb->get_results( 'SHOW COLUMNS FROM `' . $table . '`', ARRAY_A );
    $indexes = $wpdb->get_results( 'SHOW INDEX FROM `' . $table . '`', ARRAY_A );
    if ( ! is_array( $columns ) || ! is_array( $indexes )
        || '' !== (string) $wpdb->last_error ) {
        $schema[ $suffix ] = array( 'exists'=>true, 'metadata_error'=>true );
        continue;
    }
    $schema[ $suffix ] = array(
        'exists'=>true,
        'columns'=>array_map( static function ( $c ) {
            return array( 'name'=>$c['Field'], 'type'=>$c['Type'], 'nullable'=>$c['Null'] );
        }, $columns ),
        'indexes'=>array_map( static function ( $i ) {
            return array( 'name'=>$i['Key_name'], 'column'=>$i['Column_name'],
                'order'=>(int)$i['Seq_in_index'], 'unique'=>0 === (int)$i['Non_unique'] );
        }, $indexes ),
        'exact_rows' => null,
        'exact_rows_status' => $exact ? 'pending' : 'not_requested',
    );
    if ( $exact ) {
        // A failed/null SQL count is UNKNOWN, never a fabricated zero-row table.
        $rows = $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $table . '`' );
        if ( null === $rows || '' !== (string) $wpdb->last_error
            || ! is_scalar( $rows ) || ! preg_match( '/^(?:0|[1-9][0-9]*)$/D', (string) $rows ) ) {
            $schema[ $suffix ]['exact_rows_status'] = 'query_failed';
        } else {
            $schema[ $suffix ]['exact_rows'] = (string) $rows;
            $schema[ $suffix ]['exact_rows_status'] = 'verified';
        }
    }
}
$report = array(
    'schema'=>'sauth.live-reality-freeze.readonly.v1',
    'meaning'=>'Authorized read-only observation only; deployment parity and root cause not established',
    'timestamp_utc'=>gmdate( 'c' ),
    'runtime'=>array(
        'wordpress'=>get_bloginfo( 'version' ),
        'php'=>PHP_VERSION,
        'database'=>method_exists( $wpdb, 'db_version' ) ? (string)$wpdb->db_version() : 'unknown',
        'multisite'=>is_multisite(),
        'site_home'=>home_url( '/' ),
    ),
    'plugins'=>$plugins,
    'allowlisted_options'=>$options,
    'tables'=>$schema,
    'redacted_or_unavailable'=>array(
        'live_php_errors'=>'not_collected',
        'secrets_and_private_config'=>'not_collected',
        'record_rows'=>'not_collected',
        'backup_restore_parity'=>'not_tested',
        'actual_zip_sha256'=>'not_collected',
        'deployed_vs_repository_parity'=>'not_verified',
    ),
);
echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
