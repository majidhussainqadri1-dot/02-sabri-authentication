<?php
/** F02-NFR-007/009: compatibility routing must preserve foreign identifiers and SQL data. */
define('ABSPATH',__DIR__.'/');
$wpdb=(object)['prefix'=>'wp_'];
class SAUTH_Activator {public static function table($k){return 'wp_sauth_'.($k==='risk_challenges'?'auth_risk_challenges':$k);}}
require __DIR__.'/../includes/class-sauth-storage-router.php';
function need($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: ".$label.PHP_EOL);exit(1);}}
$cases=[
 "SELECT * FROM wp_sa_auth_sessions_backup",
 "SELECT * FROM \x60wp_sa_auth_sessions_archive\x60",
 "SELECT 'FROM wp_sa_auth_sessions' AS note",
 'SELECT "UPDATE wp_sa_auth_outbox" AS note',
 "SELECT 'it''s FROM wp_sa_auth_sessions' AS note",
 "SELECT 'it\\'s FROM wp_sa_auth_sessions' AS note",
 "SELECT 1 /* FROM wp_sa_auth_sessions */",
 "SELECT 1 -- FROM wp_sa_auth_sessions\n",
 "SELECT 1 # FROM wp_sa_auth_sessions\n",
 "SELECT \x60note FROM wp_sa_auth_sessions\x60 FROM wp_posts",
];
foreach($cases as $i=>$sql){need(SAUTH_Storage_Router::canonicalize_query($sql)===$sql,'foreign/literal/comment case '.($i+1));}
$sql="SELECT 'FROM wp_sa_auth_sessions' AS note FROM wp_sa_auth_sessions JOIN \x60wp_sa_auth_devices\x60 d ON 1=1";
$expected="SELECT 'FROM wp_sa_auth_sessions' AS note FROM \x60wp_sauth_auth_sessions\x60 JOIN \x60wp_sauth_auth_devices\x60 d ON 1=1";
need(SAUTH_Storage_Router::canonicalize_query($sql)===$expected,'mixed literal and canonical table positions');
SAUTH_Storage_Router::suspend();
need(SAUTH_Storage_Router::canonicalize_query($sql)===$sql,'migration suspension');
SAUTH_Storage_Router::resume();
echo "PASS 12 storage routing isolation assertions".PHP_EOL;
