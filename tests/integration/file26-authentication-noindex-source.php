<?php
/**
 * F02-CEN-01: exact pinned File26 source and File02 private/no-index decision.
 * Source/contract test only; a deployed search index still needs live audit.
 */
declare(strict_types=1);
$root=dirname(__DIR__,2);
$owners=file_get_contents($root.'/../file26/includes/class-file26-owner-contracts.php');
$index=file_get_contents($root.'/../file26/includes/class-file26-indexer.php');
$access=file_get_contents($root.'/includes/class-sa-access-control.php');
$routes=file_get_contents($root.'/includes/class-sauth-canonical-routes.php');
if(!$owners||!$index||!$access||!$routes)exit("FAIL missing exact pinned source\n");
function audit_check($ok,$why){if(!$ok){fwrite(STDERR,'FAIL: '.$why."\n");exit(1);}}
audit_check(strpos($owners,"'file02' =>")===false,'File26 unexpectedly requires File02 private credential connector');
foreach(["'file03'","'file21'"] as $public_owner) audit_check(strpos($owners,$public_owner)!==false,'File26 owner contract unexpectedly missing public owner '.$public_owner);
foreach(["'noindex'","'nofollow'","'noarchive'","'Cache-Control: no-store","Referrer-Policy: no-referrer"] as $rule)audit_check(strpos($access,$rule)!==false,'File02 private header policy missing '.$rule);
foreach(["'index'=>'noindex'","'cache'=>'no-store'","X-Robots-Tag: noindex"] as $rule)audit_check(strpos($routes,$rule)!==false,'File02 canonical noindex route missing '.$rule);
audit_check(strpos($index,"'private'")!==false&&strpos($index,"'restricted'")!==false,'File26 restricted/private index denial absent');
echo "PASS exact File26 no File02 private connector + File02 no-index/no-store + File26 tombstone controls (source only).\n";
