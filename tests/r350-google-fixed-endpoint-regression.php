<?php
/** F02-FR-004/005: sensitive OAuth calls must not follow redirects. */
define('ABSPATH',__DIR__.'/');
function wp_parse_url($u,$component){return parse_url($u,$component);}
function absint($n){return abs((int)$n);}
require __DIR__.'/../includes/class-sauth-provider-http-guard.php';
function need($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: ".$label.PHP_EOL);exit(1);}}
foreach(['class-sa-google-oauth.php','class-sauth-google-registration.php'] as $f){
 $s=file_get_contents(__DIR__.'/../includes/'.$f);
 $start=strpos($s,'$response = wp_remote_post(');
 $end=strpos($s,'$claims = json_decode', $start);
 need($start!==false&&$end!==false,'fixed OAuth callback scope '.$f);
 $slice=substr($s,$start,$end-$start);
 need(preg_match_all("/'redirection'\\s*=>\\s*0\\b/",$slice)===2,'both sensitive requests forbid following redirects '.$f);
 need(strpos($slice,'TOKEN_ENDPOINT')!==false&&strpos($slice,'TOKENINFO_URL')!==false,'approved endpoint constants '.$f);
}
$args=SAUTH_Provider_HTTP_Guard::bound_args(['redirection'=>0,'sslverify'=>false], 'https://oauth2.googleapis.com/token');
need($args['redirection']===0&&$args['sslverify']===true&&$args['reject_unsafe_urls']===true,'guard preserves caller zero-redirect and HTTPS verification');
$other=['redirection'=>5];
need(SAUTH_Provider_HTTP_Guard::bound_args($other,'https://example.test')===$other,'unrelated provider arguments unchanged');
echo "PASS 8 fixed Google OAuth endpoint/source and guard checks (no network)".PHP_EOL;
