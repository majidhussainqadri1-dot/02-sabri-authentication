<?php
/** F02-X24-012: isolated WP-function mock, NOT real WordPress/MariaDB integration. */
declare(strict_types=1);
define('ABSPATH', '/tmp/');
define('MINUTE_IN_SECONDS', 60);
$GLOBALS['f02_fingerprint'] = 'device-A';
$GLOBALS['f02_now'] = 1000;
$GLOBALS['f02_transients'] = [];
$GLOBALS['f02_uuid'] = 0;
class WP_Error { public $code; public $message; public function __construct(string $code, string $message) { $this->code=$code; $this->message=$message; } }
class SAUTH_Activator { public static function table($name) { return 'test_'.$name; } }
class SA_Security {
    public static function client_fingerprint() { return $GLOBALS['f02_fingerprint']; }
    public static function random_token($length) { return str_repeat('X', $length); }
    public static function encrypt($data) { return base64_encode($data); }
}
class FakeF02Db {
    public $rows=[];
    public function insert($table, $data, $formats) { $this->rows[$data['public_id']]=$data; return 1; }
}
$GLOBALS['wpdb'] = new FakeF02Db();
function wp_generate_uuid4() { return sprintf('00000000-0000-4000-8000-%012d', ++$GLOBALS['f02_uuid']); }
function wp_json_encode($x) { return json_encode($x); }
function sanitize_key($x) { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower($x)); }
function current_time($kind, $gmt=false) { return '2026-11-06 00:00:00'; }
function wp_salt($x) { return 'test-secret-salt'; }
function set_transient($key, $value, $ttl) { $GLOBALS['f02_transients'][$key]=[$value,$GLOBALS['f02_now']+$ttl]; return true; }
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
echo "PASS: URL secrecy, TTL, same/different fingerprint, expiry, deleted-handoff replay (mock-only)\n";
