<?php
/** F02-X24-011: unreadable recovery payload must not consume the one-time claim.
 * Deterministic mock; not WordPress/MariaDB integration.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A','ARRAY_A');
define('DAY_IN_SECONDS',86400);
define('HOUR_IN_SECONDS',3600);
class WP_Error {
    private $code;
    public function __construct($code,$message='') {$this->code=$code;}
    public function get_error_code(){return $this->code;}
}
class SAUTH_Activator {public static function table($name){return 'wp_sauth_'.$name;}}
class SA_Security {
    public static $payload = '{broken json';
    public static function decrypt($cipher){return $cipher === 'payload' ? self::$payload : $cipher;}
    public static function client_fingerprint(){return 'fingerprint';}
}
class PayloadDB {
    public $updates=0;
    public $status='pending';
    public function prepare($sql,...$args){return $sql;}
    public function get_row($sql,$mode){
        return ['public_id'=>'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'status'=>$this->status,'change_kind'=>'email_change',
            'apply_after'=>'2000-01-01 00:00:00',
            'owner_token_ciphertext'=>'proof','payload_ciphertext'=>'payload',
            'user_id'=>17,'fingerprint_hash'=>'fingerprint'];
    }
    public function update($table,$data,$where,...$rest){++$this->updates;$this->status=$data['status'];return 1;}
}
function absint($v){return abs((int)$v);}
function current_time($type,$gmt=false){return gmdate('Y-m-d H:i:s');}
$ownerCalls=0;
function apply_filters($hook,...$args){global $ownerCalls;++$ownerCalls;return true;}
$wpdb=new PayloadDB();
require_once __DIR__.'/../includes/class-sauth-security-orchestrator.php';
foreach (['{broken json','null','false','42','"text"'] as $bad) {
    SA_Security::$payload=$bad;
    $wpdb->status='pending';$wpdb->updates=0;$ownerCalls=0;
    $result=SAUTH_Security_Orchestrator::apply_recovery_change('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa','proof');
    if (!($result instanceof WP_Error) || $wpdb->updates!==0 || $ownerCalls!==0 || $wpdb->status!=='pending') {
        fwrite(STDERR,"FAIL malformed payload: ".json_encode($bad)." updates={$wpdb->updates} owner={$ownerCalls} state={$wpdb->status}\n");
        exit(1);
    }
}
echo "Unreadable payload denied before claim/owner (mock only).\n";
