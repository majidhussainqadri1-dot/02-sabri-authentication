<?php
/**
 * File02 F02-X24-015/016 F02-NFR-003:
 * verified CAEP/RISC signals must not be acknowledged if authoritative
 * WordPress session revocation could not be verified. Failed containment
 * cannot be silently replayed as successful via an idempotent event receipt.
 * Controlled PHP mock, not a real IdP or production WordPress test.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__.'/');
define('DAY_IN_SECONDS',86400);
define('ARRAY_A','ARRAY_A');
class WP_Error {
    private $code;
    public function __construct($code,$message=''){$this->code=$code;}
    public function get_error_code(){return $this->code;}
}
class SAUTH_Activator {public static function table($s){return 'wp_sauth_'.$s;}}
class SAUTH_Session_Manager {
    public static $allow=false;
    public static $calls=0;
    public static function revoke_user_sessions($id,$reason) {
        ++self::$calls; return self::$allow;
    }
}
class SAUTH_Passkey_Runtime {
    public static $allow=true;
    public static function invalidate_user_assurance($user) {return self::$allow;}
}
class SAUTH_Security_Orchestrator {
    const COMPROMISE_META='_ci_compromise';
    public static function timeline_event($user,$event,$severity,$context) {return true;}
}
class F02ContainDB {
    public $last_error='';
    public $rows=array();
    public $writes=0;
    public function prepare($sql,...$args){return $sql;}
    public function get_row($sql,$output=null){
        foreach($this->rows as $key=>$row) {
            if(strpos($sql,$key)!==false) return $row;
        }
        // The mock prepare() above intentionally does not interpolate.
        return null;
    }
    public function insert($table,$data,$format) {
        $this->rows[$data['event_id']]=$data;++$this->writes;return 1;
    }
    public function update($table,$data,$where,$fmt=null,$where_fmt=null) {
        $id=$where['event_id']??'';
        if(!isset($this->rows[$id])) return false;
        $this->rows[$id]=array_merge($this->rows[$id],$data);++$this->writes;return 1;
    }
}
$GLOBALS['wpdb']=new F02ContainDB();
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$s));}
function sanitize_text_field($s){return trim((string)$s);}
function absint($s){return abs((int)$s);}
function wp_json_encode($x){return json_encode($x);}
function current_time($type,$gmt=false){return gmdate('Y-m-d H:i:s');}
function update_user_meta($id,$key,$v){return true;}
function do_action($hook,$data){return true;}
function die_if($condition,$why){if($condition){fwrite(STDERR,'FAIL: '.$why."\n");exit(1);}}
require_once __DIR__.'/../../includes/class-sauth-shared-signals.php';
$id='12345678-1234-4234-8234-123456789a11';
$event=array('event_id'=>$id,'family'=>'risc','type'=>'credential_compromise',
    'user_id'=>17,'issued_at'=>time(),'claims'=>array('reason'=>'unrecognized_event'));
$ctx=array('verified'=>true,'source'=>'ci');
$one=SAUTH_Shared_Signals::ingest($event,$ctx);
die_if(!($one instanceof WP_Error)||$one->get_error_code()!=='sauth_signal_containment_unverified',
    'failed WordPress session revocation was falsely acknowledged');
die_if(($GLOBALS['wpdb']->rows[$id]['status']??'')!=='needs_reconciliation',
    'failed containment lacked durable operator reconciliation state');
die_if(SAUTH_Session_Manager::$calls!==1,'initial security containment not attempted exactly once');
$repeat=SAUTH_Shared_Signals::ingest($event,$ctx);
die_if(!($repeat instanceof WP_Error)||$repeat->get_error_code()!=='sauth_signal_containment_unverified',
    'duplicate event falsely changed failed containment into success');
die_if(SAUTH_Session_Manager::$calls!==1,'uncertain owner side effect automatically replayed');
SAUTH_Session_Manager::$allow=true;
$id2='12345678-1234-4234-8234-123456789a12';
$event['event_id']=$id2;
$ok=SAUTH_Shared_Signals::ingest($event,$ctx);
die_if($ok!==$id2,'successful containment was denied');
die_if(($GLOBALS['wpdb']->rows[$id2]['status']??'')!=='active','successful signal not active');
echo "PASS: CAEP/RISC cannot acknowledge unverified containment and never auto-retries uncertain side effects (PHP mock).\n";
