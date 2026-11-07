<?php
/**
 * File 02 F02-X24-015/016: event_id is immutable even if two verified
 * processes both initially see no row and only one wins UNIQUE insertion.
 * Controlled WP/DB mock for deterministic DB race, not a real IdP verifier.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__.'/');
define('ARRAY_A','ARRAY_A');
define('DAY_IN_SECONDS',86400);
class WP_Error {
    private $code;
    public function __construct($code,$message=''){ $this->code=(string)$code; }
    public function get_error_code(){ return $this->code; }
}
class SAUTH_Activator {public static function table($type){return 'wp_sauth_'.$type;}}
function sanitize_key($s){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$s));}
function sanitize_text_field($s){return trim(strip_tags((string)$s));}
function absint($x){return abs((int)$x);}
function wp_json_encode($x){return json_encode($x);}
function current_time($type,$gmt=false){return gmdate('Y-m-d H:i:s');}
function apply_filters($hook,$v){return $v;}
function fail_sig($why){fwrite(STDERR,'FAIL: '.$why."\n");exit(1);}
class SharedSignalRaceDB {
    public $last_error='';
    public $calls=0;
    public $inserts=0;
    public $winner=null;
    public $initialRow=null;
    public function prepare($sql, ...$args){return $sql;}
    public function get_row($sql,$format=null){
        ++$this->calls;
        $this->last_error='';
        return $this->calls===1 ? $this->initialRow : $this->winner;
    }
    public function insert($table,$values,$format){
        ++$this->inserts;
        $this->last_error='Duplicate entry';
        return false;
    }
}
$GLOBALS['wpdb']=new SharedSignalRaceDB();
$event='00112233-4455-4666-8777-8899aabbccdd';
$now=time();
$envelope=array('family'=>'caep','type'=>'risk_level_change',
    'event_id'=>$event,'user_id'=>17,'issued_at'=>$now,'claims'=>array('risk'=>1));
$matching=array('event_id'=>$event,'family'=>'caep','signal_type'=>'risk_level_change','user_id'=>17,'claims_json'=>json_encode(array('risk'=>1)),'status'=>'active');
require_once __DIR__.'/../../includes/class-sauth-shared-signals.php';
$wpdb=$GLOBALS['wpdb'];
$wpdb->winner=$matching;
$result=SAUTH_Shared_Signals::ingest($envelope,array('verified'=>true,'source'=>'ci'));
if($result!==$event||$wpdb->calls!==2||$wpdb->inserts!==1)
    fail_sig('atomic UNIQUE insert race did not reconcile exact duplicate');
$wpdb->calls=0;$wpdb->inserts=0;
$wpdb->winner=array_merge($matching,array('user_id'=>18));
$result=SAUTH_Shared_Signals::ingest($envelope,array('verified'=>true,'source'=>'ci'));
if(!($result instanceof WP_Error)||$result->get_error_code()!=='sauth_signal_event_id_conflict')
    fail_sig('raced event with mismatched user was incorrectly acknowledged');
$wpdb->calls=0;$wpdb->inserts=0;
$wpdb->initialRow=$matching;
$result=SAUTH_Shared_Signals::ingest($envelope,array('verified'=>true,'source'=>'ci'));
if($result!==$event||$wpdb->inserts!==0)
    fail_sig('previous exact event was not idempotent');
$wpdb->initialRow=array_merge($matching,array('claims_json'=>json_encode(array('risk'=>42))));
$result=SAUTH_Shared_Signals::ingest($envelope,array('verified'=>true,'source'=>'ci'));
if(!($result instanceof WP_Error)||$result->get_error_code()!=='sauth_signal_event_id_conflict')
    fail_sig('existing event with altered claims was accepted');
$wpdb->initialRow=array_merge($matching,array('signal_type'=>'verification_change'));
$result=SAUTH_Shared_Signals::ingest($envelope,array('verified'=>true,'source'=>'ci'));
if(!($result instanceof WP_Error)||$result->get_error_code()!=='sauth_signal_event_id_conflict')
    fail_sig('existing event with conflicting type was accepted');
echo "PASS: identical CAEP race idempotent, conflict rejects, no duplicate effects (mock).\n";
