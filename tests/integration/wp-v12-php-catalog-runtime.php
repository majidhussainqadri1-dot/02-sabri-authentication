<?php
/**
 * F02-NFR-010: real WordPress 7.0.1 PHP translation-catalog load test.
 * WordPress has already activated File00 + File02 in disposable CI.
 * This does NOT claim device/browser, full locale or live translation QA.
 */
if(!defined('ABSPATH') || !defined('SAUTH_VERSION')){fwrite(STDERR,"FAIL File02 live runtime absent in disposable WP\n");exit(1);}
$plugin=WP_PLUGIN_DIR.'/sabri-authentication/languages/';
$domain='sabri-authentication';
$expected=[
 'ur'=>'محفوظ رکنیت کا داخلہ',
 'ar'=>'دخول الأعضاء الآمن'
];
foreach($expected as $locale=>$target){
 $file=$plugin.'sabri-authentication-'.$locale.'.l10n.php';
 if(!is_file($file)){fwrite(STDERR,"FAIL PHP catalog missing ".$locale."\n");exit(1);}
 unload_textdomain($domain,true);
 $loaded=load_textdomain($domain,$file,$locale);
 $translated=__('Secure Member Access',$domain);
 if(!$loaded || $translated!==$target){
  fwrite(STDERR,"FAIL real WP PHP catalog ".$locale." loaded=".($loaded?'yes':'no')." actual=".wp_json_encode($translated)."\n");exit(1);
 }
}
unload_textdomain($domain,true);
echo "PASS native WordPress 7.0.1 loads Urdu/Arabic .l10n.php catalogs for File02 (disposable).\n";
