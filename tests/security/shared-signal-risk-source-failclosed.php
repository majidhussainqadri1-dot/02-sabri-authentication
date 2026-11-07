<?php
/**
 * F02-X24-013/015/016: shared-signal risk store outage is not safe=zero.
 * PHP 7.4/8.3 deterministic mock; no genuine IdP or database used.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
define('ARRAY_A','ARRAY_A');
define('DAY_IN_SECONDS',86400);
define('HOUR_IN_SECONDS',3600);
define('MINUTE_IN_SECONDS',60);
class WP_Error {
  private $code;
  public function __construct($code,$text=''){$this->code=$code;}
  public function get_error_code(){return $this->code;}
}
class SAUTH_Activator {public static function table($n){return 'test_'.$n;}}
class F02RiskDB {
  public $last_error='';
  public $fail=true;
  public $rows=array();
  public function prepare($sql,...$args){return $sql;}
  public function get_var($sql){$this->last_error='';return '0';}
  public function get_results($sql,$fmt=null){
    if($this->fail){$this->last_error='ci injected DB outage';return false;}
    $this->last_error='';return $this->rows;
  }
}
$GLOBALS['wpdb']=new F02RiskDB();
function absint($n){return abs((int)$n);}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$s));}
function get_user_meta($id,$key,$single=true){return array();}
function is_wp_error($x){return $x instanceof WP_Error;}
function fail_risk($why){fwrite(STDERR,'FAIL: '.$why."\n");exit(1);}
require_once __DIR__.'/../../includes/class-sauth-shared-signals.php';
require_once __DIR__.'/../../includes/class-sauth-security-orchestrator.php';
$wpdb=$GLOBALS['wpdb'];
$err=SAUTH_Shared_Signals::risk_score_for_user(17);
if(!($err instanceof WP_Error)||$err->get_error_code()!=='sauth_shared_risk_unavailable'){
    fail_risk('unreadable CAEP/RISC signal store not returned as typed error');
}
$blocked=SAUTH_Security_Orchestrator::risk_v2(17);
if(!is_array($blocked)||($blocked['score']??0)!==100
   ||!in_array('shared_security_unavailable',(array)($blocked['reasons']??array()),true)
   ||empty($blocked['explanations'])){
    fail_risk('risk_v2 accepted unavailable security evidence or gave no reason');
}
$wpdb->fail=false;
$none=SAUTH_Shared_Signals::risk_score_for_user(17);
if($none!==0) fail_risk('healthy empty store was not zero');
$wpdb->rows=array(array('family'=>'risc','signal_type'=>'credential_compromise'));
$score=SAUTH_Shared_Signals::risk_score_for_user(17);
if($score!==35) fail_risk('healthy verified risk event not counted');
$normal=SAUTH_Security_Orchestrator::risk_v2(17);
if(!is_array($normal)||($normal['score']??0)<35
   ||!in_array('shared_security_signal',(array)($normal['reasons']??array()),true)){
    fail_risk('risk engine no longer consumes available verified signals');
}
echo "PASS: CAEP/RISC DB errors fail closed with reason; healthy empty and compromised signals remain distinguishable.\n";
