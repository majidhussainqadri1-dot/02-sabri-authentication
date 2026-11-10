<?php
/* F02-X24-012: absent canonical owner leaves protected handoff in its original TTL.
 * This is a structural regression, not real browser/provider acceptance.
 */
$s=file_get_contents(__DIR__.'/../../includes/class-sauth-security-orchestrator.php');
$a=strpos($s,'public static function resolve_collision_post()');
$b=strpos($s,'public static function schedule_recovery_change(',$a);
if($a===false||$b===false){fwrite(STDERR, "FAIL collision POST boundary missing\n");exit(1);}
$body=substr($s,$a,$b-$a);
$call=strpos($body,'self::resolve_collision_case( $case, $proof )');
$unavailable=strpos($body,"'sauth_collision_owner_unavailable'");
$delete=strpos($body,'delete_transient( $handoff_key )');
if($call===false||$unavailable===false||$delete===false||!($call<$unavailable&&$unavailable<$delete)){fwrite(STDERR, "FAIL owner unavailability consumes bearer proof\n");exit(1);}
if(strpos($body,"'case'=>$case")===false||strpos($body,"'status'=>'temporarily_unavailable'")===false){fwrite(STDERR, "FAIL safe retry does not preserve non-secret case handle\n");exit(1);}
echo "PASS protected collision proof retained only on absent owner (structural).\n";
