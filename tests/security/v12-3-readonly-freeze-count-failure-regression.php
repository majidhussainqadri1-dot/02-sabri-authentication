<?php
/**
 * P00: execute the opt-in CLI-only metadata reader with a controlled WordPress
 * stub. A failed SELECT COUNT(*) must not be reported as a genuine zero.
 * Does not connect to a real WordPress site or alter the database.
 */
define( 'ABSPATH', '/disposable/' );
define( 'WP_CLI', true );
define( 'WP_PLUGIN_DIR', '/disposable/none/wp-content/plugins' );
putenv( 'SAUTH_FREEZE_READONLY=AUTHORIZED' );
putenv( 'SAUTH_FREEZE_EXACT_COUNTS=YES' );
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
function get_option( $name, $default = false ) {
    return 'active_plugins' === $name ? array() : $default;
}
function get_site_option( $name, $default = false ) { return $default; }
function get_bloginfo( $key ) { return '7.0.1'; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function is_multisite() { return false; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
class File02_Freeze_WPDB_Stub {
    public $prefix = 'wp_';
    public $last_error = '';
    public function db_version() { return '11.4'; }
    public function esc_like( $text ) { return addcslashes( $text, '_%\\' ); }
    public function prepare( $sql, $arg ) { return str_replace( '%s', "'" . addslashes( $arg ) . "'", $sql ); }
    public function get_var( $sql ) {
        $this->last_error = '';
        if ( false !== strpos( $sql, 'SHOW TABLES LIKE' ) ) {
            if ( preg_match( "/SHOW TABLES LIKE '(.+)'/D", $sql, $match ) ) {
                return stripslashes( stripslashes( $match[1] ) ); // Mock wpdb->prepare() SQL quoting followed by LIKE escape removal.
            }
            return null;
        }
        if ( false !== strpos( $sql, 'COUNT(*)' ) ) {
            if ( false !== strpos( $sql, 'wp_sauth_rate_limits' ) ) {
                $this->last_error = 'Simulated database count failure';
                return null;
            }
            if ( false !== strpos( $sql, 'wp_sauth_auth_outbox' ) ) { return '0'; }
            return '12';
        }
        return null;
    }
    public function get_results( $sql, $shape ) {
        $this->last_error = '';
        if ( false !== strpos( $sql, 'SHOW COLUMNS' ) ) {
            return array( array( 'Field'=>'id', 'Type'=>'bigint(20)', 'Null'=>'NO' ) );
        }
        if ( false !== strpos( $sql, 'SHOW INDEX' ) ) {
            return array( array( 'Key_name'=>'PRIMARY', 'Column_name'=>'id', 'Seq_in_index'=>1, 'Non_unique'=>0 ) );
        }
        return null;
    }
}
$wpdb = new File02_Freeze_WPDB_Stub();
ob_start();
require dirname( __DIR__, 2 ) . '/tools/file02-live-reality-freeze-readonly.php';
$body = ob_get_clean();
$data = json_decode( $body, true );
function f02_freeze_check( $ok, $message ) {
    if ( ! $ok ) {
        fwrite( STDERR, 'FAIL read-only row-count truth: ' . $message . PHP_EOL );
        exit( 1 );
    }
}
f02_freeze_check( is_array( $data ), 'JSON output unavailable' );
$fail = $data['tables']['sauth_rate_limits'] ?? array();
$zero = $data['tables']['sauth_auth_outbox'] ?? array();
$positive = $data['tables']['sauth_email_verifications'] ?? array();
f02_freeze_check( ( $fail['exact_rows_status'] ?? '' ) === 'query_failed' && null === ( $fail['exact_rows'] ?? null ), 'SQL failure was falsely reported as zero; observed=' . json_encode( $fail ) );
f02_freeze_check( ( $zero['exact_rows_status'] ?? '' ) === 'verified' && ( $zero['exact_rows'] ?? null ) === '0', 'real zero was misclassified' );
f02_freeze_check( ( $positive['exact_rows_status'] ?? '' ) === 'verified' && ( $positive['exact_rows'] ?? null ) === '12', 'valid aggregate was misclassified' );
f02_freeze_check( ( $data['redacted_or_unavailable']['deployed_vs_repository_parity'] ?? '' ) === 'not_verified', 'false deployed parity claim' );
echo "PASS: P00 failed count is UNKNOWN, verified zero remains zero, real count is preserved.\n";
