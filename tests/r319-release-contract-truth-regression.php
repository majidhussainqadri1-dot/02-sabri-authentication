<?php
$root=dirname(__DIR__); $adapter=file_get_contents($root.'/includes/class-sa-membership-adapter.php'); $lock=file_get_contents($root.'/RELEASE-LOCK.json'); $status=file_get_contents($root.'/STATUS.md'); $readme=file_get_contents($root.'/README.md'); $manifest=file_get_contents($root.'/RELEASE-MANIFEST.md'); $migration=file_get_contents($root.'/MIGRATION.md'); $dict=file_get_contents($root.'/DATA-DICTIONARY.md'); $contracts=file_get_contents($root.'/CONTRACTS.md'); $fail=array();
$checks=array(
 array($adapter,'SAUTH_Passkey_Runtime::current_assurance','membership compatibility helper bypasses hardened passkey assurance'),
 array($adapter,"add_query_arg( 'redirect_to', SA_Security::safe_redirect( \$redirect )",'membership login URL still pre-encodes redirect destination'),
 array($lock,'review/file02-r321-r330-2026-08-14','release lock names stale review line'),
 array($lock,'cross_file_blockers','release lock hides cross-file account-taxonomy blocker'),
 array($status,'R321–R329','status document names stale review line'),
 array($readme,'R321–R329 corrective review','README names stale review line'),
 array($manifest,'review/file02-r321-r330-2026-08-14','release manifest names stale review line'),
 array($migration,'mandatory activation/guarded-repair postconditions','migration guide still claims auth can remain available after required passkey migration failure'),
 array($migration,'credential_lookup_hash','migration guide omits canonical passkey-column reconciliation'),
 array($dict,'credential_id_ciphertext','data dictionary omits canonical passkey-column reconciliation'),
 array($contracts,'SAUTH_Passkey_Runtime::current_assurance','contract register does not distinguish hardened current assurance runtime')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if(false!==strpos($adapter,'rawurlencode( SA_Security::safe_redirect( $redirect )'))$fail[]='membership login redirect remains pre-encoded';
if(false!==strpos($adapter,'SAUTH_Passkeys::file00_assurance'))$fail[]='membership compatibility helper still calls legacy assurance directly';
if(false!==strpos($migration,'password/Google authentication can remain available'))$fail[]='migration guide retains fail-open passkey-failure claim';
if($fail){fwrite(STDERR,"R319 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R319 release/contract truth regression PASS ('.(count($checks)+3)." assertions).\n";
