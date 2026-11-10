<?php
/**
 * F02-CEN-02 / X24-017/018: exact File24 adapter consumer, source-only mock.
 * Run only with PHP 8.3 and matching File24 repository checked out beside File02.
 * This NEVER certifies File24 live, File00 identity or actual user privileges.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/'); define('SAUTH_VERSION','1.4.0');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
class WP_Error {function __construct($code,$message=''){}}
function is_wp_error($value){return $value instanceof WP_Error;}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\\-]/','',strtolower((string)$v));}
function get_current_user_id(){return $GLOBALS['actor'];}
function get_user_meta($uid,$key,$single=true){return $GLOBALS['meta'][$uid][$key]??false;}
function wp_get_session_token(){return 'mock-session-token-not-exported';}
function wp_salt($context){return 'never-real-local-salt';}
class SA_Security {static function client_fingerprint(){return 'mock-fingerprint';}}
class SAUTH_Passkey_Runtime {
 static $valid=true;
 static function current_assurance($uid){return self::$valid?['method'=>'webauthn_passkey','passkey_asserted'=>true,'verified_at'=>time()-30,'hardware_backed'=>false]:[];}
}
class SAUTH_Shared_Signals{static $risk=0;static function risk_score_for_user($uid){return self::$risk;}}
class SAUTH_Activator{static function table($name){return 'ci_'.$name;}}
class DB {
 public $last_error='';
 function prepare($q,...$args){return $q;}
 function get_var($q){return 0;}
}
$GLOBALS['wpdb']=new DB();$GLOBALS['actor']=17;$GLOBALS['meta']=[];
require_once __DIR__.'/../../includes/class-sauth-security-orchestrator.php';
require_once __DIR__.'/../../../file24/plugin/sabri-security-center/src/Support/Sanitizer.php';
require_once __DIR__.'/../../../file24/plugin/sabri-security-center/src/Integration/File02Adapter.php';
function apply_filters($hook,$value,...$args) {
 if($hook==='sauth/session_assurance') return SAUTH_Security_Orchestrator::file24_assurance_projection($value,$args[0],$args[1],$args[2]??[]);
 return $value;
}
function check($okay,$label){if(!$okay){fwrite(STDERR,'RED: '.$label."\n");exit(1);}}
$adapter=new \Sabri\Platform\Security\Integration\File02Adapter();
$good=$adapter->assurance([],17,'account_security',[]);
check(($good['authenticated']??null)===true &&($good['recent_authentication']??null)===true && ($good['mfa_satisfied']??null)===true,'valid File02 passkey not consumed by actual File24 adapter');
check(($good['session_risk']??'')==='low'&&preg_match('/^sauth:[0-9a-f]{64}$/D',(string)($good['assurance_ref']??''))===1,'File24 opaque assurance and risk contract invalid');
check(strpos((string)$good['assurance_ref'],'mock-session-token')===false,'raw session token leaked to File24');
$GLOBALS['actor']=19;
$foreign=$adapter->assurance(['authenticated'=>true,'recent_authentication'=>true,'mfa_satisfied'=>true],17,'account_security',[]);
check(empty($foreign['authenticated'])&&empty($foreign['mfa_satisfied'])&&empty($foreign['recent_authentication']),'cross-principal File24 session assurance allowed');
$GLOBALS['actor']=17;SAUTH_Shared_Signals::$risk=90;
$high=$adapter->assurance([],17,'account_security',[]);
check(empty($high['mfa_satisfied'])&&empty($high['recent_authentication'])&&($high['session_risk']??'')==='high','high-risk File24 assurance fail-closed not enforced');
SAUTH_Shared_Signals::$risk=0;
$GLOBALS['meta'][17][SAUTH_Security_Orchestrator::LOCKDOWN_META]=['active'=>true];
$locked=$adapter->assurance([],17,'account_security',[]);
check(empty($locked['mfa_satisfied'])&&empty($locked['authenticated']),'locked File24 assurance falsely granted');
echo "PASS exact File24 adapter consumes bounded File02 2.0.0 assurance; wrong actor/high risk/lockdown fail closed (mock).\n";
