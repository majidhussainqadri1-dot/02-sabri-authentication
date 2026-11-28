<?php
/** File00/02 typed account owner result and subject-binding regression, isolated mocks only. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
define('SMC_AUTHENTICATION_CONTRACT_V11_VERSION','1.1.0');
class SA_Membership_Adapter{static function available(){return true;}}
class SA_Security{
 static function client_fingerprint(){return 'fixture';}
 static function safe_redirect($url,$fallback){return $url==='/complete/'?$url:$fallback;}
}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function sanitize_text_field($v){return (string)$v;}
function sanitize_email($v){return (string)$v;}
function esc_url_raw($v){return (string)$v;}
function sanitize_textarea_field($v){return (string)$v;}
function wp_generate_uuid4(){return '11111111-1111-4111-8111-111111111111';}
class SMC_Authentication_Contract_V11{
 static $reply=[],$calls=0;
 static function register_account($p,$c){++self::$calls;return self::$reply;}
 static function mark_email_verified($uid,$email,$ctx){++self::$calls;return self::$reply;}
 static function get_completion_state($uid,$ctx){++self::$calls;return self::$reply;}
}
require __DIR__.'/../includes/class-sauth-account-contract.php';
$n=0;
function need($v,$label){global $n;if(!$v){fwrite(STDERR,'RED: '.$label.PHP_EOL);exit(1);}++$n;}
$base=['contract'=>'smc.authentication-account','contract_version'=>'1.1.0','result'=>'allow','reason_code'=>'fixture','user_id'=>17,'subject_uuid'=>'11111111-1111-4111-8111-111111111111','missing_steps'=>['profile'],'next_route'=>'/complete/'];
foreach([-17,'17',17.5,true,[],0,null] as $invalid){
 SMC_Authentication_Contract_V11::$reply=array_replace($base,['user_id'=>$invalid]);
 $result=SAUTH_Account_Contract::register_account([]);
 need(($result['result']??'')==='unknown','malformed registration subject accepted');
}
foreach(['mark_email_verified','completion_state'] as $method){
 foreach([-17,18,'17',17.5,true,0,null] as $invalid){
  SMC_Authentication_Contract_V11::$reply=array_replace($base,['user_id'=>$invalid]);
  $res=$method==='mark_email_verified'?SAUTH_Account_Contract::$method(17,'fixture@example.test'):SAUTH_Account_Contract::$method(17);
  need(($res['result']??'')==='unknown','nonmatching owner response accepted: '.$method);
 }
 SMC_Authentication_Contract_V11::$reply=$base;
 foreach([-17,0,17.5,true,[], '17x'] as $invalid){
  $before=SMC_Authentication_Contract_V11::$calls;
  $res=$method==='mark_email_verified'?SAUTH_Account_Contract::$method($invalid,'fixture@example.test'):SAUTH_Account_Contract::$method($invalid);
  need(($res['result']??'')==='unknown'&&SMC_Authentication_Contract_V11::$calls===$before,'malformed request reached owner: '.$method);
 }
 $ok=$method==='mark_email_verified'?SAUTH_Account_Contract::$method(17,'fixture@example.test'):SAUTH_Account_Contract::$method(17);
 need(($ok['result']??'')==='allow','exact native owner result denied: '.$method);
}
SMC_Authentication_Contract_V11::$reply=$base;
$ok=SAUTH_Account_Contract::register_account([]);
need(($ok['result']??'')==='allow'&&($ok['user_id']??null)===17,'typed registration result denied');
echo 'PASS '.$n.' File00 account owner subject/type assertions (isolated mocks)'.PHP_EOL;
