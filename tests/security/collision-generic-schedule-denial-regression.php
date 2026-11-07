<?php
/**
 * File 02 v12 F02-X24-011/012: generic recovery scheduling must never create
 * a collision-resolution case. Only the dedicated, one-time fingerprint-bound
 * collision constructor may do so. Isolated PHP runtime harness; not E2E.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);

class WP_Error {
    public $code;
    public function __construct($code, $message = '') { $this->code = $code; }
}
class SAUTH_Activator {
    public static function table($name) { return 'test_' . $name; }
}
class SAUTH_Passkey_Runtime {
    public static function current_assurance($user_id) {
        return array('method'=>'webauthn_passkey', 'passkey_asserted'=>true,
            'verified_at'=>time(), 'hardware_backed'=>false);
    }
}
class SA_Security {
    public static function random_token($bytes) { return str_repeat('x', $bytes); }
    public static function encrypt($s) { return base64_encode((string)$s); }
    public static function client_fingerprint() { return 'fingerprint-A'; }
}
class F02ScheduleDB {
    public $inserts = array();
    public function prepare($sql) { return $sql; }
    public function get_var($sql) { return '0'; }
    public function insert($table, $values, $format = null) {
        $this->inserts[] = array('table'=>$table,'values'=>$values);
        return 1;
    }
}
$GLOBALS['wpdb'] = new F02ScheduleDB();
$GLOBALS['uuid_counter'] = 0;
function absint($v) { return abs((int)$v); }
function get_current_user_id() { return 77; }
function get_user_meta($user_id, $key, $single = true) { return array(); }
function wp_get_session_token() { return 'test-session-token'; }
function wp_salt($purpose = '') { return 'test-salt'; }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$v)); }
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function apply_filters($hook, $default) { return $default; }
function wp_json_encode($v) { return json_encode($v); }
function wp_generate_uuid4() { return sprintf('00000000-0000-4000-8000-%012d', ++$GLOBALS['uuid_counter']); }
function current_time($type, $gmt = false) { return gmdate('Y-m-d H:i:s'); }
function f02_fail($message) { fwrite(STDERR, 'FAIL: ' . $message . "\n"); exit(1); }

require_once __DIR__ . '/../../includes/class-sauth-security-orchestrator.php';
$wpdb = $GLOBALS['wpdb'];

// Verify the legitimate owner-mediated generic recovery flow still works.
$good = SAUTH_Security_Orchestrator::schedule_recovery_change(
    77, 'email_change', array('next_email'=>'test@example.invalid')
);
if ($good instanceof WP_Error || !is_array($good) || empty($good['owner_token'])) {
    f02_fail('legitimate email change was rejected');
}
$before = count($wpdb->inserts);
if ($before !== 2 || $wpdb->inserts[0]['table'] !== 'test_recovery_changes'
    || $wpdb->inserts[1]['table'] !== 'test_security_timeline') {
    f02_fail('legitimate recovery and audit row were not written');
}

// Previously this created an unresolvable pending collision row and token.
$blocked = SAUTH_Security_Orchestrator::schedule_recovery_change(
    77, 'collision_resolution', array('identifier'=>'other@example.invalid')
);
if (!($blocked instanceof WP_Error)) {
    f02_fail('generic scheduler created a dedicated collision-resolution case');
}
if (count($wpdb->inserts) !== $before) {
    f02_fail('collision kind was rejected only after database/audit side effects');
}

// Independent unknown change kinds must also fail before storage.
$unknown = SAUTH_Security_Orchestrator::schedule_recovery_change(77, 'unrecognized_kind', array());
if (!($unknown instanceof WP_Error) || count($wpdb->inserts) !== $before) {
    f02_fail('unknown recovery kind caused a write');
}
echo "PASS: owner-mediated generic recovery works; collision/unknown kinds are rejected without side effects (PHP mock).\n";
