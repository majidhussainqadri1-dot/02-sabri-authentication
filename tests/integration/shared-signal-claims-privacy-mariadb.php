<?php
/**
 * F02-NFR-002/006 and F02-X24-015/016:
 * incoming verified CAEP/RISC event claims must not preserve raw secrets or
 * contact identifiers hidden under innocuous claim names. Real disposable DB.
 */
if(!defined('ABSPATH')||!class_exists('SAUTH_Shared_Signals')){exit(2);}
global $wpdb;
$table=SAUTH_Activator::table('shared_signals');
$id=strtolower(wp_generate_uuid4());
try {
    $r=SAUTH_Shared_Signals::ingest(array(
        'event_id'=>$id,'family'=>'caep','type'=>'risk_level_change',
        'user_id'=>1,'issued_at'=>time(),
        'claims'=>array(
            'risk_score'=>35,
            'patient_record_number'=>123456789,
            'beneficiary_id'=>99887766,
            'reason_code'=>'rawbearersecrettoken123456789abcdefghijklmnop',

            'reason'=>'user_lockdown',
            'user_identifier'=>123456,
            'reason_code'=>'Bearer leaked-secret',
            'incident_note'=>'Jane Doe jane.doe@example.test Bearer super-secret-session',
            'contact_hint'=>'+1 (202) 555-0149',
        ),
    ),array('verified'=>true,'source'=>'ci-signal'));
    if(is_wp_error($r)) throw new RuntimeException('fixture rejected: '.$r->get_error_code());
    $encoded=$wpdb->get_var($wpdb->prepare("SELECT claims_json FROM {$table} WHERE event_id=%s",$id));
    $claims=json_decode((string)$encoded,true);
    if(!is_array($claims)||($claims['risk_score']??null)!==35||($claims['reason']??'')!=='user_lockdown') {
        throw new RuntimeException('safe numeric or coded reason claim lost');
    }
    if(array_key_exists('user_identifier',$claims)||array_key_exists('reason_code',$claims)
       ||array_key_exists('patient_record_number',$claims)||array_key_exists('beneficiary_id',$claims)
       ||array_key_exists('incident_note',$claims)||array_key_exists('contact_hint',$claims)) {
        throw new RuntimeException('personal, opaque or descriptive claim was persisted');
    }
    if(preg_match('/jane\\.doe|example\\.test|Bearer|super-secret-session|202\\D*555\\D*0149/i',(string)$encoded)){
        throw new RuntimeException('raw personal/credential data leaked in normalized CAEP claims');
    }
    echo "PASS: verified CAEP data stores safe risk without raw email/phone/bearer (real MariaDB).\n";
} catch(Throwable $e) {
    fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");exit(1);
} finally {
    $wpdb->delete($table,array('event_id'=>$id),array('%s'));
}
