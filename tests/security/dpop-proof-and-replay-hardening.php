<?php
/**
 * File02 v12 F02-X24-020: executable red-first proof of DPoP JWT iat
 * semantics, public JWK enforcement, bounded atomic replay and option cleanup.
 * Isolated WP mocks; not an external crypto adapter certification.
 */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ . '/' );
class WP_Error {
    private $code;
    public function __construct($code,$message='') { $this->code=$code; }
    public function get_error_code() { return $this->code; }
}
$GLOBALS['dpop_options']=array();
$GLOBALS['dpop_verifier_calls']=0;
function absint($x) { return abs((int)$x); }
function wp_json_encode($v,$options=0) { return json_encode($v,$options); }
function wp_parse_url($url) { return parse_url($url); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)); }
function add_option($key,$value,$deprecated='',$autoload=false) {
    if(isset($GLOBALS['dpop_options'][$key])) return false;
    $GLOBALS['dpop_options'][$key]=$value; return true;
}
function get_option($key,$default=0) { return $GLOBALS['dpop_options'][$key]??$default; }
function delete_option($key) { unset($GLOBALS['dpop_options'][$key]); return true; }
function wp_schedule_single_event($time,$hook,$args) { return true; }
function apply_filters($hook,$default,...$args) {
    if($hook==='sauth_dpop_verify_signature_v1') { ++$GLOBALS['dpop_verifier_calls']; return true; }
    return $default;
}
function fail_dpop($message) { fwrite(STDERR,"FAIL: ".$message."\n");exit(1); }
function make_dpop($iat,$jti='0123456789abcdef',$private=false) {
    $jwk=array('kty'=>'EC','crv'=>'P-256','x'=>str_repeat('A',43),'y'=>str_repeat('B',43));
    if($private) $jwk['d']=str_repeat('C',43);
    $h=array('typ'=>'dpop+jwt','alg'=>'ES256','jwk'=>$jwk);
    $c=array('htm'=>'POST','htu'=>'https://example.test/wp-json/auth','iat'=>$iat,'jti'=>$jti);
    $encode=static function($v){return rtrim(strtr(base64_encode(json_encode($v)),'+/','-_'),'=');};
    return $encode($h).'.'.$encode($c).'.mock-signature';
}
function require_denied($result,$label) {
    if(!($result instanceof WP_Error)) fail_dpop($label.' allowed');
}
require_once __DIR__.'/../../includes/class-sauth-dpop.php';

// A negative UNIX timestamp is neither a valid signed issuance time nor
// a way to submit a fresh proof; PHP absint() would incorrectly accept it.
$GLOBALS['dpop_verifier_calls']=0;
$bad=SAUTH_DPoP::validate(make_dpop(-time()),'POST','https://example.test/wp-json/auth');
require_denied($bad,'negative iat');
if($GLOBALS['dpop_verifier_calls']!==0||count($GLOBALS['dpop_options'])!==0) {
    fail_dpop('invalid iat reached verifier or poisoned replay store');
}

// Valid proof must be verified once and replay must fail before verifier.
$valid=make_dpop(time(),'abcdefghijklmnopqrstuv123456');
$ok=SAUTH_DPoP::validate($valid,'POST','https://example.test/wp-json/auth');
if(!is_array($ok)||($ok['result']??'')!=='allow') fail_dpop('valid proof rejected');
$before=$GLOBALS['dpop_verifier_calls'];
$again=SAUTH_DPoP::validate($valid,'POST','https://example.test/wp-json/auth');
require_denied($again,'identical replay');
if($GLOBALS['dpop_verifier_calls']!==$before) fail_dpop('replay hit signature verifier again');

// Key containing private material other than d must not enter public adapter.
$jwt=make_dpop(time(),'abcdefghijklmnopprivatejti');
$parts=explode('.',$jwt);
$header=json_decode(base64_decode(strtr($parts[0],'-_','+/')),true);
$header['jwk']['p']='PRIVATE_RSA_SECRET';
$parts[0]=rtrim(strtr(base64_encode(json_encode($header)),'+/','-_'),'=');
$before=$GLOBALS['dpop_verifier_calls'];
require_denied(SAUTH_DPoP::validate(implode('.',$parts),'POST','https://example.test/wp-json/auth'),'private JWK member');
if($GLOBALS['dpop_verifier_calls']!==$before) fail_dpop('private JWK reached signature adapter');

// A scheduled callback must not delete a fresh claim or an arbitrary option.
$key=array_key_first($GLOBALS['dpop_options']);
if(!preg_match('/^sauth_dpop_[a-f0-9]{64}$/',$key)) fail_dpop('unexpected claim key');
SAUTH_DPoP::expire_replay('sauth_dpop_foreign_option');
if(isset($GLOBALS['dpop_options']['sauth_dpop_foreign_option'])) fail_dpop('foreign option altered');
SAUTH_DPoP::expire_replay($key);
if(!isset($GLOBALS['dpop_options'][$key])) fail_dpop('fresh replay claim deleted');
$GLOBALS['dpop_options'][$key]=time()-361;
SAUTH_DPoP::expire_replay($key);
if(isset($GLOBALS['dpop_options'][$key])) fail_dpop('aged replay claim retained');
echo "PASS: DPoP negative iat/private JWK deny, one-time claim and scoped expiry (mock).\n";
