<?php
/** File 02 NFR-010: extended public authentication translation/RTL source gate. */
declare(strict_types=1);
$root=dirname(__DIR__,2);
$minimum=[
 'access-required.php'=>5,
 'complete-profile.php'=>5,
 'forgot-password.php'=>6,
 'google-account.php'=>12,
 'google-verify.php'=>4,
 'reset-password.php'=>9,
 'partials/google-button.php'=>1,
];
foreach($minimum as $view=>$count){
 $s=file_get_contents($root.'/templates/'.$view);
 if(substr_count($s,'esc_html_e(')<$count){fwrite(STDERR,"FAIL untranslated ".$view."\n");exit(1);}
 if(strpos($view,'partials/')!==0&&strpos($s,"is_rtl() ? 'rtl' : 'ltr'")===false){fwrite(STDERR,"FAIL missing RTL on ".$view."\n");exit(1);}
}
foreach(['ur','ar'] as $language){
 $messages=require $root.'/languages/sabri-authentication-'.$language.'.l10n.php';
 $messages=$messages['messages']??[];
 if(!is_array($messages)||count($messages)<100){fwrite(STDERR,"FAIL incomplete ".$language." catalog\n");exit(1);}
 foreach(['Verified Account Required','Forgot Password','Reset Your Password','Unlink Google','Continue with Linked Google Account'] as $key){
  if(!isset($messages[$key])||''===trim((string)$messages[$key])){fwrite(STDERR,"FAIL ".$language." key ".$key."\n");exit(1);}
 }
}
echo "PASS extended auth/Google/recovery views and translation catalogs (static; browser QA pending).\n";
