<?php
/**
 * File02 v12 F02-X24-012: two real WP-CLI processes attempt to resolve the
 * SAME one-time collision case through the canonical-owner test callback.
 * Disposable MariaDB source integration fixture, not actual File00 identity
 * approval or production/browser acceptance.
 */
if ( ! defined('ABSPATH') || ! class_exists('SAUTH_Security_Orchestrator') ) {
    fwrite(STDERR,"FAIL: active WP File02 required\n"); exit(2);
}
global $wpdb;
$mode=isset($args[0])?(string)$args[0]:'';
$option='sauth_qa_collision_race_case';
$table=SAUTH_Activator::table('recovery_changes');
function f02_collision_race_token($id) {
    return hash_hmac('sha256','CI COLLISION PROOF|'.$id,wp_salt('auth'));
}
function f02_collision_race_fingerprint() {
    return SA_Security::client_fingerprint();
}
function f02_collision_race_error($message) {
    fwrite(STDERR,'FAIL: '.$message."\n"); exit(1);
}
if ('seed'===$mode) {
    if (false!==get_option($option,false)) f02_collision_race_error('stale fixture marker');
    $id=strtolower(wp_generate_uuid4());
    $proof=f02_collision_race_token($id);
    $cipher=SA_Security::encrypt($proof);
    $payload=SA_Security::encrypt(wp_json_encode(array('identifier'=>'ci-only@example.invalid','reason'=>'possible_existing_account')));
    if (!$cipher||!$payload||$proof!==SA_Security::decrypt($cipher)) f02_collision_race_error('encryption setup failed');
    $now=current_time('mysql',true);
    $ok=$wpdb->insert($table,array(
      'public_id'=>$id,'user_id'=>0,'change_kind'=>'collision_resolution',
      'payload_ciphertext'=>$payload,'owner_token_ciphertext'=>$cipher,
      'fingerprint_hash'=>f02_collision_race_fingerprint(),'status'=>'pending',
      'apply_after'=>$now,'created_at'=>$now,'updated_at'=>$now,
    ),array('%s','%d','%s','%s','%s','%s','%s','%s','%s','%s'));
    if (1!==(int)$ok||!add_option($option,$id,'',false)) f02_collision_race_error('DB seed failed: '.$wpdb->last_error);
    echo "PASS: collision case fixture seeded on real MariaDB\n"; exit;
}
$id=get_option($option,'');
if(!is_string($id)||!preg_match('/^[0-9a-f-]{36}$/',$id)) f02_collision_race_error('fixture missing');
if('worker'===$mode){
    $proof=f02_collision_race_token($id);
    add_filter('sauth_resolve_collision_case_v1',static function($value,$payload,$context) {
        if(!is_array($payload)||($payload['reason']??'')!=='possible_existing_account') {
            f02_collision_race_error('canonical callback received malformed payload');
        }
        if(!add_option('sauth_qa_collision_race_owner_'.getmypid(),'once','',false)) {
            f02_collision_race_error('owner callback duplicate');
        }
        usleep(700000);
        return array('result'=>'allow','user_id'=>1);
    },99,3);
    $res=SAUTH_Security_Orchestrator::resolve_collision_case($id,$proof);
    if(is_array($res)&&($res['result']??'')==='allow'&&(int)($res['user_id']??0)===1) {
        echo "WIN: exactly one collision resolution owner callback\n"; exit;
    }
    if(is_wp_error($res)&&in_array($res->get_error_code(),array('sauth_collision_case_unavailable','sauth_collision_state_race'),true)) {
        echo "LOSE: competing collision callback rejected: ".$res->get_error_code()."\n"; exit;
    }
    f02_collision_race_error(is_wp_error($res)?$res->get_error_code():'unexpected result');
}
if('verify'===$mode){
    $row=$wpdb->get_row($wpdb->prepare("SELECT status,user_id FROM {$table} WHERE public_id=%s",$id),ARRAY_A);
    $receipts=$wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'sauth_qa_collision_race_owner_%'");
    if(!is_array($row)||$row['status']!=='applied'||(int)$row['user_id']!==1||count($receipts)!==1) {
        f02_collision_race_error('double owner or invalid DB end-state: '.wp_json_encode(array('row'=>$row,'receipts_count'=>count($receipts))));
    }
    $wpdb->delete($table,array('public_id'=>$id),array('%s'));
    delete_option($option);
    foreach($receipts as $receipt) delete_option($receipt);
    echo "PASS: one collision owner, one applied row, duplicate denied; fixture cleaned (real MariaDB)\n"; exit;
}
f02_collision_race_error('unknown mode');
