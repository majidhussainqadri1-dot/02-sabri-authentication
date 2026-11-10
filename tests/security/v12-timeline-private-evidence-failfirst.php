<?php
/**
 * F02-X24-007/NFR-002: timeline details cannot echo PII or bearer content.
 * Isolated WP mock: no real accounts or databases.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
class SAUTH_Activator {static function table($name){return 'mock_'.$name;}}
class SA_Security {static function client_fingerprint(){return 'hashed-mock-device';}}
class MockDB {
 public $last_error='',$lastInsert=null;
 function insert($table,$data,$format){$this->lastInsert=$data;return 1;}
}
$wpdb=new MockDB();
function absint($v){return abs((int)$v);}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\\-]/','',strtolower((string)$v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function wp_generate_uuid4(){return 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';}
function wp_json_encode($v){return json_encode($v);}
function current_time($arg,$gmt=true){return gmdate('Y-m-d H:i:s');}
require __DIR__.'/../../includes/class-sauth-security-orchestrator.php';
$id=SAUTH_Security_Orchestrator::timeline_event(17,'account_security','high',[
 'method'=>'webauthn_passkey',
 'reason'=>'person@example.test',
 'source_event'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
 'kind'=>'Bearer_test_secret',
 'ip_label'=>'127.0.0.1',
 'unused_private'=>'RANDOM_SECRET',
 'risk_score'=>40
]);
if(!is_string($id)||!is_array($wpdb->lastInsert))exit("FAIL timeline event not stored\n");
$safe=json_decode($wpdb->lastInsert['details_json'],true);
if(!is_array($safe)||($safe['method']??'')!=='webauthn_passkey'||($safe['risk_score']??'')!==40||($safe['source_event']??'')!=='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')exit("FAIL safe diagnostic values unexpectedly missing\n");
$full=json_encode($safe);
foreach(['person@example.test','Bearer_test_secret','127.0.0.1','RANDOM_SECRET'] as $secret)if(strpos($full,$secret)!==false)exit("RED: timeline stored raw PII or unapproved detail value\n");
echo "PASS timeline bounded code-only diagnostics, no PII/bearers (mock).\n";
