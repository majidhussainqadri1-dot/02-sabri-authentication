<?php
$root=dirname(__DIR__); $e=file_get_contents($root.'/includes/class-sauth-email-verification.php'); $r=file_get_contents($root.'/includes/class-sa-registration.php'); $fail=array();
$checks=array(
 array($e,"allow_request( 'email' )",'verification delivery ignores email provider circuit'),
 array($e,'sauth_email_delivery_circuit_open','verification delivery circuit has no explicit failure'),
 array($e,'\'pending\' === (string) ( $row[\'status\'] ?? \'\' )','delivery_failed verification cannot be retried immediately'),
 array($r,"allow_request( 'email' )",'password recovery worker ignores email provider circuit')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if($fail){fwrite(STDERR,"R313 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R313 registration/recovery regression PASS ('.count($checks)." assertions).\n";
