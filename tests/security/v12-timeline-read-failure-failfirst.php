<?php
/** F02-NFR-008: failed timeline DB read cannot be disguised as no events. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
class WP_Error{private $c;function __construct($c,$m=''){$this->c=$c;}function get_error_code(){return $this->c;}}
class SAUTH_Activator{static function table($key){return 'mock_'.$key;}}
class Db {public $last_error='unavailable';function prepare($sql,...$args){return $sql;}function get_results($sql,$mode){return null;}}
$wpdb=new Db();
function absint($a){return abs((int)$a);}
require __DIR__.'/../../includes/class-sauth-security-orchestrator.php';
$r=SAUTH_Security_Orchestrator::timeline(17,30);
if(!($r instanceof WP_Error)||$r->get_error_code()!=='sauth_timeline_unavailable')fwrite(STDERR,"RED: timeline storage error rendered as empty security activity\n"); exit(1);
echo "PASS DB timeline error is explicit, not false empty activity (mock).\n";
