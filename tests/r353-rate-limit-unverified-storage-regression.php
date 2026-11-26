<?php
/** F02-NFR-001/003/007: unverified rate counter must not permit authentication. */
define('ABSPATH',__DIR__.'/');define('DAY_IN_SECONDS',86400);
function absint($v){return abs((int)$v);}function sanitize_key($v){return strtolower((string)$v);}
function sanitize_text_field($v){return (string)$v;}function wp_unslash($v){return $v;}function wp_salt($v){return 'synthetic';}
function wp_rand($a,$b){return 2;}
function get_transient($k){++$GLOBALS['fallback_reads'];return false;}
function set_transient($k,$v,$t){++$GLOBALS['fallback_writes'];return true;}
class SAUTH_Activator{public static function table($n){return 'wp_sauth_'.$n;}}
class FakeRateDB{
 public $last_error='',$write=true,$count='1',$write_error='',$read_error='',$reads=0;
 function prepare($sql,...$args){return $sql;}
 function query($sql){$this->last_error=$this->write_error;return $this->write;}
 function get_var($sql){++$this->reads;$this->last_error=$this->read_error;return $this->count;}
}
function need($ok,$label){if(!$ok){fwrite(STDERR,"FAIL: ".$label.PHP_EOL);exit(1);}}
require __DIR__.'/../includes/class-sa-security.php';
$wpdb=new FakeRateDB();$GLOBALS['fallback_reads']=0;$GLOBALS['fallback_writes']=0;
$wpdb->write=false;
need(SA_Security::rate_limited('test',6,900,'synthetic'),'failed atomic write denied');
need($wpdb->reads===0&&$GLOBALS['fallback_reads']===0&&$GLOBALS['fallback_writes']===0,'no non-atomic fallback used');
$wpdb->write=true;$wpdb->write_error='write error';
need(SA_Security::rate_limited('test',6,900,'synthetic'),'unverified write denied');
$wpdb->write_error='';
foreach([null,'unknown',0,'0',-1,true,1.5] as $bad){
 $wpdb->count=$bad;
 need(SA_Security::rate_limited('test',6,900,'synthetic'),'invalid count denied');
}
$wpdb->count='1';$wpdb->read_error='read error';
need(SA_Security::rate_limited('test',6,900,'synthetic'),'read error denied');
$wpdb->read_error='';$wpdb->count='6';
need(!SA_Security::rate_limited('test',6,900,'synthetic'),'verified boundary count allowed');
$wpdb->count='7';
need(SA_Security::rate_limited('test',6,900,'synthetic'),'verified excessive count denied');
echo "PASS 13 rate-limit atomic storage boundary assertions (mock)".PHP_EOL;
