<?php
/** F02-NFR-010: security events originate in UTC and display through WP timezone. */
$root=dirname(__DIR__,2);
$source=file_get_contents($root.'/includes/class-sauth-security-orchestrator.php');
$start=strpos($source,'public static function render_security_center()');
$end=strpos($source,'public static function render_collision_resolution()',$start);
if($start===false||$end===false){fwrite(STDERR, "FAIL timeline render missing\n"); exit(1);}
$view=substr($source,$start,$end-$start);
foreach(["strtotime( (string)\$row['created_at'] . ' UTC' )","get_option('date_format'","get_option('time_format'","wp_date( \$auth_time_format, \$auth_time_utc )"] as $marker)if(strpos($view,$marker)===false){fwrite(STDERR, "FAIL timezone marker ".$marker."\n"); exit(1);}
if(strpos($view,"esc_html((string)\$row['created_at'])")!==false){fwrite(STDERR, "FAIL raw UTC shown\n"); exit(1);}
echo "PASS timeline dates use WordPress locale and site timezone (structural).\n";
