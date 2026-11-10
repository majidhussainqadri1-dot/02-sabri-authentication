<?php
/**
 * F02-X24-011/012: deterministic callback-failure and replay containment.
 * Mock only; NOT WordPress/MariaDB concurrency or crash-safety evidence.
 * Run: php tests/recovery-claim-owner-failure.php
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
    public static function client_fingerprint() { return 'fingerprint'; }
}
class OwnerFailureDB {
    public $status = 'pending';
    public $updates = 0;
    public function prepare($sql, ...$args) { return $sql; }
    public function get_row($sql, $mode) {
        return array(
            'public_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'status' => $this->status,
            'change_kind' => 'email_change',
            'apply_after' => '2000-01-01 00:00:00',
            'owner_token_ciphertext' => 'valid-proof',
            'payload_ciphertext' => '{}',
            'user_id' => 17,
            'fingerprint_hash' => 'fingerprint',
        );
    }
    public function update($table, $data, $where, ...$rest) {
        ++$this->updates;
        if (($where['status'] ?? '') !== $this->status) { return 0; }
        $this->status = (string)$data['status'];
        return 1;
    }
}
function absint($v) { return abs((int)$v); }
function current_time($type, $gmt = false) { return gmdate('Y-m-d H:i:s'); }
function sanitize_text_field($v) { return (string)$v; }
$ownerCalls = 0;
function apply_filters($hook, ...$args) {
    global $ownerCalls;
    if ($hook === 'sauth_apply_recovery_change_v1' || $hook === 'sauth_resolve_collision_case_v1') {
        ++$ownerCalls;
        return false; // Owner may have partially mutated before refusing.
    }
    return null;
}
// Simulate WordPress's registered canonical-owner filter for this isolated mock.
function has_filter($hook) { return in_array($hook, ['sauth_resolve_collision_case_v1','sauth_apply_recovery_change_v1'], true) ? 10 : false; }
$wpdb = new OwnerFailureDB();
require_once __DIR__ . '/../includes/class-sauth-security-orchestrator.php';
$failed = false;
foreach (array('apply_recovery_change', 'resolve_collision_case') as $method) {
    $wpdb->status = 'pending';
    $wpdb->updates = 0;
    $ownerCalls = 0;
    $first = SAUTH_Security_Orchestrator::{$method}('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'valid-proof');
    $second = SAUTH_Security_Orchestrator::{$method}('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'valid-proof');
    if (!($first instanceof WP_Error) || !($second instanceof WP_Error)
        || $ownerCalls !== 1 || $wpdb->status !== 'processing' || $wpdb->updates !== 1) {
        fwrite(STDERR, "FAIL {$method}: calls={$ownerCalls}, state={$wpdb->status}, updates={$wpdb->updates}\n");
        $failed = true;
    }
}
if ($failed) { exit(1); }
echo "Owner failure leaves processing state; replay denied (mock only).\n";
