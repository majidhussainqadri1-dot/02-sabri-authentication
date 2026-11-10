<?php
/**
 * F02-X24-019 RED-first: configured breach adapter errors/malformed data
 * must not be mistaken for a clean password. No network, no real password.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__.'/');
class WP_Error {
 private $code;
 public function __construct($code,$message=''){$this->code=$code;}
 public function get_error_code(){return $this->code;}
}
function is_wp_error($x){return $x instanceof WP_Error;}
function absint($x){return abs((int)$x);}
$GLOBALS['prefixResponse']=null;
$GLOBALS['prefixCalls']=array();
function apply_filters($hook,$initial,$prefix){
 if($hook!=='sauth_breached_password_prefix_lookup_v1'){fwrite(STDERR, "wrong provider hook\n");exit(1);}
 $GLOBALS['prefixCalls'][]=$prefix;
 return $GLOBALS['prefixResponse'];
}
require_once __DIR__.'/../../includes/class-sauth-password-safety.php';
$pass='F02-Independent-Strong-Phrase-2026-Nonce-Random';
$hash=strtoupper(sha1($pass));$p=substr($hash,0,5);$s=substr($hash,5);
function expect($check,$why){if(!$check){fwrite(STDERR,"RED: ".$why."\n");exit(1);}}
$GLOBALS['prefixResponse']=new WP_Error('upstream_offline');
$result=SAUTH_Password_Safety::check($pass);
expect(is_wp_error($result)&&$result->get_error_code()==='sauth_password_breach_lookup_unavailable','network/provider error falsely returned clean');
$GLOBALS['prefixResponse']="not a valid suffix response\r\n";
$result=SAUTH_Password_Safety::check($pass);
expect(is_wp_error($result),'malformed provider payload falsely returned clean');
$GLOBALS['prefixResponse']=$s.":9\r\n";
$result=SAUTH_Password_Safety::check($pass);
expect(is_wp_error($result)&&$result->get_error_code()==='sauth_password_breached','known breached suffix not denied');
$GLOBALS['prefixResponse']=str_repeat('A',35).":0\r\n";
$result=SAUTH_Password_Safety::check($pass);
expect(true===$result,'valid no-match suffix was denied');
$GLOBALS['prefixResponse']=null;
expect(true===SAUTH_Password_Safety::check($pass),'unconfigured optional provider disabled local-only fallback');
foreach($GLOBALS['prefixCalls'] as $sent)expect($sent===$p&&strlen($sent)===5,'unapproved credential material crossed adapter');
echo "PASS breach provider errors fail closed; valid suffix-only check and local-only policy preserved (mock).\n";
