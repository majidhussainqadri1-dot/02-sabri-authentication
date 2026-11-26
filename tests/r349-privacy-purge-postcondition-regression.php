<?php
/** F02-NFR-002: failed transient deletion must retain retry evidence and barrier. */
define('ABSPATH',__DIR__.'/');
$GLOBALS['meta']=[];$GLOBALS['transients']=[];$GLOBALS['fail_delete']=[];$GLOBALS['fail_meta']=[];
function absint($v){return abs((int)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function get_user_meta($u,$k,$s){return $GLOBALS['meta'][$k]??'';}
function update_user_meta($u,$k,$v){$GLOBALS['meta'][$k]=$v;return true;}
function delete_user_meta($u,$k){if(!empty($GLOBALS['fail_meta'][$k]))return false;unset($GLOBALS['meta'][$k]);return true;}
function get_transient($k){return $GLOBALS['transients'][$k]??false;}
function delete_transient($k){if(!empty($GLOBALS['fail_delete'][$k]))return false;unset($GLOBALS['transients'][$k]);return true;}
class SA_Security {public static function random_token($n){return str_repeat('a',2*$n);}}
require __DIR__.'/../includes/class-sauth-privacy-jobs.php';
function need($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: ".$label.PHP_EOL);exit(1);}}
$k='sauth_password_recovery_job_'.str_repeat('a',64);
$j='sauth_email_verification_resend_job_'.str_repeat('b',64);
$foreign='unrelated_transient';
$idx=SAUTH_Privacy_Jobs::JOB_INDEX_META;$active=SAUTH_Privacy_Jobs::ACTIVE_META;$epoch=SAUTH_Privacy_Jobs::EPOCH_META;
$GLOBALS['meta'][$idx]=[$k,$j];$GLOBALS['transients']=[$k=>['synthetic'=>1],$j=>['synthetic'=>2],$foreign=>'keep'];
$GLOBALS['fail_delete'][$k]=true;
need(!SAUTH_Privacy_Jobs::begin_erasure(17),'failed deletion must not report erased');
need($GLOBALS['meta'][$idx]===[$k],'failed key retained for retry');
need($GLOBALS['meta'][$active]==='1','barrier remains active');
need(get_transient($j)===false,'successful job purged');
unset($GLOBALS['fail_delete'][$k]);
need(SAUTH_Privacy_Jobs::begin_erasure(17),'retry successful');
need(get_transient($k)===false,'failed job subsequently removed');
$GLOBALS['meta'][$idx]=[$foreign];
need(!SAUTH_Privacy_Jobs::purge_jobs(17),'foreign index fails closed');
need(get_transient($foreign)==='keep','foreign transient preserved');
$GLOBALS['meta'][$idx]=[];
$GLOBALS['fail_meta'][$epoch]=true;
need(!SAUTH_Privacy_Jobs::finish_erasure(17),'epoch deletion failure reported');
need($GLOBALS['meta'][$active]==='1','barrier retained if epoch deletion fails');
unset($GLOBALS['fail_meta'][$epoch]);
need(SAUTH_Privacy_Jobs::finish_erasure(17),'successful final cleanup');
echo "PASS 11 privacy cleanup postcondition assertions".PHP_EOL;
