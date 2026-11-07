<?php
/**
 * File02 v12 F02-X24-013/015/016 + NFR-003:
 * an unavailable shared-signal database cannot mean "zero risk" or be
 * silently treated as a safe verified signal. Real disposable MariaDB
 * fault injection; never run on production data.
 */
if(!defined('ABSPATH')||!class_exists('SAUTH_Shared_Signals')){exit(2);}
$injected=0;
$inject=static function($sql) use (&$injected) {
    if(stripos((string)$sql,'SELECT family,signal_type')!==false
       && stripos((string)$sql,'sauth_shared_signals')!==false) {
        ++$injected;
        return 'SELECT family,signal_type FROM ci_invalid_signals_table';
    }
    return $sql;
};
function f02_risk_fail($message){fwrite(STDERR,'FAIL: '.$message."\n");exit(1);}
try {
    add_filter('query',$inject,99,1);
    $score=SAUTH_Shared_Signals::risk_score_for_user(1);
    if(!is_wp_error($score)||$score->get_error_code()!=='sauth_shared_risk_unavailable'){
        f02_risk_fail('SQL failure incorrectly treated as empty/zero security signal risk');
    }
    $risk=SAUTH_Security_Orchestrator::risk_v2(1);
    if(!is_array($risk)||($risk['score']??0)!==100
       ||!in_array('shared_security_unavailable',(array)($risk['reasons']??array()),true)){
        f02_risk_fail('adaptive risk engine did not fail closed with explainable reason');
    }
    if($injected<2){f02_risk_fail('risk source query fault was not exercised by both paths');}
    remove_filter('query',$inject,99);
    $zero=SAUTH_Shared_Signals::risk_score_for_user(1);
    if(is_wp_error($zero)||!is_int($zero)){
        f02_risk_fail('healthy signal store did not preserve integer risk interface');
    }
    echo "PASS: verified CAEP/RISC store failure returns typed error, adaptive risk fails closed and recovers on real MariaDB.\n";
} catch(Throwable $e) {
    fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");exit(1);
} finally {
    remove_filter('query',$inject,99);
}
