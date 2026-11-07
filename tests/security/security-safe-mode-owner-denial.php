<?php
/**
 * F02-NFR-001/003/008: Safe Mode must block collision owner side effects,
 * even when an action is called from PHP without going through admin_init.
 * Isolated PHP mock, not a live endpoint bypass attempt.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
define('ARRAY_A','ARRAY_A');
define('DAY_IN_SECONDS',86400);
define('HOUR_IN_SECONDS',3600);
define('MINUTE_IN_SECONDS',60);
class WP_Error {private $code;public function __construct($code,$msg=''){$this->code=$code;}public function get_error_code(){return $this->code;}}
class SAUTH_Operations {public static function safe_mode(){return true;}}
class SAUTH_Activator {public static function table($name){return 'wp_sauth_'.$name;}}
class SA_Security {
    public static function random_token($bytes){return str_repeat('x',$bytes);}
    public static function encrypt($v){return base64_encode((string)$v);}
    public static function client_fingerprint(){return 'fingerprint-test';}
}
class SpyDB {
    public $reads=0,$writes=0;
    public function get_row($sql,$shape=null){++$this->reads;return null;}
    public function prepare($sql,...$args){return $sql;}
    public function insert($table,$fields,$format=null){++$this->writes;return 1;}
    public function update($table,$fields,$where,$fmt=null,$where_fmt=null){++$this->writes;return 1;}
}
$GLOBALS['wpdb']=new SpyDB();
function sanitize_text_field($v){return (string)$v;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function absint($v){return abs((int)$v);}
function wp_generate_uuid4(){return '12345678-1234-4234-8234-123456789abc';}
function wp_json_encode($v){return json_encode($v);}
function current_time($type,$gmt=false){return gmdate('Y-m-d H:i:s');}
function wp_salt($purpose=''){return 'CI salt';}
function add_query_arg($args,$value){return 'https://example.test/resolve-account/';}
function home_url($p=''){return 'https://example.test'.$p;}
function set_transient($key,$v,$ttl){return true;}
function apply_filters($hook,$default,...$args){return $default;}
function fail_sm($s){fwrite(STDERR,'FAIL: '.$s."\n");exit(1);}
require_once __DIR__.'/../../includes/class-sauth-security-orchestrator.php';
$cases=array(
    array('create',static function(){return SAUTH_Security_Orchestrator::create_collision_case('someone@example.invalid');}),
    array('resolve',static function(){return SAUTH_Security_Orchestrator::resolve_collision_case('12345678-1234-4234-8234-123456789abc','proof');}),
    array('apply',static function(){return SAUTH_Security_Orchestrator::apply_recovery_change('12345678-1234-4234-8234-123456789abc','proof');}),
    array('schedule',static function(){return SAUTH_Security_Orchestrator::schedule_recovery_change(17,'email_change',array('next_email'=>'test@example.invalid'));})
);
foreach($cases as $case){
    try{$result=$case[1]();}
    catch(Throwable $error){fail_sm($case[0].' hit unguarded runtime: '.$error->getMessage());}
    if(!($result instanceof WP_Error)||$result->get_error_code()!=='sauth_safe_mode_active'){
        fail_sm($case[0].' did not fail closed in Safe Mode');
    }
}
if($GLOBALS['wpdb']->reads||$GLOBALS['wpdb']->writes)fail_sm('Safe Mode denial executed DB side effects');
$ops=(string)file_get_contents(__DIR__.'/../../includes/class-sauth-operations.php');
if(strpos($ops,"'sauth_resolve_collision'")===false) fail_sm('public admin-post route not in Safe Mode gate');
echo "PASS: Safe Mode gates collision create/resolve and recovery schedule/apply before DB (mock).\n";
