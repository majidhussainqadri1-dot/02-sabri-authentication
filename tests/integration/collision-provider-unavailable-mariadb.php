<?php
/**
 * F02-X24-012 + File00 canonical owner boundary:
 * missing verified identity-owner adapter must not irreversibly claim a
 * valid pending collision case. Real disposable WordPress/MariaDB fixture;
 * the later allow callback is test-only, NOT a File00 identity decision.
 */
if(!defined('ABSPATH')||!class_exists('SAUTH_Security_Orchestrator')){exit(2);}
global $wpdb;
$table=SAUTH_Activator::table('recovery_changes');
$id=strtolower(wp_generate_uuid4());
$proof=hash_hmac('sha256','ci-only-owner-proof|'.$id,wp_salt('auth'));
$now=current_time('mysql',true);
function f02_missing_owner_fail($reason){fwrite(STDERR,'FAIL: '.$reason."\n");exit(1);}
$owner_adapter=static function($default,$payload,$context) {
    return array('result'=>'allow','user_id'=>1);
};
try {
    if(false!==has_filter('sauth_resolve_collision_case_v1')) {
        f02_missing_owner_fail('test isolation unexpectedly has a real collision owner callback');
    }
    $saved=$wpdb->insert($table,array(
        'public_id'=>$id,'user_id'=>0,'change_kind'=>'collision_resolution',
        'payload_ciphertext'=>SA_Security::encrypt(wp_json_encode(array('identifier'=>'ci@example.invalid','reason'=>'possible_existing_account'))),
        'owner_token_ciphertext'=>SA_Security::encrypt($proof),
        'fingerprint_hash'=>SA_Security::client_fingerprint(),
        'status'=>'pending','apply_after'=>$now,'created_at'=>$now,'updated_at'=>$now
    ),array('%s','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s'));
    if(1!==(int)$saved)f02_missing_owner_fail('valid fixture could not be seeded');
    $missing=SAUTH_Security_Orchestrator::resolve_collision_case($id,$proof);
    if(!is_wp_error($missing)||$missing->get_error_code()!=='sauth_collision_owner_unavailable') {
        f02_missing_owner_fail('missing owner not rejected before an irreversible claim');
    }
    $state=(string)$wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE public_id=%s",$id));
    if($state!=='pending')f02_missing_owner_fail('missing provider consumed the valid pending case');
    add_filter('sauth_resolve_collision_case_v1',$owner_adapter,99,3);
    $resolved=SAUTH_Security_Orchestrator::resolve_collision_case($id,$proof);
    if(!is_array($resolved)||($resolved['result']??'')!=='allow') {
        f02_missing_owner_fail('later test-only canonical owner could not resolve preserved case');
    }
    $status=(string)$wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE public_id=%s",$id));
    if($status!=='applied')f02_missing_owner_fail('test-only owner resolution not durably applied');
    echo "PASS: missing File00 collision owner rejected before claim, later approved test-only adapter may resolve preserved case.\n";
} finally {
    remove_filter('sauth_resolve_collision_case_v1',$owner_adapter,99);
    $wpdb->delete($table,array('public_id'=>$id),array('%s'));
}
