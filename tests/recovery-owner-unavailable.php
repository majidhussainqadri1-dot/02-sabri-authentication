<?php
/** F02-X24-011: missing owner preclaim gate; isolated PHP mock, not live. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/'); define('ARRAY_A', 'ARRAY_A');
define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600);
class WP_Error { private $code; function __construct($code,$message=''){$this->code=$code;} function get_error_code(){return $this->code;} }
class SAUTH_Activator {static function table($k){return 'wp_sauth_'.$k;}}
class SA_Security {static function decrypt($s){return $s;} static function client_fingerprint(){return 'fixture';}}
class DBFixture {
 public $status='pending',$writes=0;
 function prepare($q,...$args){return $q;}
 function get_row($q,$type){return ['public_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','status'=>$this->status,
 'change_kind'=>'email_change','apply_after'=>'2000-01-01 00:00:00','created_at'=>gmdate('Y-m-d H:i:s'),
 'owner_token_ciphertext'=>'proof','payload_ciphertext'=>'{}','fingerprint_hash'=>'fixture','user_id'=>17];}
 function update($table,$v,$where,...$args){++$this->writes;if(($where['status']??'')!==$this->status)return 0;$this->status=$v['status'];return 1;}
}
function absint($v){return abs((int)$v);} function current_time($x,$gmt=false){return gmdate('Y-m-d H:i:s');}
function sanitize_text_field($x){return (string)$x;} function is_wp_error($x){return $x instanceof WP_Error;}
$GLOBALS['registered']=false;$GLOBALS['calls']=0;
function has_filter($name){return $GLOBALS['registered']&&$name==='sauth_apply_recovery_change_v1' ? 10:false;}
function apply_filters($hook,...$args){++$GLOBALS['calls'];return false;}
$wpdb=new DBFixture();
require_once __DIR__.'/../includes/class-sauth-security-orchestrator.php';
$call=function(){return SAUTH_Security_Orchestrator::apply_recovery_change('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','proof');};
$r=$call();
if(!is_wp_error($r)||$r->get_error_code()!=='sauth_recovery_owner_unavailable'||$wpdb->writes||$wpdb->status!=='pending'||$GLOBALS['calls'])exit("FAIL: absent owner consumed claim\n");
$GLOBALS['registered']=true; $r=$call();
if(!is_wp_error($r)||$wpdb->writes!==1||$wpdb->status!=='processing'||$GLOBALS['calls']!==1)exit("FAIL: failed owner did not leave processing\n");
$r=$call();if(!is_wp_error($r)||$GLOBALS['calls']!==1||$wpdb->writes!==1)exit("FAIL: owner replay\n");
echo "PASS absent owner no claim; failed owner processing; retry contained (mock).\n";
