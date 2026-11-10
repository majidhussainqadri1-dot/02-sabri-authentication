<?php
/** F02-X24-020: DPoP htu must not smuggle URI fragment/query/userinfo
 * and JOSE alg must be structurally consistent with public JWK key type.
 * Crypto signature itself still supplied only by explicitly approved verifier.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
class WP_Error{private $code;function __construct($c,$m=''){$this->code=$c;}function get_error_code(){return $this->code;}}
function absint($v){return abs((int)$v);}
function wp_json_encode($value,$flags=0){return json_encode($value,$flags);}
function wp_parse_url($url){return parse_url($url);}
$GLOBALS['opts']=[];$GLOBALS['verifierCalls']=0;$GLOBALS['counter']=0;
function add_option($key,$v,$old='',$autoload=false){if(isset($GLOBALS['opts'][$key]))return false;$GLOBALS['opts'][$key]=$v;return true;}
function get_option($key,$fallback=false){return $GLOBALS['opts'][$key]??$fallback;}
function delete_option($key){unset($GLOBALS['opts'][$key]);return true;}
function wp_schedule_single_event($at,$hook,$args){return true;}
function apply_filters($hook,$initial,...$args){++$GLOBALS['verifierCalls'];return true;}
function make_proof($htu,$alg='ES256',$keytype='EC'){
 $jwk=$keytype==='EC'?['kty'=>'EC','crv'=>'P-256','x'=>str_repeat('A',43),'y'=>str_repeat('B',43)]:['kty'=>'RSA','e'=>'AQAB','n'=>str_repeat('C',80)];
 $h=['typ'=>'dpop+jwt','alg'=>$alg,'jwk'=>$jwk];
 $c=['htm'=>'POST','htu'=>$htu,'iat'=>time(),'jti'=>'f02test'.(++$GLOBALS['counter']).'abcdefghijklmno'];
 $encode=static function($x){return rtrim(strtr(base64_encode(json_encode($x)),'+/','-_'),'=');};
 return $encode($h).'.'.$encode($c).'.mock-verifier-sig';
}
require __DIR__.'/../../includes/class-sauth-dpop.php';
$url='https://example.test/wp-json/auth';
foreach([
 ['https://example.test/wp-json/auth?query=1','ES256','EC'],
 ['https://example.test/wp-json/auth#fragment','ES256','EC'],
 ['https://anonymous@example.test/wp-json/auth','ES256','EC'],
 [$url,'ES256','RSA'],
 [$url,'RS256','EC']
] as $bad){
 $before=$GLOBALS['verifierCalls'];
 $r=SAUTH_DPoP::validate(make_proof(...$bad),'POST',$url);
 if(!($r instanceof WP_Error)||$before!==$GLOBALS['verifierCalls'])exit("RED: malformed DPoP htu or inconsistent JOSE alg reached signature adapter\n");
}
$r=SAUTH_DPoP::validate(make_proof($url),'POST',$url);
if(!is_array($r)||($r['result']??'')!=='allow')exit("FAIL: valid canonical proof rejected\n");
echo "PASS strict DPoP htu and JOSE alg/JWK type before verifier (mock).\n";
