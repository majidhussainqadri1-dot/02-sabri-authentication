<?php
/** F02-X24-011: unreadable/invalid cooling-off timestamp must not invoke owner. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
class WP_Error{private $code;function __construct($c,$m=''){$this->code=$c;}function get_error_code(){return $this->code;}}
class SAUTH_Activator{static function table($t){return 'mock_'.$t;}}
class SA_Security{static function decrypt($text){return $text;}}
class MockDB{
 public $date='not-a-date',$status='pending',$updates=0,$last_error='';
 function prepare($sql,...$args){return $sql;}
 function get_row($query,$fmt){return ['status'=>$this->status,'public_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','change_kind'=>'email_change','apply_after'=>$this->date,'owner_token_ciphertext'=>'test-proof','payload_ciphertext'=>'{}','user_id'=>17];}
 function update($table,$data,$where,...$args){$this->updates++;$this->status=$data['status'];return 1;}
}
function absint($v){return abs((int)$v);}
function current_time($a,$b=true){return gmdate('Y-m-d H:i:s');}
function has_filter($name){return 10;}
$GLOBALS['ownerCalls']=0;
function apply_filters($name,...$args){$GLOBALS['ownerCalls']++;return true;}
$wpdb=new MockDB();
require __DIR__.'/../../includes/class-sauth-security-orchestrator.php';
$id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
function check($x,$why){if(!$x){fwrite(STDERR,"RED: ".$why."\n");exit(1);}}
$r=SAUTH_Security_Orchestrator::apply_recovery_change($id,'test-proof');
check($r instanceof WP_Error && $r->get_error_code()==='sauth_recovery_not_ready','invalid timestamp did not fail closed');
check($GLOBALS['ownerCalls']===0 && $wpdb->updates===0,'unreadable cooling-off date invoked owner');
$wpdb->date='2099-01-01 00:00:00';
$r=SAUTH_Security_Orchestrator::apply_recovery_change($id,'test-proof');
check($r instanceof WP_Error && $GLOBALS['ownerCalls']===0,'future case invoked owner');
$wpdb->date=gmdate('Y-m-d H:i:s',time()-7200);
$r=SAUTH_Security_Orchestrator::apply_recovery_change($id,'test-proof');
check($r===true && $GLOBALS['ownerCalls']===1 && $wpdb->status==='applied','valid elapsed change unexpectedly denied');
echo "PASS unreadable/future cooling-off denies; valid elapsed owner once (mock).\n";
