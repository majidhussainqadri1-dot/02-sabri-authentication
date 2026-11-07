<?php
/**
 * F02-X24-015/016: an IdP-signed event id is an immutable domain claim.
 * Same event+user+type is idempotent; reused event_id with another subject
 * or event type MUST NOT receive a false successful receipt.
 * Disposable CI WP7.0.1 / MariaDB11.4; verifier is test-only.
 */
if(!defined('ABSPATH') || !class_exists('SAUTH_Shared_Signals')) {fwrite(STDERR,"FAIL: active File02 needed\n");exit(2);}
global $wpdb;
$table=SAUTH_Activator::table('shared_signals');
$event_id=strtolower(wp_generate_uuid4());
$start=time();
$first=array(
    'event_id'=>$event_id,'family'=>'caep','type'=>'risk_level_change',
    'user_id'=>1,'issued_at'=>$start,'claims'=>array('risk_score'=>42)
);
function f02_signal_assert($ok,$why){if(!$ok){fwrite(STDERR,'FAIL: '.$why."\n");exit(1);}}
try {
    $a=SAUTH_Shared_Signals::ingest($first,array('verified'=>true,'source'=>'ci-issuer'));
    f02_signal_assert(!is_wp_error($a) && $a===$event_id,'valid signal rejected');
    $again=SAUTH_Shared_Signals::ingest($first,array('verified'=>true,'source'=>'ci-issuer'));
    f02_signal_assert(!is_wp_error($again) && $again===$event_id,'same event duplicate not idempotent');
    $other=$first; $other['user_id']=2;
    $conflict=SAUTH_Shared_Signals::ingest($other,array('verified'=>true,'source'=>'ci-issuer'));
    f02_signal_assert(is_wp_error($conflict),'reused event_id wrongly acknowledged with a different user');
    $other=$first; $other['type']='verification_change';
    $conflict=SAUTH_Shared_Signals::ingest($other,array('verified'=>true,'source'=>'ci-issuer'));
    f02_signal_assert(is_wp_error($conflict),'reused event_id wrongly acknowledged with a changed signal');
    $other=$first; $other['claims']=array('risk_score'=>999);
    $conflict=SAUTH_Shared_Signals::ingest($other,array('verified'=>true,'source'=>'ci-issuer'));
    f02_signal_assert(is_wp_error($conflict),'reused event_id wrongly acknowledged with altered claims');
    $rows=$wpdb->get_results($wpdb->prepare("SELECT user_id,family,signal_type FROM {$table} WHERE event_id=%s",$event_id),ARRAY_A);
    f02_signal_assert(is_array($rows)&&count($rows)===1&&(int)$rows[0]['user_id']===1 &&$rows[0]['signal_type']==='risk_level_change','immutable signal was replaced or duplicated');
    echo "PASS: CAEP event_id immutable; exact replay idempotent; cross-user/type false receipt denied (real MariaDB).\n";
} finally {
    $wpdb->delete($table,array('event_id'=>$event_id),array('%s'));
}
