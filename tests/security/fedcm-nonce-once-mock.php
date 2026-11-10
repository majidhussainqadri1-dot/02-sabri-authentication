<?php
/**
 * F02-X24-023: FedCM RP nonce must remain valid after a malformed competitor,
 * and an already-read transient snapshot must never authorize two callbacks.
 * Isolated PHP behavioral harness; not an actual IdP/browser acceptance test.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
class F02Response extends Exception {
    public $ok;
    public $data;
    public function __construct($ok, $data) { parent::__construct($ok ? 'ok' : 'error'); $this->ok=$ok; $this->data=$data; }
}
class WP_User { public $ID = 17; }
class SA_Security { public static function client_fingerprint() { return 'client-A'; } }
class SAUTH_Account_Contract { public static function completion_state($user, $context) { return array('result'=>'allow'); } }
class SA_Membership_Adapter {
    public static function membership_assertion($user, $scope, $purpose) { return array('result'=>'allow'); }
    public static function sign_in_allowed($assertion, $completion) { return true; }
    public static function audit($event, $id) { return true; }
}
class SAUTH_Login_Risk { public static function record_successful_login($id, $method, $risk) { return true; } }
class SAUTH_Operations { public static function safe_mode() { return !empty($GLOBALS['fedcm_safe_mode']); } }
$GLOBALS['fedcm_id']='00000000-0000-4000-8000-000000000001';
$GLOBALS['fedcm_nonce']='nonce-at-test';
$GLOBALS['fedcm_record']=array('nonce_hash'=>hash('sha256','nonce-at-test'),'fingerprint'=>'client-A','created_at'=>time(),'expires_at'=>time()+300);
$GLOBALS['fedcm_deleted']=0;
$GLOBALS['fedcm_verified']=0;
$GLOBALS['fedcm_cookies']=0;
$GLOBALS['fedcm_claims']=array();
$GLOBALS['fedcm_schedule_calls']=0;
$GLOBALS['fedcm_safe_mode']=false;
$GLOBALS['fedcm_verifier_registered']=true;
function get_transient($key) { return $GLOBALS['fedcm_record']; }
function delete_transient($key) { ++$GLOBALS['fedcm_deleted']; return true; }
function add_option($key, $value, $deprecated='', $autoload=false) {
    if (array_key_exists($key, $GLOBALS['fedcm_claims'])) return false;
    $GLOBALS['fedcm_claims'][$key]=$value;
    return true;
}
function wp_schedule_single_event($time, $name, $args) { ++$GLOBALS['fedcm_schedule_calls']; return true; }
function get_option($key, $default = 0) { return array_key_exists($key, $GLOBALS['fedcm_claims']) ? $GLOBALS['fedcm_claims'][$key] : $default; }
function delete_option($key) { unset($GLOBALS['fedcm_claims'][$key]); return true; }

function wp_salt($x) { return 'test-auth-salt'; }
function sanitize_text_field($x) { return (string)$x; }
function wp_unslash($x) { return (string)$x; }
function absint($x) { return abs((int)$x); }
function apply_filters($hook, $default, ...$args) {
    if ($hook==='sauth_fedcm_verify_token_v1') { ++$GLOBALS['fedcm_verified']; return array('verified'=>true,'user_id'=>17); }
    return $default;
}
function has_filter($hook) { return $hook==='sauth_fedcm_verify_token_v1' && $GLOBALS['fedcm_verifier_registered'] ? 10 : false; }
function get_userdata($id) { return $id===17 ? new WP_User() : false; }
function wp_set_current_user($id) { return true; }
function wp_set_auth_cookie($id, $remember, $secure) { ++$GLOBALS['fedcm_cookies']; }
function is_ssl() { return true; }
function home_url($path) { return 'https://example.test'.$path; }
function wp_send_json_error($data, $http=400) { throw new F02Response(false, $data); }
function wp_send_json_success($data) { throw new F02Response(true, $data); }
function f02_attempt($nonce) {
    $_POST=array('nonce_id'=>$GLOBALS['fedcm_id'],'nonce'=>$nonce,'token'=>'mock-idp-token');
    try { SAUTH_Modern_Auth::fedcm_finish_ajax(); }
    catch (F02Response $response) { return $response; }
    throw new RuntimeException('missing terminal JSON response');
}
function f02_assert($condition,$why) { if(!$condition){fwrite(STDERR,'FAIL: '.$why."\n");exit(1);} }
require_once __DIR__ . '/../../includes/class-sauth-modern-auth.php';
$invalid=f02_attempt('attacker-invalid-nonce');
f02_assert(!$invalid->ok && ($invalid->data['code']??'')==='fedcm_nonce_invalid','invalid RP nonce did not fail');
f02_assert($GLOBALS['fedcm_deleted']===0,'invalid nonce destructively consumed valid request');
$GLOBALS['fedcm_safe_mode']=true;
$blocked=f02_attempt($GLOBALS['fedcm_nonce']);
f02_assert(!$blocked->ok && ($blocked->data['code']??'')==='safe_mode_active','FedCM finish bypassed Safe Mode');
f02_assert(!$GLOBALS['fedcm_deleted'] && !$GLOBALS['fedcm_claims'],'Safe Mode consumed pending FedCM nonce');
$GLOBALS['fedcm_safe_mode']=false;
$GLOBALS['fedcm_verifier_registered']=false;
$no_owner=f02_attempt($GLOBALS['fedcm_nonce']);
f02_assert(!$no_owner->ok && ($no_owner->data['code']??'')==='fedcm_server_verification_unavailable','missing verifier was not rejected');
f02_assert(!$GLOBALS['fedcm_deleted'] && !$GLOBALS['fedcm_claims'],'missing verifier consumed pending FedCM nonce');
$GLOBALS['fedcm_verifier_registered']=true;
$valid=f02_attempt($GLOBALS['fedcm_nonce']);
f02_assert($valid->ok,'first verified FedCM callback was denied');
$replay=f02_attempt($GLOBALS['fedcm_nonce']);
f02_assert(!$replay->ok,'FedCM nonce replay succeeded after a cached concurrent read');
f02_assert($GLOBALS['fedcm_verified']===1,'server IdP verifier executed for a replay');
f02_assert($GLOBALS['fedcm_cookies']===1,'replayed nonce issued a second login cookie');
f02_assert(count($GLOBALS['fedcm_claims'])===1,'expected one durable DB-backed nonce claim');
f02_assert($GLOBALS['fedcm_schedule_calls']===1,'nonce claim cleanup was not scheduled once');
$claim_key = array_key_first($GLOBALS['fedcm_claims']);
SAUTH_Modern_Auth::expire_fedcm_claim('smc_do_not_delete');
f02_assert(count($GLOBALS['fedcm_claims'])===1,'foreign option modified by cleanup');
SAUTH_Modern_Auth::expire_fedcm_claim($claim_key);
f02_assert(isset($GLOBALS['fedcm_claims'][$claim_key]),'fresh nonce claim prematurely erased');
$GLOBALS['fedcm_claims'][$claim_key] = time() - 361;
SAUTH_Modern_Auth::expire_fedcm_claim($claim_key);
f02_assert(!isset($GLOBALS['fedcm_claims'][$claim_key]),'expired nonce claim not removed');

echo "PASS: FedCM invalid-nonce isolation and once-only concurrent claim (mock).\n";
