<?php
/**
 * F02-X24-019 opt-in HIBP HTTP adapter verification with zero external calls.
 * Enabling is an operator decision; only the 5-hex range may leave the site.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__.'/');
define('SAUTH_HIBP_PASSWORD_CHECK_ENABLED', true);
define('SAUTH_VERSION', '1.4.0');
class WP_Error {private $c;function __construct($c,$m=''){$this->c=$c;}function get_error_code(){return $this->c;}}
function is_wp_error($v){return $v instanceof WP_Error;}
function absint($x){return abs((int)$x);}
function apply_filters($name,$default,$prefix){return $default;}
$GLOBALS['responseCode']=200;
$GLOBALS['responseBody']='';
$GLOBALS['requestLog']=[];
function wp_safe_remote_get($url,$opts){
 $GLOBALS['requestLog'][]=['url'=>$url,'opts'=>$opts];
 return ['response'=>['code'=>$GLOBALS['responseCode']],'body'=>$GLOBALS['responseBody']];
}
function wp_remote_retrieve_response_code($result){return $result['response']['code'];}
function wp_remote_retrieve_body($result){return $result['body'];}
require_once __DIR__.'/../../includes/class-sauth-password-safety.php';
$pass='F02-kAnon-Unique-Strong-Password-2026';
$hash=strtoupper(sha1($pass));$prefix=substr($hash,0,5);$suffix=substr($hash,5);
$GLOBALS['responseBody']=str_repeat('F',35).":0\r\n";
$r=SAUTH_Password_Safety::check($pass);
if(true!==$r)exit("FAIL valid padded nonmatch\n");
$l=$GLOBALS['requestLog'][0]??[];
if(($l['url']??'')!=='https://api.pwnedpasswords.com/range/'.$prefix)exit("FAIL leaked excess hash or invalid endpoint\n");
$options=$l['opts']??[];
if(($options['redirection']??null)!==0 || ($options['sslverify']??null)!==true || ($options['headers']['Add-Padding']??'')!=='true' || empty($options['limit_response_size']))exit("FAIL strict provider transport options\n");
foreach($GLOBALS['requestLog'] as $record){
 $serialized=json_encode($record);
 if(strpos($serialized,$pass)!==false||strpos($serialized,$hash)!==false)exit("FAIL password or full SHA disclosed\n");
}
$GLOBALS['responseBody']=$suffix.":3\r\n";
$r=SAUTH_Password_Safety::check($pass);
if(!is_wp_error($r)||$r->get_error_code()!=='sauth_password_breached')exit("FAIL breached password accepted\n");
$GLOBALS['responseCode']=503;$GLOBALS['responseBody']='';
$r=SAUTH_Password_Safety::check($pass);
if(!is_wp_error($r)||$r->get_error_code()!=='sauth_password_breach_lookup_unavailable')exit("FAIL outage accepted\n");
$GLOBALS['responseCode']=200;$GLOBALS['responseBody']="malformed";
$r=SAUTH_Password_Safety::check($pass);
if(!is_wp_error($r))exit("FAIL malformed range response accepted\n");
echo "PASS exact fixed HIBP range, padding, no credential leak, breached/failed provider reject (offline mock).\n";
