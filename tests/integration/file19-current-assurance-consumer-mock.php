<?php
/**
 * Source-only contract test: actual File19 consumer, synthetic identity and
 * authentication evidence. This never creates sessions or grants real roles.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__.'/');
define('SAUTH_PASSKEY_CONTRACT_VERSION','1.0.0');
function absint($v){return abs((int)$v);}
function get_userdata($uid){return (object)['ID'=>$uid];}
function get_user_locale($uid){return 'en_US';}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function is_user_logged_in(){return $GLOBALS['test_actor']>0;}
function get_current_user_id(){return $GLOBALS['test_actor'];}
function apply_filters($hook,$value,...$args){return $value;}
function current_user_can($cap){return false;} // No native mutation privileges in this test.
final class SMC_Contracts {
    public static $claims=[];
    public static function assertions($uid){return self::$claims;}
}
final class SAUTH_Passkey_Runtime {
    public static $evidence=[];
    public static function current_assurance($uid){return self::$evidence;}
}
require_once __DIR__.'/../../../file19/19-unified-notifications/includes/class-sun-auth.php';
$provider=file_get_contents(__DIR__.'/../../includes/class-sauth-passkey-runtime.php');
if(!is_string($provider)){fwrite(STDERR,"Provider source missing\n");exit(1);}
$checks=0;
function check($result,$name){global $checks;if(!$result){fwrite(STDERR,"FAIL: ".$name.PHP_EOL);exit(1);}++$checks;echo "PASS: ".$name.PHP_EOL;}
foreach(['public static function current_assurance',"'owner' => 'file02'","'method' => 'webauthn_passkey'","'contract_version' => SAUTH_Passkeys::CONTRACT_VERSION","'verified_at' => absint"] as $marker){
    check(strpos($provider,$marker)!==false,'provider field: '.$marker);
}
$GLOBALS['test_actor']=17;
$validClaims=['user_id'=>17,'contract_version'=>'1.2.3','status'=>'active','eligible'=>true,'approved'=>true,'identity_documents_current'=>true,'suspended'=>false,'guardian_verified'=>true,'account_class'=>'founder'];
$validEvidence=['owner'=>'file02','method'=>'webauthn_passkey','passkey_asserted'=>true,'contract_version'=>'1.0.0','verified_at'=>time()-10];
SMC_Contracts::$claims=$validClaims;
SAUTH_Passkey_Runtime::$evidence=$validEvidence;
$consumer=new SUN_Auth();
check($consumer->assertions(17)['step_up_verified']===true,'actual consumer accepts expected synthetic evidence shape');
check($consumer->is_governance_actor_eligible(17,true)===true,'mock canonical identity and current evidence recognized');
check($consumer->can_send_bulk()===false,'authentication evidence alone does not grant native send capability');
foreach([
    'missing'=>[],
    'stale'=>array_replace($validEvidence,['verified_at'=>time()-301]),
    'future'=>array_replace($validEvidence,['verified_at'=>time()+61]),
    'foreign_owner'=>array_replace($validEvidence,['owner'=>'file00']),
    'wrong_method'=>array_replace($validEvidence,['method'=>'password']),
    'wrong_version'=>array_replace($validEvidence,['contract_version'=>'0.0.0']),
    'not_asserted'=>array_replace($validEvidence,['passkey_asserted'=>false]),
] as $label=>$evidence){
    SAUTH_Passkey_Runtime::$evidence=$evidence;
    check($consumer->is_governance_actor_eligible(17,true)===false,$label.' denied');
}
SAUTH_Passkey_Runtime::$evidence=$validEvidence;
$GLOBALS['test_actor']=18;
check($consumer->assertions(17)['step_up_verified']===false,'foreign actor denied');
$GLOBALS['test_actor']=0;
check($consumer->assertions(17)['step_up_verified']===false,'logged out denied');
$GLOBALS['test_actor']=17;
SMC_Contracts::$claims=array_replace($validClaims,['suspended'=>true]);
check($consumer->is_governance_actor_eligible(17,true)===false,'suspended owner denied despite positive authentication evidence');
SMC_Contracts::$claims=array_replace($validClaims,['account_class'=>'member']);
check($consumer->is_governance_actor_eligible(17,true)===false,'ordinary member denied despite positive authentication evidence');
echo "PASS ".$checks." File19 consumer/source contract checks; no actual login, ceremony or authorization acceptance claimed.".PHP_EOL;
