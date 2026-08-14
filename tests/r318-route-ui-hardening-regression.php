<?php
$root=dirname(__DIR__); $plugin=file_get_contents($root.'/includes/class-sa-plugin.php'); $routes=file_get_contents($root.'/includes/class-sauth-canonical-routes.php'); $google=file_get_contents($root.'/templates/google-account.php'); $css=file_get_contents($root.'/assets/css/authentication.css'); $fail=array();
$checks=array(
 array($plugin,"available_for_ui( 'google' )",'passive Google UI does not use non-mutating health projection'),
 array($routes,"home_url( '/account/sessions/' )",'canonical sessions return URL is missing'),
 array($google,'temporarily unhealthy','Google unavailable copy asserts only administrator-disabled state'),
 array($css,'.sa-form-row a,.sa-bottom-text a,.sa-text-link','text actions are not included in the touch-target gate'),
 array($css,'min-height:44px;padding:8px 4px','text action 44px target is absent')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if(false!==strpos($plugin,"SAUTH_Provider_Health::allow_request( 'google' )"))$fail[]='passive File 02 UI still consumes a Google provider probe';
if(false!==strpos($routes,"rawurlencode( home_url( '/account/sessions/' ) )"))$fail[]='canonical sessions return URL is pre-encoded before add_query_arg';
if($fail){fwrite(STDERR,"R318 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R318 route/UI hardening regression PASS ('.(count($checks)+2)." assertions).\n";
