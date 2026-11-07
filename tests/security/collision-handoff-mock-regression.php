<?php
/** F02-X24-012: isolated WP-function mock, NOT real WordPress/MariaDB integration. */
declare(strict_types=1);
define('ABSPATH', '/tmp/');
define('ARRAY_A', 'ARRAY_A');
define('MINUTE_IN_SECONDS', 60);
$GLOBALS['f02_fingerprint'] = 'device-A';
$GLOBALS['f02_now'] = 1000;
$GLOBALS['f02_transients'] = [];
$GLOBALS['f02_uuid'] = 0;
$GLOBALS['f02_force_transient_failure'] = false;
$GLOBALS['f02_force_delete_failure'] = false;
$GLOBALS['f02_force_update_failure'] = false;
class WP_Error { public $code; public $message; public function __construct(string $code, string $message) { $this->code=$code; $this->message=$message; } }
class SAUTH_Activator { public static function table($name) { return 'test_'.$name; } }
class SA_Security {
    public static function client_fingerprint() { return $GLOBALS['f02_fingerprint']; }
    public static function random_token($length) { return str_repeat('X', $length); }
    public static function encrypt($data) { return base64_encode($data); }
    public static function decrypt($data) { return base64_decode($data, true); }
}
class FakeF02Db {
    public $rows=[];
    public function prepare($sql, ...$args) { return vsprintf(str_replace(['%s','%d'], ["'%s'",'%d'], $sql), $args); }
    public function get_row($query, $output=null) { if (preg_match("/public_id='([^']+)'/", $query, $m)) return $this->rows[$m[1]]??null; return null; }
    public function insert($table, $data, $formats) { $this->rows[$data['public_id']]=$data; return 1; }
    public function delete($table, $where, $formats=null) { if ($GLOBALS['f02_force_delete_failure']) return false; $id=$where['public_id']??''; if (!isset($this->rows[$id])) return 0; unset($this->rows[$id]); return 1; }
    public function update($table, $data, $where, $formats=null, $where_formats=null) { if ($GLOBALS['f02_force_update_failure']) return false; $id=$where['public_id']??''; if (!isset($this->rows[$id]) || ($this->rows[$id]['status']??'')!==($where['status']??'')) return 0; $this->rows[$id]=array_merge($this->rows[$id],$data); return 1; }
}
$GLOBALS['wpdb'] = new FakeF02Db();
function wp_generate_uuid4() { return sprintf('00000000-0000-4000-8000-%012d', ++$GLOBALS['f02_uuid']); }
function wp_json_encode($x) { return json_encode($x); }
function sanitize_key($x) { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower($x)); }
function current_time($kind, $gmt=false) { return gmdate('Y-m-d H:i:s'); }
function wp_salt($x) { return 'test-secret-salt'; }
function set_transient($key, $value, $ttl) { if ($GLOBALS['f02_force_transient_failure']) return false; $GLOBALS['f02_transients'][$key]=[$value,$GLOBALS['f02_now']+$ttl]; return true; }
function get_transient($key) { $entry=$GLOBALS['f02_transients'][$key]??null; return $entry && $entry[1]>$GLOBALS['f02_now'] ? $entry[0] : false; }
function delete_transient($key) { unset($GLOBALS['f02_transients'][$key]); return true; }
function add_query_arg($args, $url) { return $url.'?'.http_build_query($args); }
function home_url($path='') { return 'https://example.test'.$path; }
function wp_unslash($x) { return stripslashes($x); }
function sanitize_text_field($x) { return trim(strip_tags($x)); }
function esc_url($x) { return htmlspecialchars($x, ENT_QUOTES); }
function esc_attr($x) { return htmlspecialchars($x, ENT_QUOTES); }
function admin_url($path) { return 'https://example.test/wp-admin/'.$path; }
function wp_nonce_field($action, $name) { echo '<input type="hidden" name="'.$name.'" value="mock">'; }
function absint($value) { return abs((int)$value); }
function apply_filters($name, $value, ...$args) { return $value; }
function fail($message) { fwrite(STDERR,"FAIL: $message\n"); exit(1); }
require __DIR__.'/../../includes/class-sauth-security-orchestrator.php';
$created=SAUTH_Security_Orchestrator::create_collision_case('person@example.test');
if ($created instanceof WP_Error) fail('case creation failed: '.$created->code);
if (strpos($created['url'], 'proof') !== false || strpos($created['url'], $created['proof']) !== false) fail('secret appears in URL');
if (count($GLOBALS['f02_transients'])!==1) fail('expected one transient');
$key=array_key_first($GLOBALS['f02_transients']);
if ($GLOBALS['f02_transients'][$key][1]!==$GLOBALS['f02_now']+600) fail('handoff TTL must be 600 seconds');
$_GET=['case'=>$created['case']];
$html=SAUTH_Security_Orchestrator::render_collision_resolution();
if (strpos($html, 'name="proof"') === false || strpos($html, $created['proof']) === false) fail('same fingerprint cannot retrieve proof');
$GLOBALS['f02_fingerprint']='device-B';
$html=SAUTH_Security_Orchestrator::render_collision_resolution();
if (strpos($html, 'name="proof"') !== false) fail('different fingerprint retrieved proof');
$GLOBALS['f02_fingerprint']='device-A';
$GLOBALS['f02_now']+=601;
$html=SAUTH_Security_Orchestrator::render_collision_resolution();
if (strpos($html, 'name="proof"') !== false) fail('expired transient retrieved proof');
$GLOBALS['f02_now']=1000;
delete_transient($key);
$html=SAUTH_Security_Orchestrator::render_collision_resolution();
if (strpos($html, 'name="proof"') !== false) fail('deleted transient replayed');
$before = count($GLOBALS['wpdb']->rows);
$GLOBALS['f02_force_transient_failure'] = true;
$failed = SAUTH_Security_Orchestrator::create_collision_case('orphan@example.test');
$GLOBALS['f02_force_transient_failure'] = false;
if (!($failed instanceof WP_Error) || $failed->code !== 'sauth_collision_handoff_failed') fail('transient failure not reported');
if (count($GLOBALS['wpdb']->rows) !== $before) fail('transient failure leaves orphaned pending collision case');
// Fail-first: a compensating DB DELETE can fail after transient storage fails.
$GLOBALS['f02_force_transient_failure'] = true;
$GLOBALS['f02_force_delete_failure'] = true;
$rollback_failed = SAUTH_Security_Orchestrator::create_collision_case('rollback-delete@example.test');
$GLOBALS['f02_force_transient_failure'] = false;
$GLOBALS['f02_force_delete_failure'] = false;
if (!($rollback_failed instanceof WP_Error)) fail('rollback-delete failure not reported');
foreach ($GLOBALS['wpdb']->rows as $row) {
    $payload = base64_decode((string)($row['payload_ciphertext'] ?? ''));
    if (strpos($payload, 'rollback-delete@example.test') !== false && ($row['status'] ?? '') === 'pending') {
        fail('failed compensating delete leaves pending collision row');
    }
}
// A second failed DB write must be observable as an unresolved reconciliation incident.
$GLOBALS['f02_force_transient_failure'] = true;
$GLOBALS['f02_force_delete_failure'] = true;
$GLOBALS['f02_force_update_failure'] = true;
$double_failed = SAUTH_Security_Orchestrator::create_collision_case('double-failure@example.test');
$GLOBALS['f02_force_transient_failure'] = false;
$GLOBALS['f02_force_delete_failure'] = false;
$GLOBALS['f02_force_update_failure'] = false;
if (!($double_failed instanceof WP_Error) || $double_failed->code !== 'sauth_collision_reconciliation_required') fail('double DB failure must surface a distinct reconciliation incident');
if (count($GLOBALS['f02_transients']) !== 0) fail('double DB failure must not create a bearer handoff');
// Both DB writes were deliberately rejected: the mock cannot truthfully demand a row mutation.
// The explicit incident code is the fail-closed signal for operator reconciliation.
// Fail-first: retaining the returned proof must not bypass the 600-second case lifetime.
$expired_case=SAUTH_Security_Orchestrator::create_collision_case('expired-proof@example.test');
if ($expired_case instanceof WP_Error) fail('expiry case creation failed');
$GLOBALS['wpdb']->rows[$expired_case['case']]['created_at']=gmdate('Y-m-d H:i:s',time()-7200);
$expired_result=SAUTH_Security_Orchestrator::resolve_collision_case($expired_case['case'],$expired_case['proof']);
if (!($expired_result instanceof WP_Error) || $expired_result->code !== 'sauth_collision_case_expired') fail('retained bearer proof bypasses 600-second expiry');
echo "PASS: URL secrecy, TTL, fingerprint isolation, expiry, replay and failed-handoff rollback (mock-only)\\n";
