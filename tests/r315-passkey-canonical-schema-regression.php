<?php
$root=dirname(__DIR__); $p=file_get_contents($root.'/includes/class-sauth-passkeys.php'); $r=file_get_contents($root.'/includes/class-sauth-passkey-runtime.php'); $t=file_get_contents($root.'/tests/passkey-webauthn-unit.php'); $fail=array();
$checks=array(
 array($p,'prepare_legacy_credential_columns','legacy passkey credentials have no canonical-column migration'),
 array($p,'credential_lookup_hash','historical passkey surface is not using canonical lookup column'),
 array($p,'credential_id_ciphertext','historical passkey surface is not using canonical ciphertext column'),
 array($p,'$schema_ready && self::table_schema_ready()','passkey environment readiness still proves table name only'),
 array($r,'credential_lookup_hash','hardened passkey runtime is not using canonical lookup column'),
 array($r,'credential_id_ciphertext','hardened passkey runtime is not using canonical ciphertext column'),
 array($r,"credential_store_unavailable",'passkey DB read uncertainty does not fail closed'),
 array($r,"credential_quarantine_failed",'counter regression quarantine has no containment failure path'),
 array($r,"SAUTH_Operations::safe_mode()",'issued passkey ceremony can finish after Safe Mode is raised'),
 array($t,'str_pad( (string) $details[\'ec\'][\'x\'], 32','WebAuthn EC fixture remains random-width/flaky')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
foreach(array($p,$r) as $src){if(false!==strpos($src,'WHERE credential_hash=%s')||false!==strpos($src,"'credential_hash' =>")||false!==strpos($src,"'credential_cipher' =>"))$fail[]='legacy physical passkey column name remains in active runtime';}
if($fail){fwrite(STDERR,"R315 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R315 passkey canonical-schema regression PASS ('.(count($checks)+2)." assertions).\n";
