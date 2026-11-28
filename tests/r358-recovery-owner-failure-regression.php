<?php
/** Recovery input and callback failure containment, isolated mocks only. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
class WP_Error{private $code;function __construct($c,$m=''){$this->code=$c;}function get_error_code(){return $this->code;}}
class SAUTH_Activator{static function table($t){return 'mock_'.$t;}}
class SA_Security{static function decrypt($v){return $v;}static function client_fingerprint(){return 'fixture';}}
function absint($v){return abs((int)$v);}
function sanitize_text_field($v){return (string)$v;}
function current_time($t,$g=false){return gmdate('Y-m-d H:i:s');}
function has_filter($k){return 10;}
function get_user_meta($u,$k,$s=true){
 $rows=$GLOBALS['lockdown_fixture_rows']??[['active'=>true,'generation'=>'11111111-1111-4111-8111-111111111111']];
 return $s?($rows[0]??''):$rows;
}
$GLOBALS['calls']=0;
function apply_filters($hook,...$args){++$GLOBALS['calls'];throw new RuntimeException('synthetic unavailable owner');}
class MockDB{
 public $row=[],$reads=0,$updates=0;
 function prepare($q,...$a){return $q;}
 function get_row($q,$f){++$this->reads;return $this->row;}
 function update($t,$d,$w,...$a){++$this->updates;$this->row['status']=$d['status'];return 1;}
}
$wpdb=new MockDB();
require __DIR__.'/../includes/class-sauth-security-orchestrator.php';
$n=0;
function need($v,$label){global $n;if(!$v){fwrite(STDERR,"FAIL ".$label.PHP_EOL);exit(1);}++$n;}
$id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$base=['public_id'=>$id,'status'=>'pending','change_kind'=>'email_change','user_id'=>'17','apply_after'=>'2000-01-01 00:00:00','created_at'=>gmdate('Y-m-d H:i:s',time()-15),'owner_token_ciphertext'=>'proof','payload_ciphertext'=>'{}','fingerprint_hash'=>'fixture'];
foreach(['apply_recovery_change','resolve_collision_case'] as $method){
 foreach(['',false,[],str_repeat('x',513)] as $proof){
  $wpdb->row=$base;$wpdb->reads=0;$wpdb->updates=0;$GLOBALS['calls']=0;
  $r=SAUTH_Security_Orchestrator::$method($id,$proof);
  need($r instanceof WP_Error&&$wpdb->reads===0&&$wpdb->updates===0&&$GLOBALS['calls']===0,'invalid proof rejected before lookup');
 }
 $wpdb->row=$base;$wpdb->row['owner_token_ciphertext']='';$wpdb->updates=0;$GLOBALS['calls']=0;
 $r=SAUTH_Security_Orchestrator::$method($id,'proof');
 need($r instanceof WP_Error&&$wpdb->updates===0&&$GLOBALS['calls']===0,'failed decrypt never treated as proof');
 $wpdb->row=$base;$wpdb->updates=0;$GLOBALS['calls']=0;
 $r=SAUTH_Security_Orchestrator::$method($id,'proof');
 need($r instanceof WP_Error&&strpos($r->get_error_code(),'reconciliation_required')!==false&&$wpdb->row['status']==='processing'&&$GLOBALS['calls']===1,'owner exception preserves claim and returns reconciliation');
 $r=SAUTH_Security_Orchestrator::$method($id,'proof');
 need($r instanceof WP_Error&&$GLOBALS['calls']===1,'owner exception not replayed');
}
$wpdb->row=$base;$wpdb->reads=0;$wpdb->updates=0;
need(!SAUTH_Security_Orchestrator::cancel_recovery_change(17,$id,'')&&$wpdb->reads===0,'empty cancellation proof refused');
$wpdb->row['owner_token_ciphertext']='';
need(!SAUTH_Security_Orchestrator::cancel_recovery_change(17,$id,'proof')&&$wpdb->updates===0,'failed decryption cannot cancel');
$GLOBALS['calls']=0;
need(!SAUTH_Security_Orchestrator::clear_lockdown_by_canonical_recovery(17)&&$GLOBALS['calls']===1,'lockdown owner outage leaves containment');
// WP multi-row metadata must not be classified by the first row.
foreach ([
 ['absent',[],false],
 ['released',[['active'=>false]],false],
 ['active',[['active'=>true]],true],
 ['released then active',[['active'=>false],['active'=>true]],true],
 ['active then released',[['active'=>true],['active'=>false]],true],
 ['two released',[['active'=>false],['active'=>false]],true],
 ['malformed',['invalid'],true],
 ['missing active field',[[]],true],
 ['invalid active type',[['active'=>'0']],true],
] as [$label,$rows,$expected]) {
 $GLOBALS['lockdown_fixture_rows']=$rows;
 need(SAUTH_Security_Orchestrator::authentication_blocked(17)===$expected,'lockdown metadata: '.$label);
}
unset($GLOBALS['lockdown_fixture_rows']);
echo "PASS ".$n." recovery proof, owner exception and lockdown metadata assertions (isolated mocks)".PHP_EOL;
