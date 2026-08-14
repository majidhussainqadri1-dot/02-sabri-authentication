<?php
$root=dirname(__DIR__); $p=file_get_contents($root.'/includes/class-sa-privacy.php'); $o=file_get_contents($root.'/includes/class-sauth-operations.php'); $u=file_get_contents($root.'/uninstall.php'); $fail=array();
$checks=array(
 array($p,'return array( \'data\' => $data, \'done\' => false )','privacy DB export failures can still be reported complete'),
 array($p,'privacy_passkey_count_failed','passkey erasure precondition DB failure can collapse to zero'),
 array($p,'null === $passkey_remaining','passkey erasure postcondition DB failure can collapse to zero'),
 array($p,'privacy_table_count_failed','table erasure precondition DB failure can collapse to zero'),
 array($p,'null === $remaining_raw','destructive erasure postconditions do not distinguish DB uncertainty'),
 array($p,'privacy_outbox_read_failed','outbox anonymization can silently skip a failed read'),
 array($p,'privacy_table_probe_failed','table probe DB failure can be mistaken for an absent table'),
 array($o,'Material File 02 storage postconditions','system check can be green on marker/table-name evidence only'),
 array($o,'Authentication event outbox dispatch schedule','system check ignores a missing event-dispatch scheduler'),
 array($u,'Intentionally no destructive action','uninstall silently destroys retained authentication evidence')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if($fail){fwrite(STDERR,"R317 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R317 privacy integrity regression PASS ('.count($checks)." assertions).\n";
