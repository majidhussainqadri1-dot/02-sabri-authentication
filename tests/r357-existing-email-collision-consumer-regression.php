<?php
/** Narrow optional provider adapter; no actual identity or sign-in in test. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
define('SMC_AUTHENTICATION_CONTRACT_V11_VERSION','1.1.0');
class SA_Membership_Adapter {static $ready=true;static function available(){return self::$ready;}}
class SMC_Authentication_Contract_V11 {
 static $called=0,$throw=false;
 static function register_account($p,$c){}
 static function mark_email_verified($u,$e,$c){}
 static function get_completion_state($u,$c){}
 static function resolve_existing_email_collision_v1($p,$c,$commit){
  ++self::$called;if(self::$throw)throw new RuntimeException('fixture');
  return ['result'=>'allow','user_id'=>17,'scope'=>'same_account_no_merge'];
 }
}
require __DIR__.'/../includes/class-sauth-account-contract.php';
function need($v,$label){if(!$v){fwrite(STDERR,"FAIL ".$label.PHP_EOL);exit(1);}}
$p=['reason'=>'email_collision','identifier'=>'member@example.test'];$c=['case'=>'fixture'];
need(SAUTH_Account_Contract::existing_email_collision($p,$c,false)['scope']==='same_account_no_merge','optional narrow contract available');
SA_Membership_Adapter::$ready=false;
need(SAUTH_Account_Contract::existing_email_collision($p,$c,false)['result']==='deny'&&SMC_Authentication_Contract_V11::$called===1,'provider outage does not invoke');
SA_Membership_Adapter::$ready=true;
need(SAUTH_Account_Contract::existing_email_collision($p,$c,'true')['result']==='deny','invalid commit type denied');
SMC_Authentication_Contract_V11::$throw=true;
need(SAUTH_Account_Contract::existing_email_collision($p,$c,true)['result']==='unknown','owner exception closed');
$source=file_get_contents(__DIR__.'/../includes/class-sauth-security-orchestrator.php');
need(strpos($source,'$confirmed === $self_subject')!==false&&strpos($source,"'phishing_resistant'=>true")!==false,'source binds same principal and strong auth');
$registration=file_get_contents(__DIR__.'/../includes/class-sa-registration.php');
need(strpos($registration,"'email_collision','phone_collision','account_collision'")!==false,'actual canonical reason codes reach resolution route');
echo "PASS 6 optional collision provider adapter/source assertions (mock)".PHP_EOL;
