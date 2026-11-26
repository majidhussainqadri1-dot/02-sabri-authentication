<?php
/** F02-FR-004/CEN-02: local redirect origin must not expand with global WP allowlists. */
define('ABSPATH',__DIR__.'/');
function home_url($path='/'){return 'https://example.test'.$path;}
function wp_validate_redirect($url,$fallback=''){return $url===''?$fallback:$url;} // Intentionally permissive plugin allowlist.
function need($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: ".$label.PHP_EOL);exit(1);}}
require __DIR__.'/../includes/class-sa-security.php';
foreach(['https://example.test/account/','https://EXAMPLE.test:443/account/?a=1','/account/?a=1'] as $url){
 need(SA_Security::safe_redirect($url,'')===$url,'local URL preserved');
}
foreach(['https://other.test/account/','http://example.test/account/','https://example.test:444/account/','//example.test/account/','//other.test/account/','https://user@example.test/account/','https://example.test.evil.test/account/','/\\other.test/','javascript:alert(1)',"/x\r\nLocation: elsewhere",'https://example.test:0/'] as $url){
 need(SA_Security::safe_redirect($url,'')==='','foreign or invalid origin rejected');
}
need(SA_Security::safe_redirect('https://other.test/','https://example.test/fallback/')==='https://example.test/fallback/','local explicit fallback');
need(SA_Security::safe_redirect('https://other.test/','https://other.test/fallback/')==='','foreign fallback rejected');
need(SA_Security::safe_redirect('https://other.test/')===home_url('/'),'default home fallback');
echo "PASS 17 same-origin redirect assertions (permissive WP allowlist mock)".PHP_EOL;
