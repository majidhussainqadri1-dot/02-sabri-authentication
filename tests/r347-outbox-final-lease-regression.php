<?php
/** NFR-003: verify expired final-attempt outbox recovery and claim generation.
 * Isolated SQL/DB-call contract mock; no real events or network.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
define('ARRAY_A','ARRAY_A');
define('HOUR_IN_SECONDS',3600);define('MINUTE_IN_SECONDS',60);
function current_time($type,$gmt){return '2026-11-26 00:00:00';}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return strtolower(preg_replace('/[^a-z0-9_-]/i','',(string)$v));}
final class SAUTH_Activator {public static function table($name){return 'ci_'.$name;}}
final class OutboxReviewDB {
 public $queries=[],$updates=[],$last_error='';
 function prepare($sql,...$args){return [$sql,$args];}
 function query($q){$this->queries[]=$q;return 1;}
 function get_results($q,$mode){return [];}
 function get_row($q,$mode){return ['id'=>10,'status'=>'dispatching','attempts'=>3,'payload_json'=>'malformed'];}
 function update($table,$values,$where,$format,$whereFormat){$this->updates[]=['values'=>$values,'where'=>$where];return 1;}
}
function must($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: ".$label.PHP_EOL);exit(1);}}
$GLOBALS['wpdb']=new OutboxReviewDB();
require_once __DIR__.'/../includes/class-sauth-event-outbox.php';
SAUTH_Event_Outbox::dispatch_due();
$queries=$GLOBALS['wpdb']->queries;
$terminal=array_values(array_filter($queries,static function($q){return strpos($q[0],"status='dead_letter'")!==false;}));
must(count($terminal)===1,'expired or already stranded final attempts have terminal recovery');
must(strpos($terminal[0][0],"attempts>=%d")!==false,'terminal recovery bounded to exhausted attempts');
must(strpos($terminal[0][0],"status='dispatching' AND updated_at<%s")!==false,'active lease not terminated prematurely');
must(strpos($terminal[0][0],"status IN ('pending','retry')")!==false,'already stranded rows reconciled');
$retry=array_values(array_filter($queries,static function($q){return strpos($q[0],"SET status='retry'")!==false;}));
must(count($retry)===1&&strpos($retry[0][0],"attempts<%d")!==false,'expired nonfinal attempts alone return to retry');
$dispatch=new ReflectionMethod('SAUTH_Event_Outbox','dispatch_row');$dispatch->setAccessible(true);
$dispatch->invoke(null,['id'=>10,'attempts'=>2,'payload_json'=>'malformed']);
$claim=end($GLOBALS['wpdb']->queries);
must(strpos($claim[0],'attempts=%d')!==false&&strpos($claim[0],'available_at<=%s')!==false,'claim binds observed attempt and due time');
$write=end($GLOBALS['wpdb']->updates);
must($write['where']['attempts']===3&&$write['where']['status']==='dispatching','retry transition fenced to its claim generation');
echo "PASS 7 outbox final lease and claim generation checks (isolated DB-call mock).".PHP_EOL;
