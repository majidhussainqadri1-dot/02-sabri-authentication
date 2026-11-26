<?php
/** F02-X24-020: replay retention must cover the entire admitted time window.
 * Read-only source/mock-clock regression; no real proof, credentials or network.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
$GLOBALS['stored']=[];$GLOBALS['scheduled']=[];
function wp_next_scheduled($hook,$args){return $GLOBALS['scheduled'][$args[0]]??false;}
function wp_schedule_single_event($when,$hook,$args){$GLOBALS['scheduled'][$args[0]]=$when;return true;}
function get_option($k,$fallback=false){return $GLOBALS['stored'][$k]??$fallback;}
function delete_option($k){unset($GLOBALS['stored'][$k]);return true;}
require_once __DIR__.'/../includes/class-sauth-dpop.php';
function need($v,$label){if(!$v){fwrite(STDERR,"FAIL: ".$label.PHP_EOL);exit(1);}}
$key='sauth_dpop_'.str_repeat('a',64);
$max=SAUTH_DPoP::MAX_AGE;
$fullWindow=2*$max;
// The symmetric accepted timestamp window requires a conservative retention
// horizon. Cleanup of an existing synthetic marker must not precede that horizon.
$GLOBALS['stored'][$key]=time()-($max+61);
SAUTH_DPoP::expire_replay($key);
need(isset($GLOBALS['stored'][$key]),'replay marker retained throughout full permitted time window');
need(($GLOBALS['scheduled'][$key]??0)===$GLOBALS['stored'][$key]+SAUTH_DPoP::REPLAY_RETENTION,'early legacy cleanup requeued at full retention horizon');
$GLOBALS['stored'][$key]=time()-($fullWindow+61);
SAUTH_DPoP::expire_replay($key);
need(!isset($GLOBALS['stored'][$key]),'old marker expires after complete window and margin');
$other='unrelated_option';
$GLOBALS['stored'][$other]=time()-86400;
SAUTH_DPoP::expire_replay($other);
need(isset($GLOBALS['stored'][$other]),'unrelated option remains untouched');
echo "PASS 4 DPoP retention bounds and cleanup isolation assertions".PHP_EOL;
