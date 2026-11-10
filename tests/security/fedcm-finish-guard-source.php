<?php
/* F02-X24-023 structural: fail-closed FedCM completion, not provider acceptance. */
$s=file_get_contents(__DIR__.'/../includes/class-sauth-modern-auth.php');
$a=strpos($s,'public static function fedcm_finish_ajax()');$b=strpos($s,'public static function expire_fedcm_claim(',$a);
if($a===false||$b===false)exit("FAIL boundary\n");
$f=substr($s,$a,$b-$a);
$guard=strpos($f,'SAUTH_Operations::safe_mode()');
$owner=strpos($f,"has_filter( 'sauth_fedcm_verify_token_v1' )");
$claim=strpos($f,'add_option( $claim_key');
$verify=strpos($f,"apply_filters( 'sauth_fedcm_verify_token_v1'");
if($guard===false||$owner===false||$claim===false||$verify===false||!($guard<$claim&&$owner<$claim&&$claim<$verify))exit("FAIL FedCM guard ordering\n");
echo "PASS FedCM Safe Mode and owner readiness before atomic claim (static).\n";
