<?php
define('ABSPATH',__DIR__.'/');
function absint($v){return abs((int)$v);}
function untrailingslashit($v){return rtrim($v,'/');}
function wp_salt($v){return 'synthetic-test';}
class SA_Security {public static function client_fingerprint(){return 'test';}}
$GLOBALS['store']=[];$GLOBALS['fail']=false;
function get_transient($k){return $GLOBALS['store'][$k]??false;}
function set_transient($k,$v,$ttl){if($GLOBALS['fail'])return false;$GLOBALS['store'][$k]=$v;return true;}
require __DIR__.'/../includes/class-sauth-completion-resolver.php';
$m=new ReflectionMethod('SAUTH_Completion_Resolver','record_visit');$m->setAccessible(true);
function need($v,$msg){if(!$v){fwrite(STDERR,"FAIL ".$msg.PHP_EOL);exit(1);}}
$GLOBALS['fail']=true;
need($m->invoke(null,17,'https://example.test/complete',['email'])['blocked']===true,'failed loop persistence blocks redirect');
$GLOBALS['fail']=false;
need(!$m->invoke(null,17,'https://example.test/complete',['email'])['blocked'],'first verified visit allowed');
need(!$m->invoke(null,17,'https://example.test/complete',['email'])['blocked'],'second verified visit allowed');
need($m->invoke(null,17,'https://example.test/complete',['email'])['blocked'],'third verified visit blocked');
echo "PASS 4 completion loop persistence checks".PHP_EOL;
