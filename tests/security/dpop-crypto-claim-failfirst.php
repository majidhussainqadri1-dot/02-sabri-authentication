<?php
/** F02-X24-020 RED-first: unverified signatures must never own replay claims.
 * A durable option is created only after crypto approval. Isolated WP mock.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
class WP_Error{private $code;function __construct($c,$m=''){$this->code=$c;}function get_error_code(){return $this->code;}}
function absint($x){return abs((int)$x);}
function wp_json_encode($x,$opts=0){return json_encode($x,$opts);}
function wp_parse_url($s){return parse_url($s);}
$GLOBALS['replay']=[];$GLOBALS['atVerifier']=-1;$GLOBALS['verifyPass']=false;$GLOBALS['verifiedCalls']=0;
function add_option($k,$v,$obsolete='',$autoload=false){if(isset($GLOBALS['replay'][$k]))return false;$GLOBALS['replay'][$k]=$v;return true;}
function get_option($k,$fallback=false){return array_key_exists($k,$GLOBALS['replay'])?$GLOBALS['replay'][$k]:$fallback;}
function delete_option($k){unset($GLOBALS['replay'][$k]);return true;}
function wp_schedule_single_event($time,$hook,$args){return true;}
function apply_filters($hook,$initial,...$args){++$GLOBALS['verifiedCalls'];$GLOBALS['atVerifier']=count($GLOBALS['replay']);return $GLOBALS['verifyPass'];}
function jwt(){
 $b=static function($x){return rtrim(strtr(base64_encode(json_encode($x)),'+/','-_'),'=');};
 return $b(['typ'=>'dpop+jwt','alg'=>'ES256','jwk'=>['kty'=>'EC','crv'=>'P-256','x'=>str_repeat('A',43),'y'=>str_repeat('B',43)]])
 .'.'.$b(['htm'=>'POST','htu'=>'https://example.test/wp-json/sabri-auth/v1/data','iat'=>time(),'jti'=>'dpopproofonce2026abcdefghij'])
 .'.unsigned-but-mocked';
}
require_once __DIR__.'/../../includes/class-sauth-dpop.php';
$proof=jwt();$url='https://example.test/wp-json/sabri-auth/v1/data';
$result=SAUTH_DPoP::validate($proof,'POST',$url);
if(!($result instanceof WP_Error)||$GLOBALS['atVerifier']!==0||count($GLOBALS['replay'])!==0)exit("RED: unverified DPoP signature owned durable replay claim before crypto proof\n");
$GLOBALS['verifyPass']=true;
$valid=SAUTH_DPoP::validate($proof,'POST',$url);
if(!is_array($valid)||($valid['result']??'')!=='allow'||count($GLOBALS['replay'])!==1)exit("FAIL signed proof unable to establish replay claim\n");
$before=$GLOBALS['verifiedCalls'];
$replay=SAUTH_DPoP::validate($proof,'POST',$url);
if(!($replay instanceof WP_Error)||$replay->get_error_code()!=='sauth_dpop_replay'||$GLOBALS['verifiedCalls']!==$before)exit("FAIL replay re-verified or succeeded\n");
echo "PASS rejected signature never claims nonce; approved proof claims once; replay rejected (mock).\n";
