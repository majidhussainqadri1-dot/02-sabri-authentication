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
$safety_expected=[
 'ur'=>'میں نے حفاظتی قواعد پڑھ لیے ہیں؛ میں داخلے کی اسناد خفیہ رکھوں گا/گی، جعلی پیغامات پہچانوں گا/گی، مریضوں کی معلومات محفوظ رکھوں گا/گی اور مشتبہ رسائی کی اطلاع دوں گا/گی۔',
 'ar'=>'لقد قرأت قواعد الأمان، وسأحافظ على سرية بيانات الدخول، وأحذر من التصيد، وأحمي سجلات المرضى، وأبلّغ عن أي وصول مشبوه.'
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
 $safety_ack=__('I have read the safety rules and will keep login credentials private, recognize phishing, protect patient records and report suspicious access.',$domain);
 if($safety_ack!==$safety_expected[$locale]){
  fwrite(STDERR,"FAIL File02 V12.3 safety acknowledgement locale ".$locale." actual=".wp_json_encode($safety_ack)."\n");exit(1);
 }
}
unload_textdomain($domain,true);
echo "PASS native WordPress 7.0.1 loads Urdu/Arabic .l10n.php catalogs and V12.3 safety acknowledgement for File02 (disposable).\n";
