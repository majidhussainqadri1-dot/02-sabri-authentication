<?php
/**
 * File02 NFR-006/008: source-level operator readiness is never confused with
 * real provider acceptance. Missing hooks are a visible release blocker,
 * but must not spuriously activate Safe Mode or mutate user/session data.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
$GLOBALS['registeredHooks']=[];
function has_filter($hook){return !empty($GLOBALS['registeredHooks'][$hook])?10:false;}
require __DIR__.'/../../includes/class-sauth-operations.php';
function prove($ok,$why){if(!$ok){fwrite(STDERR,"RED: ".$why."\n");exit(1);}}
$gates=SAUTH_Operations::external_release_gate_summary();
prove(is_array($gates)&&count($gates)>=7,'bounded external gate summary missing');
foreach($gates as $gate=>$state){
 prove(is_string($gate)&&preg_match('/^[a-z0-9_]+$/D',$gate)===1,'unbounded gate identity');
 prove(is_array($state) && isset($state['status'],$state['acceptance']),'missing structured proof state');
 prove($state['status']==='missing'&&$state['acceptance']==='not_verified','missing provider incorrectly release-accepted');
}
$GLOBALS['registeredHooks']['sauth_dpop_verify_signature_v1']=true;
$gates=SAUTH_Operations::external_release_gate_summary();
prove(($gates['dpop_signature']['status']??'')==='registered_unverified','registered DPoP adapter misreported');
prove(($gates['dpop_signature']['acceptance']??'')==='not_verified','presence of hook falsely proved signature verification');
echo "PASS operator exposes provider state without false acceptance or live claims (read-only mock).\n";
