<?php
/* F02-X24-023 structural: fail-closed FedCM completion, not provider acceptance. */
$s=file_get_contents(dirname(__DIR__,2).'/includes/class-sauth-modern-auth.php');
$a=strpos($s,'public static function fedcm_finish_ajax()');$b=strpos($s,'public static function expire_fedcm_claim(',$a);
if($a===false||$b===false){fwrite(STDERR, "FAIL boundary\n");exit(1);}
$f=substr($s,$a,$b-$a);
$guard=strpos($f,'SAUTH_Operations::safe_mode()');
$owner=strpos($f,"has_filter( 'sauth_fedcm_verify_token_v1' )");
$claim=strpos($f,'add_option( $claim_key');
$verify=strpos($f,"apply_filters( 'sauth_fedcm_verify_token_v1'");
if($guard===false||$owner===false||$claim===false||$verify===false||!($guard<$claim&&$owner<$claim&&$claim<$verify)){fwrite(STDERR, "FAIL FedCM guard ordering\n");exit(1);}
$binding=strpos($f,'SAUTH_Session_Manager::session_binding_ready');$risk=strpos($f,'SAUTH_Login_Risk::record_successful_login');$success=strpos($f,'wp_send_json_success');
if($binding===false||$risk===false||$success===false||!($binding<$risk&&$risk<$success)){fwrite(STDERR,"FAIL FedCM success before durable session and risk checks\n");exit(1);}
echo "PASS FedCM guards, once-only claim and session/risk postcondition ordering (static).\n";
