<?php
/** Synthetic owner contract type/operation tests. No actual accounts or authorization. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
class WP_Error{private $code;function __construct($c,$m=''){$this->code=$c;}function get_error_code(){return $this->code;}}
class SAUTH_Activator{static function table($t){return 'mock_'.$t;}}
class SA_Security{static function decrypt($v){return $v;}static function client_fingerprint(){return 'fixture';}}
function absint($v){return abs((int)$v);}
function sanitize_text_field($v){return (string)$v;}
function current_time($t,$g=false){return gmdate('Y-m-d H:i:s');}
function has_filter($k){return 10;}
$GLOBALS['calls']=0;$GLOBALS['owner_result']=null;
function apply_filters($hook,...$args){++$GLOBALS['calls'];$GLOBALS['last_owner_args']=$args;return $GLOBALS['owner_result'];}
class MockDB{
 public $row=[],$updates=0;
 function prepare($q,...$a){return $q;}
 function get_row($q,$f){return $this->row;}
 function update($t,$d,$w,...$a){++$this->updates;$this->row['status']=$d['status'];return 1;}
}
$wpdb=new MockDB();
require __DIR__.'/../includes/class-sauth-security-orchestrator.php';
function need($v,$label){if(!$v){fwrite(STDERR,"FAIL ".$label.PHP_EOL);exit(1);}}
$id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$base=['public_id'=>$id,'status'=>'pending','change_kind'=>'email_change','user_id'=>'17','apply_after'=>'2000-01-01 00:00:00','created_at'=>gmdate('Y-m-d H:i:s',time()-15),'owner_token_ciphertext'=>'proof','payload_ciphertext'=>'{}','fingerprint_hash'=>'fixture'];
foreach([['change_kind'=>'unknown'],['user_id'=>-17],['user_id'=>0],['user_id'=>'17x'],['user_id'=>[]],['user_id'=>true],['user_id'=>17.0]] as $override){
 $wpdb->row=array_replace($base,$override);$wpdb->updates=0;$GLOBALS['calls']=0;
 $res=SAUTH_Security_Orchestrator::apply_recovery_change($id,'proof');
 need($res instanceof WP_Error&&$res->get_error_code()==='sauth_recovery_scope_invalid'&&$wpdb->updates===0&&$GLOBALS['calls']===0,'invalid recovery scope denied before claim');
}
foreach([-17,0,'17',true,[17],1.5] as $uid){
 $wpdb->row=array_replace($base,['change_kind'=>'collision_resolution','user_id'=>0]);$wpdb->updates=0;$GLOBALS['calls']=0;$GLOBALS['owner_result']=['result'=>'allow','user_id'=>$uid];
 $res=SAUTH_Security_Orchestrator::resolve_collision_case($id,'proof');
 need($res instanceof WP_Error&&$res->get_error_code()==='sauth_collision_owner_verification_required'&&$wpdb->updates===1&&$wpdb->row['status']==='processing'&&$GLOBALS['calls']===1,'malformed owner result not finalized');
}
$wpdb->row=array_replace($base,['public_id'=>'not-an-opaque-reference']);$wpdb->updates=0;$GLOBALS['calls']=0;
$res=SAUTH_Security_Orchestrator::apply_recovery_change($id,'proof');
need($res instanceof WP_Error&&$res->get_error_code()==='sauth_recovery_reference_invalid'&&$wpdb->updates===0&&$GLOBALS['calls']===0,'malformed persisted reference denied');
$wpdb->row=$base;$wpdb->updates=0;$GLOBALS['calls']=0;$GLOBALS['owner_result']=false;
$res=SAUTH_Security_Orchestrator::apply_recovery_change($id,'proof');
need($res instanceof WP_Error&&$wpdb->row['status']==='processing'&&$GLOBALS['calls']===1,'declined owner outcome remains processing');
need($GLOBALS['last_owner_args'][4]===['request_id'=>$id,'contract_version'=>'1.0.0'],'exact stored operation reference delivered without bearer proof');
echo "PASS 16 strict owner contract input/output and reference checks (mock only)".PHP_EOL;
