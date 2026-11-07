<?php
/**
 * F02-X24-020 real WordPress 7.0.1 / MariaDB 11.4 DPoP replay/expiry.
 * A test-only signed-proof verifier is injected. This does NOT certify a
 * real provider, JOSE library or external signature verification.
 */
if (!defined('ABSPATH') || !class_exists('SAUTH_DPoP')) {fwrite(STDERR,"FAIL: File02 runtime required\n");exit(2);}
global $wpdb;
$test_id = 'ci-dpop-'.strtolower(wp_generate_uuid4());
$issued = time();
$jwk = array('kty'=>'EC','crv'=>'P-256','x'=>str_repeat('A',43),'y'=>str_repeat('B',43));
$header = array('typ'=>'dpop+jwt','alg'=>'ES256','jwk'=>$jwk);
$encode = static function($value) {return rtrim(strtr(base64_encode(wp_json_encode($value)),'+/','-_'),'=');};
$proof = static function($iat,$jti,$header_override=null) use ($header,$encode) {
    $h=is_array($header_override)?$header_override:$header;
    return $encode($h).'.'.$encode(array(
        'htm'=>'POST','htu'=>'https://example.test/wp-json/sabri-auth/v1/test',
        'iat'=>$iat,'jti'=>$jti
    )).'.ci-signature-not-production';
};
$verifier_calls = 0;
add_filter('sauth_dpop_verify_signature_v1', static function($default,$signed,$signature,$pub,$alg) use (&$verifier_calls) {
    $verifier_calls++;
    return $signature==='ci-signature-not-production' && $alg==='ES256' && is_array($pub);
},99,5);
function f02_dpop_real_assert($condition,$why) {if(!$condition) {fwrite(STDERR,'FAIL: '.$why."\n");exit(1);}}
$url='https://example.test/wp-json/sabri-auth/v1/test';
$bad=SAUTH_DPoP::validate($proof(-$issued,$test_id.'-negative'),'POST',$url);
f02_dpop_real_assert(is_wp_error($bad),'negative iat passed');
f02_dpop_real_assert($verifier_calls===0,'negative iat hit verifier');
$private=$header;
$private['jwk']['p']='ci-private-member';
$bad=SAUTH_DPoP::validate($proof($issued,$test_id.'-private',$private),'POST',$url);
f02_dpop_real_assert(is_wp_error($bad),'JWK with private material passed');
f02_dpop_real_assert($verifier_calls===0,'private JWK hit verifier');
$jwt=$proof($issued,$test_id.'-valid');
$one=SAUTH_DPoP::validate($jwt,'POST',$url);
f02_dpop_real_assert(is_array($one)&&($one['result']??'')==='allow','valid test adapter denied');
$twice=SAUTH_DPoP::validate($jwt,'POST',$url);
f02_dpop_real_assert(is_wp_error($twice)&&$twice->get_error_code()==='sauth_dpop_replay','identical JWT replay allowed');
f02_dpop_real_assert($verifier_calls===1,'duplicate signature verification occurred');
$key='sauth_dpop_'.hash('sha256',(string)$one['jkt'].'|'.$test_id.'-valid');
f02_dpop_real_assert((int)get_option($key,0)>0,'real wp_options replay key absent');
$autoload=(string)$wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name=%s",$key));
f02_dpop_real_assert(!in_array($autoload,array('yes','on','auto-on'),true),'replay option autoload enabled');
$event=wp_next_scheduled(SAUTH_DPoP::REPLAY_EXPIRE_HOOK,array($key));
f02_dpop_real_assert(false!==$event,'expiry event not scheduled');
do_action(SAUTH_DPoP::REPLAY_EXPIRE_HOOK,$key);
f02_dpop_real_assert(false!==get_option($key,false),'fresh key removed by cleanup');
update_option($key,time()-361,false);
do_action(SAUTH_DPoP::REPLAY_EXPIRE_HOOK,$key);
f02_dpop_real_assert(false===get_option($key,false),'aged key survived cleanup');
wp_clear_scheduled_hook(SAUTH_DPoP::REPLAY_EXPIRE_HOOK,array($key));
echo "PASS: signed-iAT guard, public JWK, DB-backed DPoP replay and scoped expiry (real WordPress/MariaDB; mock verifier).\n";
