<?php
$root=dirname(__DIR__); $adapter=file_get_contents($root.'/includes/class-sa-membership-adapter.php'); $lock=file_get_contents($root.'/RELEASE-LOCK.json'); $status=file_get_contents($root.'/STATUS.md'); $readme=file_get_contents($root.'/README.md'); $manifest=file_get_contents($root.'/RELEASE-MANIFEST.md'); $migration=file_get_contents($root.'/MIGRATION.md'); $dict=file_get_contents($root.'/DATA-DICTIONARY.md'); $contracts=file_get_contents($root.'/CONTRACTS.md'); $lineage=file_get_contents($root.'/SOURCE-LINEAGE-LOCK.json'); $fail=array();
$checks=array(
 array($adapter,'SAUTH_Passkey_Runtime::current_assurance','membership compatibility helper bypasses hardened passkey assurance'),
 array($adapter,"add_query_arg( 'redirect_to', SA_Security::safe_redirect( \$redirect )",'membership login URL still pre-encodes redirect destination'),
 array($lock,'review/file02-r338-fresh-review-fix-2026-08-16','release lock names stale current branch'),
 array($lock,'blocked_unrecovered_approved_1.3.8_x24','release lock omits current source-lineage blocker'),
 array($lock,'31850253635','release lock omits proven historical cross-file integration run'),
 array($status,'R338 authoritative source-lineage status','status document names stale corrective line'),
 array($status,'current recoverable File 02 `1.3.0` / File 00 `1.2.44` paired integration is not yet exact-head proven','status does not preserve the current exact-head paired-integration boundary'),
 array($status,'Packaged | **BLOCKED**','status does not block packaging for unrecovered source lineage'),
 array($readme,'1.3.0','README current recoverable runtime identity stale'),
 array($manifest,'review/file02-r338-fresh-review-fix-2026-08-16','source-review manifest names stale current branch'),
 array($manifest,'Historical run `31850253635`','source-review manifest does not separate prior integration evidence'),
 array($manifest,'Installable package: **BLOCKED','source-review manifest does not block installable packaging'),
 array($lineage,'"packaging_allowed": false','source-lineage lock does not block packaging'),
 array($migration,'mandatory activation/guarded-repair postconditions','migration guide still claims auth can remain available after required passkey migration failure'),
 array($migration,'credential_lookup_hash','migration guide omits canonical passkey-column reconciliation'),
 array($dict,'credential_id_ciphertext','data dictionary omits canonical passkey-column reconciliation'),
 array($contracts,'SAUTH_Passkey_Runtime::current_assurance','contract register does not distinguish hardened current assurance runtime')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if(false!==strpos($adapter,'rawurlencode( SA_Security::safe_redirect( $redirect )'))$fail[]='membership login redirect remains pre-encoded';
if(false!==strpos($adapter,'SAUTH_Passkeys::file00_assurance'))$fail[]='membership compatibility helper still calls legacy assurance directly';
if(false!==strpos($migration,'password/Google authentication can remain available'))$fail[]='migration guide retains fail-open passkey-failure claim';
if($fail){fwrite(STDERR,"R319 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R319 current release/contract truth invariants PASS ('.(count($checks)+3)." assertions).\n";
