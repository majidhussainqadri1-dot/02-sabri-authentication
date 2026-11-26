<?php
/** F02-X24-009: generation-bound recovery, synthetic metadata only. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
class SAUTH_Activator {static function table($k){return 'mock_'.$k;}}
class SA_Security {static function client_fingerprint(){return 'fixture';}}
class SA_Membership_Adapter {static function audit($n,$u,$c=[]){return true;}}
class FakeDB {
 public $usermeta='mock_usermeta',$last_error='';
 function insert($t,$v,$f){return 1;}
 function prepare($sql,...$args){return $args;}
 function query($args){list($released,$uid,$key,$old)=$args;
  if($GLOBALS['fail']||!isset($GLOBALS['meta'][$key])||serialize($GLOBALS['meta'][$key])!==$old)return 0;
  $GLOBALS['meta'][$key]=unserialize($released);return 1;
 }
}
$wpdb=new FakeDB();
function absint($v){return abs((int)$v);}
function sanitize_key($v){return strtolower((string)$v);}
function current_time($v,$g=true){return gmdate('Y-m-d H:i:s');}
function wp_json_encode($v){return json_encode($v);}
$GLOBALS['meta']=[];$GLOBALS['calls']=0;$GLOBALS['approve']=false;$GLOBALS['race']=false;$GLOBALS['fail']=false;
function get_user_meta($u,$k,$s=true){return $s?($GLOBALS['meta'][$k]??false):(isset($GLOBALS['meta'][$k])?[$GLOBALS['meta'][$k]]:[]);}
function maybe_serialize($v){return serialize($v);}
function wp_cache_delete($u,$group){return true;}
function wp_generate_uuid4(){return '33333333-3333-4333-8333-333333333333';}
function update_user_meta($u,$k,$v,$prev=null){
 if($GLOBALS['fail'] || (null!==$prev && ($GLOBALS['meta'][$k]??null)!==$prev))return false;
 $GLOBALS['meta'][$k]=$v;return true;
}
function apply_filters($hook,$default,$u,$e,$ctx){
 ++$GLOBALS['calls'];$GLOBALS['context']=$ctx;
 if($GLOBALS['race']==='erased')unset($GLOBALS['meta'][SAUTH_Security_Orchestrator::LOCKDOWN_META]);
 elseif($GLOBALS['race'])$GLOBALS['meta'][SAUTH_Security_Orchestrator::LOCKDOWN_META]=['active'=>true,'generation'=>'22222222-2222-4222-8222-222222222222'];
 return $GLOBALS['approve'];
}
require __DIR__.'/../includes/class-sauth-security-orchestrator.php';
function need($v,$label){if(!$v){fwrite(STDERR,"FAIL ".$label.PHP_EOL);exit(1);}}
$k=SAUTH_Security_Orchestrator::LOCKDOWN_META;
$old=['active'=>true,'generation'=>'11111111-1111-4111-8111-111111111111'];
$GLOBALS['meta'][$k]=['active'=>true];
need(!SAUTH_Security_Orchestrator::clear_lockdown_by_canonical_recovery(17)&&$GLOBALS['calls']===0,'legacy unbound state denied');
$GLOBALS['meta'][$k]=$old;
need(!SAUTH_Security_Orchestrator::clear_lockdown_by_canonical_recovery(-17)&&$GLOBALS['calls']===0,'negative subject denied');
need(!SAUTH_Security_Orchestrator::clear_lockdown_by_canonical_recovery(17)&&$GLOBALS['meta'][$k]===$old,'missing approval retains lock');
need($GLOBALS['context']===['contract_version'=>'1.0.0','lockdown_generation'=>$old['generation']],'exact generation handoff');
$GLOBALS['approve']=true;$GLOBALS['race']=true;
need(!SAUTH_Security_Orchestrator::clear_lockdown_by_canonical_recovery(17)&&$GLOBALS['meta'][$k]['generation']!==$old['generation']&&$GLOBALS['meta'][$k]['active']===true,'new concurrent lockdown retained');
$GLOBALS['race']='erased';$GLOBALS['meta'][$k]=$old;
need(!SAUTH_Security_Orchestrator::clear_lockdown_by_canonical_recovery(17)&&!isset($GLOBALS['meta'][$k]),'erased state not recreated');
$GLOBALS['race']=false;$GLOBALS['fail']=true;$GLOBALS['meta'][$k]=$old;
need(!SAUTH_Security_Orchestrator::clear_lockdown_by_canonical_recovery(17)&&$GLOBALS['meta'][$k]===$old,'failed state write retains lock');
$GLOBALS['fail']=false;
need(SAUTH_Security_Orchestrator::clear_lockdown_by_canonical_recovery(17)&&$GLOBALS['meta'][$k]['active']===false,'approved synthetic exact generation released');
$calls=$GLOBALS['calls'];
need(!SAUTH_Security_Orchestrator::clear_lockdown_by_canonical_recovery(17)&&$GLOBALS['calls']===$calls,'inactive generation not replayed');
echo "PASS 9 lockdown generation and state fencing checks (mock; no owner approval implemented)".PHP_EOL;
