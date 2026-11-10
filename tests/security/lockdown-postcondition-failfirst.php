<?php
/** F02-X24-009: RED-first lockdown truth; isolated WP mocks only. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);define('ARRAY_A','ARRAY_A');
class ResponseResult extends Exception{public $ok,$data;function __construct($ok,$data){$this->ok=$ok;$this->data=$data;}}
class SAUTH_Activator{static function table($k){return 'wp_sauth_'.$k;}}
class SA_Security{static function client_fingerprint(){return 'fixture';}}
class SAUTH_Session_Manager{static $result=true;static function revoke_user_sessions($uid,$reason){return self::$result;}}
class SAUTH_Passkey_Runtime{static $result=true;static function invalidate_user_assurance($uid){return self::$result;}}
class SAUTH_Shared_Signals{static function emit($family,$type,$uid,$claims){return 'event';}}
class SA_Membership_Adapter{static function audit($name,$uid,$context=[]){return true;}}
class FakeDB{function insert($table,$data,$format){return 1;}}
$GLOBALS['wpdb']=new FakeDB();
$GLOBALS['store_ok']=true;$GLOBALS['meta']=[];
function absint($v){return abs((int)$v);}function sanitize_key($v){return strtolower((string)$v);}
function sanitize_text_field($v){return (string)$v;}
function update_user_meta($uid,$key,$value){if(!$GLOBALS['store_ok'])return false;$GLOBALS['meta'][$uid][$key]=$value;return true;}
function get_user_meta($uid,$key,$single=true){return $GLOBALS['meta'][$uid][$key]??false;}
function current_time($s,$gmt=false){return gmdate('Y-m-d H:i:s');}
function wp_generate_uuid4(){return '33333333-3333-4333-8333-333333333333';}
function wp_json_encode($x){return json_encode($x);}
function is_user_logged_in(){return true;}
function get_current_user_id(){return 17;}
function check_ajax_referer($key,$field){return true;}
function wp_send_json_success($data){throw new ResponseResult(true,$data);}
function wp_send_json_error($data,$status=400){throw new ResponseResult(false,$data);}
function fail($message){fwrite(STDERR,"RED: ".$message."\n");exit(1);}
require __DIR__.'/../../includes/class-sauth-security-orchestrator.php';
$GLOBALS['store_ok']=false;
if(SAUTH_Security_Orchestrator::emergency_lockdown(17,'owner_request')!==false)fail('lockdown reports success when user meta persistence fails');
$GLOBALS['store_ok']=true;$GLOBALS['meta']=[];
SAUTH_Passkey_Runtime::$result=false;
if(SAUTH_Security_Orchestrator::emergency_lockdown(17,'owner_request')!==false)fail('lockdown reports success when assurance invalidation fails');
SAUTH_Passkey_Runtime::$result=true;SAUTH_Session_Manager::$result=false;$GLOBALS['meta']=[];
try{SAUTH_Security_Orchestrator::lockdown_ajax();}catch(ResponseResult $result){
 if($result->ok || ($result->data['code']??'')!=='lockdown_unverified')fail('AJAX reports success when session revoke fails');
}
SAUTH_Session_Manager::$result=true;$GLOBALS['meta']=[];
try{SAUTH_Security_Orchestrator::lockdown_ajax();}catch(ResponseResult $result){
 if(!$result->ok || empty($result->data['locked']))fail('legitimate lockdown result denied');
}
echo "PASS emergency lockdown storage/assurance/session postconditions (mock).\n";
