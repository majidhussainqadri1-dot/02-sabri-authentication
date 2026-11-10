<?php
/** F02-NFR-010: source-level translatable login/signup + included Urdu/Arabic catalogs. */
$root=dirname(__DIR__,2);$domain='sabri-authentication';
foreach(['login','signup'] as $view){
 $text=file_get_contents($root.'/templates/'.$view.'.php');
 if(substr_count($text,"esc_html_e(")<10||strpos($text,"'".$domain."'")===false){fwrite(STDERR, "FAIL translated ".$view." UI\n"); exit(1);}
 if(strpos($text,"is_rtl() ? 'rtl' : 'ltr'")===false){fwrite(STDERR, "FAIL locale-aware direction\n"); exit(1);}
}
foreach(['ur','ar'] as $locale){
 $path=$root.'/languages/'.$domain.'-'.$locale.'.l10n.php';
 if(!is_file($path)){fwrite(STDERR, "FAIL missing ".$locale." language file\n"); exit(1);}
 $catalog=require $path;
 if(!is_array($catalog)||!is_array($catalog['messages']??null)||count($catalog['messages'])<68){fwrite(STDERR, "FAIL incomplete ".$locale." catalog\n"); exit(1);}
 foreach(['Secure Member Access','Create a Verified Account','Password','Confirm password','I accept the current Terms of Use.'] as $msg){
  if(empty($catalog['messages'][$msg])){fwrite(STDERR, "FAIL untranslated ".$locale." ".$msg."\n"); exit(1);}
 }
}
$js=file_get_contents($root.'/assets/js/authentication.js');
if(substr_count($js,'tr(')<16 || strpos($js,'SabriAuthI18n')===false){fwrite(STDERR, "FAIL translatable browser UI\n"); exit(1);}
$loader=file_get_contents($root.'/includes/class-sa-plugin.php');
if(strpos($loader,"'SabriAuthI18n'")===false){fwrite(STDERR, "FAIL localized JS strings not passed from WordPress\n"); exit(1);}
$builder=file_get_contents($root.'/tools/build-package.sh');
if(strpos($builder,"  languages\n")===false){fwrite(STDERR, "FAIL language catalogs omitted from installable package\n"); exit(1);}
echo "PASS WordPress PHP i18n catalogs included; key auth UI strings translatable (static).\n";
