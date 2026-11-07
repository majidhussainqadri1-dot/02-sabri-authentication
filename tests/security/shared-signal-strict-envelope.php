<?php
/**
 * F02-X24-015/016 NFR-001/002: immutable security signal envelopes require a
 * canonical UUID, positive owner ID and signed positive integer issued time.
 * Red-first isolated PHP harness, no real IdP verifier.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A','ARRAY_A');
define('DAY_IN_SECONDS',86400);
class WP_Error {
    private $code;
    public function __construct($code,$message=''){$this->code=$code;}
    public function get_error_code(){return $this->code;}
}
class SAUTH_Activator {public static function table($name){return 'test_'.$name;}}
class F02_Signal_DB {
    public $last_error='';
    public $read_count=0;
    public $write_count=0;
    public function prepare($sql,...$args){return $sql;}
    public function get_row($sql,$type=null){$this->read_count++;return null;}
    public function insert($table,$rows,$format){$this->write_count++;return false;}
}
$GLOBALS['wpdb']=new F02_Signal_DB();
function absint($n){return abs((int)$n);}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$s));}
function sanitize_text_field($s){return trim((string)$s);}
function wp_json_encode($s){return json_encode($s);}
function current_time($type,$gmt=false){return gmdate('Y-m-d H:i:s');}
function assert_denied($data,$case) {
    $db=$GLOBALS['wpdb'];
    $before=$db->read_count+$db->write_count;
    $result=SAUTH_Shared_Signals::ingest($data,array('verified'=>true,'source'=>'ci'));
    if(!($result instanceof WP_Error)||$result->get_error_code()!=='sauth_signal_invalid'){
        fwrite(STDERR,"FAIL: ".$case." not rejected at validation\n");exit(1);
    }
    if($db->read_count+$db->write_count!==$before) {
        fwrite(STDERR,"FAIL: ".$case." touched database before rejection\n");exit(1);
    }
}
require_once __DIR__.'/../../includes/class-sauth-shared-signals.php';
$valid=array('family'=>'caep','type'=>'risk_level_change',
    'event_id'=>'12345678-1234-4234-8234-123456789abc',
    'user_id'=>17,'issued_at'=>time(),'claims'=>array('risk_score'=>50));
$malformed=$valid;$malformed['event_id']=str_repeat('-',36);
assert_denied($malformed,'malformed 36-character event id');
$bad=$valid;$bad['user_id']=-17;
assert_denied($bad,'negative user identifier');
$bad=$valid;$bad['issued_at']=-time();
assert_denied($bad,'negative issued-at');
$bad=$valid;$bad['issued_at']=time()+0.7;
assert_denied($bad,'fractional issued-at');
$good=SAUTH_Shared_Signals::ingest($valid,array('verified'=>true,'source'=>'ci'));
if(!($good instanceof WP_Error)||$good->get_error_code()==='sauth_signal_invalid'||$GLOBALS['wpdb']->read_count!==1){
    fwrite(STDERR,"FAIL: canonical valid envelope did not reach protected store\n");exit(1);
}
echo "PASS: strict CAEP/RISC UUID, subject, issued-at validation before DB; valid envelope reaches storage.\n";
