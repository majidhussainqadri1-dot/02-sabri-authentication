<?php
/** Isolated WordPress/MariaDB test of storage and denied guest decisions only. */
defined('ABSPATH') || exit;
if(!defined('WP_CLI')||!WP_CLI||wp_get_environment_type()!=='staging'
 ||wp_parse_url((string)get_option('siteurl'),PHP_URL_HOST)!=='example.test'
 ||getenv('SAUTH_DISPOSABLE_RECOVERY_QA')!=='1'
 ||DB_NAME!=='wordpress'||DB_HOST!=='127.0.0.1:3306'||realpath(ABSPATH)!=='/tmp/wordpress'){
 throw new RuntimeException('Refusing non-disposable recovery fixture');
}
function recovery_need($v,$label){if(!$v){throw new RuntimeException($label);}echo 'PASS '.$label.PHP_EOL;}
recovery_need(get_current_user_id()===0,'unauthenticated native CLI actor');
$payload=['reason'=>'email_collision','identifier'=>'fixture@example.test'];
$context=['contract_version'=>'1.0.0','case'=>wp_generate_uuid4(),'fingerprint'=>str_repeat('a',64)];
$result=SAUTH_Account_Contract::existing_email_collision($payload,$context,false);
recovery_need(($result['result']??'')==='deny','actual optional File00 provider denies guest');
require_once ABSPATH.'wp-admin/includes/user.php';
$uid=wp_insert_user(['user_login'=>'recovery-ci-'.wp_generate_password(12,false,false),'user_email'=>wp_generate_uuid4().'@example.test','user_pass'=>wp_generate_password(32),'role'=>'subscriber']);
if(is_wp_error($uid)||!is_int($uid)||$uid<=1){throw new RuntimeException('disposable fixture unavailable');}
$key=SAUTH_Security_Orchestrator::LOCKDOWN_META;
$old=['active'=>true,'generation'=>wp_generate_uuid4(),'started_at'=>time()];
$new=['active'=>true,'generation'=>wp_generate_uuid4(),'started_at'=>time()];
$method=new ReflectionMethod('SAUTH_Security_Orchestrator','release_lockdown_generation');
$method->setAccessible(true);
try{
 update_user_meta($uid,$key,$old);
 recovery_need(!SAUTH_Security_Orchestrator::clear_lockdown_by_canonical_recovery($uid),'no native owner approval denies lockdown release');
 update_user_meta($uid,$key,$new);
 recovery_need(!$method->invoke(null,$uid,$old)&&get_user_meta($uid,$key,true)===$new,'stale generation cannot release newer native record');
 delete_user_meta($uid,$key);
 recovery_need(!$method->invoke(null,$uid,$old)&&!metadata_exists('user',$uid,$key),'missing native row never recreated');
 update_user_meta($uid,$key,$old);
 add_user_meta($uid,$key,$new);
 recovery_need(!$method->invoke(null,$uid,$old)&&count(get_user_meta($uid,$key,false))===2,'ambiguous native rows refused');
 delete_user_meta($uid,$key);
 update_user_meta($uid,$key,$old);
 recovery_need($method->invoke(null,$uid,$old)&&get_user_meta($uid,$key,true)['active']===false,'exact native storage CAS updates only matched generation');
}finally{
 wp_delete_user($uid);
}
echo "PASS 7 native recovery storage and guest-denial checks (no positive owner identity ceremony)".PHP_EOL;
