<?php
/**
 * F02-NFR-003/008: read-only operational check must surface stale owner claims
 * and expired-but-pending collision cases, without exposing secrets/identifiers.
 * Real disposable WordPress/MariaDB fixture. No production writes.
 */
if ( ! defined('ABSPATH') || ! class_exists('SAUTH_Operations') ) { exit(2); }
global $wpdb;
$table = SAUTH_Activator::table('recovery_changes');
$created = array();
function f02_reconciliation_check() {
    foreach (SAUTH_Operations::system_check() as $row) {
        if (($row['label']??'') === 'Recovery claim reconciliation backlog') return $row;
    }
    return null;
}
try {
    $now = current_time('mysql', true);
    $old = gmdate('Y-m-d H:i:s',time()-7200);
    foreach (array(
        array('collision_resolution','processing',$old,$old,0),
        array('collision_resolution','pending',$old,$old,0),
    ) as $config) {
        $id = strtolower(wp_generate_uuid4());
        $saved = $wpdb->insert($table,array(
            'public_id'=>$id,'user_id'=>$config[4], 'change_kind'=>$config[0],
            'payload_ciphertext'=>SA_Security::encrypt('{}'),
            'owner_token_ciphertext'=>SA_Security::encrypt('ci-test-only'),
            'fingerprint_hash'=>str_repeat('a',64), 'status'=>$config[1],
            'apply_after'=>$now,'created_at'=>$config[2],'updated_at'=>$config[3],
        ),array('%s','%d','%s','%s','%s','%s','%s','%s','%s','%s'));
        if(1!==(int)$saved){throw new RuntimeException('fixture insert failed: '.$wpdb->last_error);}
        $created[]=$id;
    }
    $bad=f02_reconciliation_check();
    if(!is_array($bad)||($bad['status']??'')!=='fail'){
        throw new RuntimeException('stale recovery backlog invisible to System Check');
    }
    if(preg_match('/(?:[0-9a-f]{8}-){4}|ci-test-only|payload|token|email/i',(string)($bad['evidence']??''))){
        throw new RuntimeException('System Check leaks sensitive claim details');
    }
    // P0 operator queue inspection is read-only, aggregate-only and
    // distinguishes unconfirmed owner-processing from stale collision handoffs.
    if(!is_callable(array('SAUTH_Operations','reconciliation_summary'))) {
        throw new RuntimeException('read-only recovery queue inspection is missing');
    }
    $summary=SAUTH_Operations::reconciliation_summary();
    if(!is_array($summary)
       || (int)($summary['stale_processing']??0)<1
       || (int)($summary['stale_collision_pending']??0)<1) {
        throw new RuntimeException('operator queue summary is missing both unresolved classes');
    }
    foreach($summary as $key=>$value) {
        if(!is_string($key)||!is_int($value)||$value<0) {
            throw new RuntimeException('queue exposes unbounded or personally identifying data');
        }
    }
    foreach($created as $id) {
        if(1!==(int)$wpdb->delete($table,array('public_id'=>$id),array('%s'))){throw new RuntimeException('fixture cleanup failed');}
    }
    $created=array();
    $clean_summary=SAUTH_Operations::reconciliation_summary();
    if(!is_array($clean_summary)
       || (int)($clean_summary['stale_processing']??-1)!==(int)$summary['stale_processing']-1
       || (int)($clean_summary['stale_collision_pending']??-1)!==(int)$summary['stale_collision_pending']-1) {
        throw new RuntimeException('operator aggregates did not reconcile after fixture deletion');
    }
    $good=f02_reconciliation_check();
    if(!is_array($good)||($good['status']??'')!=='ok'){
        throw new RuntimeException('recovered backlog still marked failed');
    }
    echo "PASS: stale owner claims and collision backlog visible, non-sensitive and recoverable (real MariaDB).\n";
} catch(Throwable $e) {
    fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");
    exit(1);
} finally {
    foreach($created as $id){$wpdb->delete($table,array('public_id'=>$id),array('%s'));}
}
