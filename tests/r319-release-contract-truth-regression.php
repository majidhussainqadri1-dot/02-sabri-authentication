<?php
$root=dirname(__DIR__); $adapter=file_get_contents($root.'/includes/class-sa-membership-adapter.php'); $lock=file_get_contents($root.'/RELEASE-LOCK.json'); $status=file_get_contents($root.'/STATUS.md'); $readme=file_get_contents($root.'/README.md'); $manifest=file_get_contents($root.'/RELEASE-MANIFEST.md'); $migration=file_get_contents($root.'/MIGRATION.md'); $dict=file_get_contents($root.'/DATA-DICTIONARY.md'); $contracts=file_get_contents($root.'/CONTRACTS.md'); $fail=array();
$checks=array(
 array($adapter,'SAUTH_Passkey_Runtime::current_assurance','membership compatibility helper bypasses hardened passkey assurance'),
 array($adapter,"add_query_arg( 'redirect_to', SA_Security::safe_redirect( \$redirect )",'membership login URL still pre-encodes redirect destination'),
 array($adapter,"MEMBERSHIP_APPLICATION_KEY  = 'application'",'File 02 does not consume the canonical File 00 application page key'),
 array($adapter,"MEMBERSHIP_SECURITY_KEY     = 'security'",'File 02 does not consume the canonical File 00 security page key'),
 array($adapter,"MEMBERSHIP_STATUS_KEY       = 'status'",'File 02 does not consume the canonical File 00 status page key'),
 array($lock,'fix/file02-legacy-email-verification-reconciliation-1.3.4','release lock does not name the current R341 legacy-email reconciliation branch'),
 array($lock,'"release_version": "1.3.4"','release lock does not identify the current 1.3.4 runtime'),
 array($lock,'"database_version": "1.3.0"','release lock changed the canonical DB identity unexpectedly'),
 array($lock,'cross_file_integration_evidence','release lock omits exact cross-file integration evidence'),
 array($lock,'31850253635','release lock omits proven historical cross-file integration run'),
 array($lock,'live_1_3_0_activation_incident','release lock omits the live 1.3.0 activation incident evidence'),
 array($lock,'live_1_3_1_membership_route_contract_incident','release lock omits the live 1.3.1 membership-route incident evidence'),
 array($lock,'live_1_3_2_passkey_assurance_cycle_incident','release lock omits the live 1.3.2 passkey-assurance cycle incident evidence'),
 array($lock,'live_1_3_3_legacy_email_verification_reconciliation_incident','release lock omits the live-proven 1.3.3 legacy email reconciliation incident evidence'),
 array($lock,'"review_line": "R341-email-verification-legacy-reconciliation"','release lock does not bind the current correction to R341'),
 array($status,'R337 comprehensive remediation','status document no longer preserves the R337 base line'),
 array($readme,'1.3.0','README no longer preserves the R337 base identity/history'),
 array($manifest,'Historical run `31850253635`','release manifest does not separate prior integration evidence'),
 array($migration,'mandatory activation/guarded-repair postconditions','migration guide still claims auth can remain available after required passkey migration failure'),
 array($migration,'credential_lookup_hash','migration guide omits canonical passkey-column reconciliation'),
 array($dict,'credential_id_ciphertext','data dictionary omits canonical passkey-column reconciliation'),
 array($contracts,'SAUTH_Passkey_Runtime::current_assurance','contract register does not distinguish hardened current assurance runtime')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if(false!==strpos($adapter,'rawurlencode( SA_Security::safe_redirect( $redirect )'))$fail[]='membership login redirect remains pre-encoded';
if(false!==strpos($adapter,'SAUTH_Passkeys::file00_assurance'))$fail[]='membership compatibility helper still calls legacy assurance directly';
foreach(array('sabri_profile','sabri_security_center','sabri_verification_status','/sabri-profile/','/sabri-security-center/','/sabri-verification-status/') as $forbidden){if(false!==strpos($adapter,$forbidden))$fail[]='invented File 00 membership route remains: '.$forbidden;}
if(false!==strpos($migration,'password/Google authentication can remain available'))$fail[]='migration guide retains fail-open passkey-failure claim';
if($fail){fwrite(STDERR,"R319 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R319 current release/contract truth invariants PASS ('.(count($checks)+9)." assertions).\n";
