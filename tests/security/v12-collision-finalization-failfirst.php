<?php
/** F02-X24-012: final DB write fails after canonical owner allowed.
 * MUST return manual reconciliation, never unsafe retry advice or replay.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
class WP_Error{private $code;function __construct($c,$m=''){$this->code=$c;}function get_error_code(){return $this->code;}}
class SAUTH_Activator{static function table($t){return 'mock_'.$t;}}
class SA_Security{static function decrypt($v){return $v;}static function client_fingerprint(){return 'fingerprint-A';}}
class MockDB{
 public $status='pending',$updates=0,$last_error='';
 function prepare($sql,...$params){return $sql;}
 function get_row($q,$fmt){return ['public_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','status'=>$this->status,'user_id'=>0,'change_kind'=>'collision_resolution','created_at'=>gmdate('Y-m-d H:i:s',time()-15),'fingerprint_hash'=>'fingerprint-A','owner_token_ciphertext'=>'proof','payload_ciphertext'=>'{"identifier":"sample@example.test"}'];}
 function update($table,$data,$where,...$format){$this->updates++;if($this->updates===1){$this->status='processing';return 1;}return false;}
}
function absint($n){return abs((int)$n);}
function sanitize_text_field($s){return trim((string)$s);}
function current_time($arg,$utc=true){return gmdate('Y-m-d H:i:s');}
function has_filter($name){return $name==='sauth_resolve_collision_case_v1'?10:false;}
$GLOBALS['ownerCalls']=0;
function apply_filters($name,...$args){$GLOBALS['ownerCalls']++;return ['result'=>'allow','user_id'=>17];}
$wpdb=new MockDB();
require __DIR__.'/../../includes/class-sauth-security-orchestrator.php';
$id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$r=SAUTH_Security_Orchestrator::resolve_collision_case($id,'proof');
if(!($r instanceof WP_Error)||$r->get_error_code()!=='sauth_collision_reconciliation_required'||$wpdb->status!=='processing'||$GLOBALS['ownerCalls']!==1)fwrite(STDERR,"RED: canonical collision owner acted, final DB failed, but reply lacked supervised reconciliation\n"); exit(1);
$r=SAUTH_Security_Orchestrator::resolve_collision_case($id,'proof');
if(!($r instanceof WP_Error)||$GLOBALS['ownerCalls']!==1)fwrite(STDERR,"FAIL: duplicate canonical owner action\n"); exit(1);
echo "PASS uncertain collision outcome retained for manual reconciliation, no replay (mock).\n";
