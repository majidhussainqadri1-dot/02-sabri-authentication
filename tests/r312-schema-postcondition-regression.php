<?php
$root=dirname(__DIR__); $a=file_get_contents($root.'/includes/class-sa-activator.php'); $p=file_get_contents($root.'/includes/class-sauth-passkeys.php'); $fail=array();
$checks=array(
 array($a,'required_table_columns','base table schemas have no material-column postcondition'),
 array($a,'SHOW COLUMNS FROM','base storage readiness still proves names only'),
 array($a,"sauth_legacy_table_migration_version', ''","legacy migration marker write is not verified"),
 array($p,'table_schema_ready','passkey schema marker still proves table name only'),
 array($p,'credential_id_ciphertext','passkey schema does not require encrypted credential id column'),
 array($p,'hardware_backed','passkey schema does not require modern security metadata columns')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if($fail){fwrite(STDERR,"R312 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R312 schema postcondition regression PASS ('.count($checks)." assertions).\n";
