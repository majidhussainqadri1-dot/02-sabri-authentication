<?php
/** F02-X24-011: owner may have acted while final status write fails. Mock only. */
declare(strict_types=1);define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
class WP_Error{private $code;function __construct($c,$m=''){$this->code=$c;}function get_error_code(){return $this->code;}}
class SAUTH_Activator{static function table($k){return 'wp_sauth_'.$k;}}
class SA_Security{static function decrypt($v){return $v;}}
class DB{
 public $status='pending',$updates=0;
 function prepare($q,...$args){return $q;}
 function get_row($q,$format){return ['status'=>$this->status,'public_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
 'change_kind'=>'email_change','apply_after'=>'2000-01-01 00:00:00',
 'owner_token_ciphertext'=>'proof','payload_ciphertext'=>'{}','user_id'=>17];}
 function update($t,$data,$where,...$args){++$this->updates;if($this->updates===1){$this->status='processing';return 1;}return false;}
}
function absint($v){return abs((int)$v);}function current_time($x,$gmt=false){return gmdate('Y-m-d H:i:s');}
function has_filter($name){return $name==='sauth_apply_recovery_change_v1'?10:false;}
$GLOBALS['calls']=0;
function apply_filters($name,...$args){++$GLOBALS['calls'];return true;}
$wpdb=new DB();require __DIR__.'/../../includes/class-sauth-security-orchestrator.php';
$r=SAUTH_Security_Orchestrator::apply_recovery_change('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','proof');
if(!($r instanceof WP_Error)||$r->get_error_code()!=='sauth_recovery_reconciliation_required'||$wpdb->status!=='processing'||$GLOBALS['calls']!==1)exit("RED: canonical owner acted but failed finalization not typed/retained\n");
$r=SAUTH_Security_Orchestrator::apply_recovery_change('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','proof');
if(!($r instanceof WP_Error)||$GLOBALS['calls']!==1)exit("FAIL owner effect replay\n");
echo "PASS owner success + DB failure requires supervised reconciliation, no replay (mock).\n";
