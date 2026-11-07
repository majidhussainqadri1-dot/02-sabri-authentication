<?php
/**
 * File02 v12 F02-NFR-003/004/006/008: strict 1000-row admin queue scan,
 * fail-closed when DB is missing, truncated or unavailable. Never return
 * subject identifiers, secrets or payloads. PHP mock; real MariaDB runs
 * in recovery-reconciliation-health.php.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__.'/');
define('HOUR_IN_SECONDS',3600);
define('ARRAY_A','ARRAY_A');
class WP_Error {public $code;public function __construct($code,$msg=''){ $this->code=$code; }}
class SAUTH_Activator {public static function table($name){return 'wp_sauth_'.$name;}}
class FakeQueueDB {
    public $last_error='';
    public $rows=array();
    public $exists='wp_sauth_recovery_changes';
    public $query='';
    public function prepare($sql, ...$args){return $sql;}
    public function esc_like($s){return $s;}
    public function get_var($sql){return $this->exists;}
    public function get_results($sql,$format){$this->query=$sql;return $this->rows;}
}
function fail_summary($why){fwrite(STDERR,'FAIL: '.$why."\n");exit(1);}
require_once __DIR__.'/../../includes/class-sauth-operations.php';
$GLOBALS['wpdb']=new FakeQueueDB();
$wpdb=$GLOBALS['wpdb'];
$now=gmdate('Y-m-d H:i:s');
$old=gmdate('Y-m-d H:i:s',time()-7200);
$wpdb->rows=array(
 array('status'=>'processing','change_kind'=>'email_change','created_at'=>$old,'updated_at'=>$old),
 array('status'=>'processing','change_kind'=>'provider_unlink','created_at'=>$now,'updated_at'=>$now),
 array('status'=>'pending','change_kind'=>'collision_resolution','created_at'=>$old,'updated_at'=>$old),
 array('status'=>'pending','change_kind'=>'collision_resolution','created_at'=>$now,'updated_at'=>$now),
 array('status'=>'pending','change_kind'=>'email_change','created_at'=>$old,'updated_at'=>$old),
);
$summary=SAUTH_Operations::reconciliation_summary();
$expect=array('stale_processing'=>1,'stale_collision_pending'=>1,'recent_processing'=>1,'fresh_collision_pending'=>1,'other_pending_recovery'=>1);
if($summary!==$expect) fail_summary('bounded summary counts wrong: '.json_encode($summary));
if(strpos($wpdb->query,'LIMIT 1001')===false) fail_summary('queue inspection not explicitly bounded');
foreach($summary as $key=>$value){if(!is_string($key)||!is_int($value)) fail_summary('personalized queue row leaked');}
$wpdb->rows=array_fill(0,1001,$wpdb->rows[0]);
if(!(SAUTH_Operations::reconciliation_summary() instanceof WP_Error)) fail_summary('truncated queue falsely healthy');
$wpdb->rows=array();$wpdb->exists='';
if(!(SAUTH_Operations::reconciliation_summary() instanceof WP_Error)) fail_summary('missing table falsely healthy');
$wpdb->exists='wp_sauth_recovery_changes';$wpdb->last_error='server_error';
if(!(SAUTH_Operations::reconciliation_summary() instanceof WP_Error)) fail_summary('database failure falsely healthy');
echo "PASS: File02 aggregate recovery queue is read-only, bounded and fail-closed (mock).\n";
