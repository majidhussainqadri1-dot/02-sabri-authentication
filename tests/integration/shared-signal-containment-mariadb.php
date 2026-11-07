<?php
/**
 * File 02 v12 F02-X24-015/016 + F02-NFR-003:
 * disposable WordPress 7.0.1/MariaDB 11.4 fault injection for failure of the
 * real WordPress session registry update. No external CAEP verifier is claimed.
 * Never run against a production/staging database.
 */
if (!defined('ABSPATH') || !class_exists('SAUTH_Shared_Signals')) {
    fwrite(STDERR,"FAIL: File02 WordPress runtime is required\n");exit(2);
}
global $wpdb;
$table=SAUTH_Activator::table('shared_signals');
$id=strtolower(wp_generate_uuid4());
$id2=strtolower(wp_generate_uuid4());
$events_broadcast=0;
$injected=0;
$listener=static function($envelope) use (&$events_broadcast){++$events_broadcast;};
$inject=static function($sql) use (&$injected) {
    if (stripos((string)$sql,'UPDATE')!==false
        && stripos((string)$sql,'sauth_auth_sessions')!==false
        && stripos((string)$sql,"status='revoked'")!==false) {
        ++$injected;
        return "UPDATE ci_invalid_session_table_for_fault_injection SET status='revoked'";
    }
    return $sql;
};
function f02_contain_db_assert($ok,$why){if(!$ok)throw new RuntimeException($why);}
$event=array('event_id'=>$id,'family'=>'caep','type'=>'session_revoked',
    'user_id'=>1,'issued_at'=>time(),'claims'=>array('reason'=>'user_lockdown'));
$context=array('verified'=>true,'source'=>'ci-only-test-verified');
try {
    add_action('sauth_shared_security_signal_v1',$listener,20,1);
    add_filter('query',$inject,99,1);
    $result=SAUTH_Shared_Signals::ingest($event,$context);
    remove_filter('query',$inject,99);
    f02_contain_db_assert(is_wp_error($result)&&$result->get_error_code()==='sauth_signal_containment_unverified',
        'failed database revocation was incorrectly acknowledged');
    f02_contain_db_assert($injected>=1,'test failed to inject a real session DB failure');
    $state=(string)$wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE event_id=%s",$id));
    f02_contain_db_assert($state==='needs_reconciliation',
        'failed containment not durably flagged for operator reconciliation');
    f02_contain_db_assert($events_broadcast===0,
        'security event broadcast before confirmed containment');
    $same=SAUTH_Shared_Signals::ingest($event,$context);
    f02_contain_db_assert(is_wp_error($same)&&$same->get_error_code()==='sauth_signal_containment_unverified',
        'failed security event incorrectly idempotent-success on duplicate');
    f02_contain_db_assert($injected===1,'uncertain WordPress side effect was replayed');
    $ok=$event;$ok['event_id']=$id2;$ok['issued_at']=time();
    $success=SAUTH_Shared_Signals::ingest($ok,$context);
    f02_contain_db_assert($success===$id2,
        is_wp_error($success)?'valid containment failure: '.$success->get_error_code():'valid containment not accepted');
    $active=(string)$wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE event_id=%s",$id2));
    f02_contain_db_assert($active==='active','success did not reach verified active state');
    f02_contain_db_assert($events_broadcast===1,'successful containment notification not emitted once');
    echo "PASS: DB fault -> durable needs_reconciliation and no replay; normal real WordPress containment -> active.\n";
} catch(Throwable $e) {
    fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");
    exit(1);
} finally {
    remove_filter('query',$inject,99);
    remove_action('sauth_shared_security_signal_v1',$listener,20);
    $wpdb->delete($table,array('event_id'=>$id),array('%s'));
    $wpdb->delete($table,array('event_id'=>$id2),array('%s'));
}
