<?php
/**
 * F02-X24-012: reject mismatched fingerprints in dedicated collision flow; generic flow must reject collision kinds outright.
 * Deterministic mock only; not WordPress/MariaDB integration.
 * Run: php tests/recovery-claim-fingerprint-denial.php
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
class WP_Error {
    private $code;
    public function __construct($code, $message = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
class SAUTH_Activator {
    public static function table($name) { return 'wp_sauth_' . $name; }
}
class SA_Security {
    public static function decrypt($cipher) { return $cipher; }
    public static function client_fingerprint() { return 'different-device'; }
}
class FingerprintDenialDB {
    public $updates = 0;
    public function prepare($sql, ...$args) { return $sql; }
    public function get_row($sql, $mode) {
        return array(
            'public_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'status' => 'pending',
            'change_kind' => 'collision_resolution',
            'apply_after' => '2000-01-01 00:00:00',
            'owner_token_ciphertext' => 'valid-proof',
            'payload_ciphertext' => '{}',
            'user_id' => 17,
            'fingerprint_hash' => 'original-device',
        );
    }
    public function update($table, $data, $where, ...$rest) {
        ++$this->updates;
        return 1;
    }
}
function absint($v) { return abs((int)$v); }
function sanitize_text_field($v) { return (string)$v; }
$ownerCalls = 0;
function apply_filters($hook, ...$args) {
    global $ownerCalls;
    ++$ownerCalls;
    return array('result'=>'allow','user_id'=>17);
}
$wpdb = new FingerprintDenialDB();
require_once __DIR__ . '/../includes/class-sauth-security-orchestrator.php';
$failed = false;
foreach (array('resolve_collision_case', 'apply_recovery_change') as $method) {
    $wpdb->updates = 0;
    $ownerCalls = 0;
    $result = SAUTH_Security_Orchestrator::{$method}('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'valid-proof');
    $expected = 'resolve_collision_case' === $method ? 'sauth_collision_fingerprint_mismatch' : 'sauth_collision_dedicated_flow_required';
    if (!($result instanceof WP_Error) || $result->get_error_code() !== $expected || $wpdb->updates !== 0 || $ownerCalls !== 0) {
        fwrite(STDERR, "FAIL {$method}: claim={$wpdb->updates} owner={$ownerCalls}\n");
        $failed = true;
    }
}
if ($failed) { exit(1); }
echo "Dedicated collision fingerprint mismatch and generic collision isolation denied before claim/owner (mock only).\n";
