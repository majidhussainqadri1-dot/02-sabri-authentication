<?php
/**
 * F02-X24-008/009/013/018: fail-first behavioral mock, NOT staging/live.
 * 1) "not me" must not claim containment when session revocation failed.
 * 2) critical security-signal failure must not report containment complete.
 * 3) unavailable risk DB must not approve privileged smart step-up.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');
define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
class WP_Error{private $code;function __construct($code,$msg=''){$this->code=$code;}function get_error_code(){return $this->code;}}
function is_wp_error($v){return $v instanceof WP_Error;}
class SAUTH_Activator{static function table($key){return 'wp_sauth_'.$key;}}
class SA_Security{static function client_fingerprint(){return 'test-device';}}
class SA_Membership_Adapter{static function audit($event,$uid,$details=[]){return true;}}
class SAUTH_Session_Manager{
 static $other=false, $all=true;
 static function revoke_other_sessions($uid,$token,$reason){return self::$other;}
 static function revoke_user_sessions($uid,$reason){return self::$all;}
}
class SAUTH_Passkey_Runtime{
 static function invalidate_user_assurance($id){return true;}
 static function current_assurance($id){return ['method'=>'webauthn_passkey','passkey_asserted'=>true,'verified_at'=>time(),'hardware_backed'=>false];}
}
class SAUTH_Shared_Signals{
 static $result='event-17';
 static function emit($family,$type,$uid,$claims=[]){return self::$result;}
 static function risk_score_for_user($uid){return 0;}
}
class MockSecurityDB{
 public $last_error='',$risk_unavailable=true;
 function prepare($sql,...$args){return $sql;}
 function get_var($sql){return strpos($sql,'security_timeline')!==false?'event-17':($this->risk_unavailable?null:0);}
 function insert($table,$data,$formats){return 1;}
}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\\-]/','',strtolower((string)$v));}
function sanitize_text_field($v){return (string)$v;}
function wp_get_session_token(){return 'token-17';}
function wp_salt($s){return 'unit-key';}
function get_current_user_id(){return 17;}
function wp_generate_uuid4(){return '22222222-2222-4222-8222-222222222222';}
function wp_json_encode($x){return json_encode($x);}
function current_time($s,$gmt=false){return gmdate('Y-m-d H:i:s');}
function apply_filters($name,$value,...$args){return $value;}
$GLOBALS['meta']=[];
function update_user_meta($uid,$key,$value){$GLOBALS['meta'][$uid][$key]=$value;return true;}
function get_user_meta($uid,$key,$single=true){return $GLOBALS['meta'][$uid][$key]??false;}
function delete_user_meta($uid,$key){unset($GLOBALS['meta'][$uid][$key]);return true;}
$wpdb=new MockSecurityDB();
require __DIR__.'/../../includes/class-sauth-security-orchestrator.php';
function f02_check($ok,$reason){if(!$ok){fwrite(STDERR,"RED: ".$reason."\n");exit(1);}}
$r=SAUTH_Security_Orchestrator::report_not_me(17,'event-17');
f02_check(is_wp_error($r)&&$r->get_error_code()==='sauth_not_me_containment_unverified','session revocation failure falsely reported as contained');
f02_check(SAUTH_Security_Orchestrator::authentication_blocked(17),'failed session containment did not trigger user lockdown');
SAUTH_Session_Manager::$other=true;
unset($GLOBALS['meta'][17]);
SAUTH_Shared_Signals::$result=new WP_Error('unavailable');
$r=SAUTH_Security_Orchestrator::report_not_me(17,'event-17');
f02_check(is_wp_error($r)&&$r->get_error_code()==='sauth_not_me_signal_unverified','unavailable security signal falsely acknowledged');
f02_check(SAUTH_Security_Orchestrator::authentication_blocked(17),'risk-signal failure was not contained');
unset($GLOBALS['meta'][17]);
SAUTH_Shared_Signals::$result='event-17';
$r=SAUTH_Security_Orchestrator::report_not_me(17,'event-17');
f02_check($r===true&&!SAUTH_Security_Orchestrator::authentication_blocked(17),'valid security report was rejected');
$wpdb->risk_unavailable=true;
f02_check(SAUTH_Security_Orchestrator::smart_step_up(17,'remove_passkey')===false,'failed risk DB approved privileged step-up');
$wpdb->risk_unavailable=false;
f02_check(SAUTH_Security_Orchestrator::smart_step_up(17,'remove_passkey')===true,'low-risk verified passkey step-up unexpectedly denied');
echo "PASS not-me fails closed and risk-gated step-up (isolated mock).\n";
