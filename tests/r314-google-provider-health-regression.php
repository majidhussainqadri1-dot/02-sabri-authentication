<?php
$root=dirname(__DIR__); $h=file_get_contents($root.'/includes/class-sauth-provider-health.php'); $g=file_get_contents($root.'/includes/class-sa-google-oauth.php'); $r=file_get_contents($root.'/includes/class-sauth-google-registration.php'); $guard=file_get_contents($root.'/includes/class-sauth-provider-http-guard.php'); $fail=array();
$checks=array(
 array($h,'return \'open\' !== $status','half-open Google circuit cannot become visible for a recovery attempt'),
 array($g,"available_for_ui( 'google' )",'Google login/link start still consumes the half-open probe'),
 array($r,"available_for_ui( 'google' )",'Google registration start still consumes the half-open probe'),
 array($guard,'allow_request( $provider )','actual provider HTTP boundary does not own probe acquisition'),
 array($guard,'record_success( $provider','Google provider success is not recorded centrally'),
 array($guard,'record_failure( $provider','Google provider failure is not recorded centrally')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if(false!==strpos($g,"allow_request( 'google' )"))$fail[]='Google OAuth start retains a direct mutating health gate';
if(false!==strpos($r,"&& SAUTH_Provider_Health::allow_request( 'google' )"))$fail[]='Google registration availability retains a direct mutating health gate';
if($fail){fwrite(STDERR,"R314 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R314 Google provider-health regression PASS ('.(count($checks)+2)." assertions).\n";
