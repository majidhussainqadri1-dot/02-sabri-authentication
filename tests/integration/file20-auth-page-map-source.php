<?php
/** Exact File20 navigation consumer and File02 canonical map source assertions. */
declare(strict_types=1);
$producer=file_get_contents(__DIR__.'/../../includes/class-sa-activator.php');
$consumer=file_get_contents(__DIR__.'/../../../file20/sabri-unified-application-shell/includes/class-integrations.php');
if(!is_string($producer)||!is_string($consumer)){fwrite(STDERR,"Exact source unavailable\n");exit(1);}
$n=0;
function source_check($ok,$name){global $n;if(!$ok){fwrite(STDERR,"FAIL: ".$name.PHP_EOL);exit(1);}++$n;}
source_check(strpos($producer,"update_option( 'sauth_page_map', \$page_map, false )")!==false,'canonical map write');
source_check(strpos($producer,"update_option( 'sa_page_map', \$page_map, false )")!==false,'compatibility map write');
foreach(['login'=>'sabri_auth_login','signup'=>'sabri_auth_signup','complete'=>'sabri_auth_complete_profile','forgot'=>'sabri_auth_forgot_password'] as $key=>$shortcode){
    source_check(preg_match("/'".preg_quote($key,'/')."'\\s*=>\\s*array\\([^\\n]+\\[".preg_quote($shortcode,'/')."\\]/",$producer)===1,'producer map/shortcode '.$key);
    source_check(strpos($consumer,"array( 'sa_page_map', '".$key."' )")!==false,'actual consumer map '.$key);
    source_check(strpos($consumer,"'".$shortcode."'")!==false,'actual consumer shortcode '.$key);
}
echo "PASS ".$n." File20/File02 source map and shortcode checks; runtime routes, sharing and browser acceptance remain separate.".PHP_EOL;
