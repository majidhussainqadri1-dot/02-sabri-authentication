<?php
$root=dirname(__DIR__); $adapter=file_get_contents($root.'/includes/class-sa-membership-adapter.php'); $lock=file_get_contents($root.'/RELEASE-LOCK.json'); $status=file_get_contents($root.'/STATUS.md'); $readme=file_get_contents($root.'/README.md'); $manifest=file_get_contents($root.'/RELEASE-MANIFEST.md'); $migration=file_get_contents($root.'/MIGRATION.md'); $dict=file_get_contents($root.'/DATA-DICTIONARY.md'); $contracts=file_get_contents($root.'/CONTRACTS.md'); $fail=array();
$checks=array(
 array($adapter,'SAUTH_Passkey_Runtime::current_assurance','membership compatibility helper bypasses hardened passkey assurance'),
 array($adapter,"add_query_arg( 'redirect_to', SA_Security::safe_redirect( \$redirect )",'membership login URL still pre-encodes redirect destination'),
 array($lock,'review/file02-r337-fresh-audit-2026-08-16','release lock does not name current R337 review branch'),
 array($lock,'cross_file_integration_evidence','release lock omits exact cross-file integration evidence'),
 array($lock,'31850253635','release lock omits historical proven cross-file integration run'),
 array($lock,'current_r337_revalidation_required','release lock does not require post-R337 paired revalidation'),
 array($status,'R331–R337','status document does not name current corrective line'),
 array($status,'historical pre-R337','status does not scope older paired integration evidence honestly'),
 array($readme,'1.2.6','README current runtime identity stale'),
 array($manifest,'review/file02-r337-fresh-audit-2026-08-16','release manifest does not name current R337 review branch'),
 array($manifest,'31850253635','release manifest omits historical proven cross-file integration run'),
 array($manifest,'post-R337','release manifest does not require current post-R337 evidence'),
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
