<?php
/**
 * F02-X24-011/012 behavioral fail-first test: the canonical owner must not
 * execute when the pending -> processing claim loses a race.
 * This harness deliberately does not claim WordPress/MariaDB integration.
 * Run: php tests/recovery-claim-reentrancy.php
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
class WP_Error {
    public $code;
    public function __construct($code, $message = '') { $this->code = $code; }
}
class SAUTH_Activator {
    public static function table($name) { return 'wp_sauth_' . $name; }
}
class SA_Security {
    public static function decrypt($cipher) { return $cipher; }
    public static function client_fingerprint() { return 'fingerprint'; }
}
class ClaimRaceDB {
    public $updates = 0;
    public function prepare($sql, ...$args) { return $sql; }
    public function get_row($sql, $mode) {
        return array(
            'public_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'status' => 'pending',
            'change_kind' => 'email_change',
            'apply_after' => '2000-01-01 00:00:00',
            'owner_token_ciphertext' => 'valid-proof',
            'payload_ciphertext' => '{}',
            'user_id' => 17,
            'fingerprint_hash' => 'fingerprint',
        );
    }
    // Simulate another worker already claiming the record.
    public function update(...$args) { ++$this->updates; return 0; }
    public function query(...$args) { ++$this->updates; return 0; }
}
function absint($v) { return abs((int)$v); }
function sanitize_text_field($v) { return (string)$v; }
// WordPress time helper is required before the claim is attempted.
function current_time($type, $gmt = false) { return gmdate('Y-m-d H:i:s'); }
$ownerCalls = 0;
function apply_filters($hook, ...$args) {
    global $ownerCalls;
    if ($hook === 'sauth_apply_recovery_change_v1') { ++$ownerCalls; return true; }
    if ($hook === 'sauth_resolve_collision_case_v1') {
        ++$ownerCalls;
        return array('result' => 'allow', 'user_id' => 17);
    }
    return null;
}
// Simulate WordPress's registered canonical-owner filter for this isolated mock.
function has_filter($hook) { return in_array($hook, ['sauth_resolve_collision_case_v1','sauth_apply_recovery_change_v1'], true) ? 10 : false; }
$wpdb = new ClaimRaceDB();
require_once __DIR__ . '/../includes/class-sauth-security-orchestrator.php';
$cases = array(
    array('apply_recovery_change', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'valid-proof'),
    array('resolve_collision_case', 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'valid-proof'),
);
$failed = false;
foreach ($cases as $case) {
    $ownerCalls = 0;
    $wpdb->updates = 0;
    $result = SAUTH_Security_Orchestrator::{$case[0]}($case[1], $case[2]);
    if ($ownerCalls !== 0 || $wpdb->updates < 1 || !($result instanceof WP_Error)) {
        fwrite(STDERR, "FAIL {$case[0]}: owner_calls={$ownerCalls}, claims={$wpdb->updates}, rejected=" . (int)($result instanceof WP_Error) . "\n");
        $failed = true;
    }
}
if ($failed) { exit(1); }
echo "Race-loser never invoked canonical owner (mock only).\n";
