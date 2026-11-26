<?php
/** NFR-001/003, X24-013: risk decisions require verified database counts. */
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
function absint($v){return abs((int)$v);}function wp_salt($kind){return 'synthetic';}
function sanitize_text_field($v){return (string)$v;}function wp_unslash($v){return $v;}
class SA_Security{public static function client_fingerprint(){return str_repeat('a',64);}}
class SAUTH_Activator{public static function table($name){return 'wp_sauth_'.$name;}}
class SAUTH_Provider_Health{public static function available_for_ui($name){return true;}}
class SAUTH_Passkeys{public static function authentication_ready(){return true;}}
class FakeRiskDB{
 public $prefix='wp_',$last_error='',$values=[],$device=null;
 function prepare($sql,...$args){return $sql;}
 function get_row($sql,$mode){return $this->device;}
 function get_var($sql){$item=array_shift($this->values);$this->last_error=$item[1];return $item[0];}
}
function need($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: ".$label.PHP_EOL);exit(1);}}
require __DIR__.'/../includes/class-sauth-login-risk.php';
$wpdb=new FakeRiskDB();$wpdb->device=['last_seen_at'=>gmdate('Y-m-d H:i:s')];
foreach([null,'unknown',-1,true,1.5] as $bad){
 $wpdb->last_error='';$wpdb->values=[[$bad,'']];
 need(SAUTH_Login_Risk::evaluate(17)['reason_code']==='risk_storage_unavailable','unknown network count denied');
 $wpdb->last_error='';$wpdb->values=[['1',''],[$bad,'']];
 need(SAUTH_Login_Risk::evaluate(17)['reason_code']==='risk_storage_unavailable','unknown attempt count denied');
}
$wpdb->last_error='';$wpdb->values=[['1','read error']];
need(SAUTH_Login_Risk::evaluate(17)['action']==='deny','database error despite numeric count denied');
$wpdb->last_error='';$wpdb->values=[['1',''],['0','']];
need(SAUTH_Login_Risk::evaluate(17)['action']==='allow','verified low-risk counts accepted');
$wpdb->last_error='';$wpdb->device=null;$wpdb->values=[['1',''],['0',''],[null,'']];
need(SAUTH_Login_Risk::evaluate(17)['reason_code']==='passkey_status_unavailable','missing passkey count cannot downgrade');
$wpdb->last_error='';$wpdb->values=[['1',''],['0',''],['0','']];
need(SAUTH_Login_Risk::evaluate(17)['reason_code']==='bounded_medium_risk_no_passkey','verified zero count preserves bounded policy');
$wpdb->last_error='';$wpdb->values=[['1',''],['0',''],['1','']];
need(SAUTH_Login_Risk::evaluate(17)['action']==='challenge','verified active passkey requires challenge');
echo "PASS 15 risk count and downgrade boundary assertions (mock)".PHP_EOL;
