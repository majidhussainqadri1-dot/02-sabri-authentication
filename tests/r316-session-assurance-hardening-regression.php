<?php
$root=dirname(__DIR__); $s=file_get_contents($root.'/includes/class-sauth-session-manager.php'); $a=file_get_contents($root.'/includes/class-sa-authentication-assurance.php'); $p=file_get_contents($root.'/includes/class-sa-professional-reauthentication.php'); $ac=file_get_contents($root.'/includes/class-sa-access-control.php'); $sm=file_get_contents($root.'/includes/class-sauth-safe-mode-challenge-gate.php'); $fail=array();
$checks=array(
 array($a,"SAUTH_Passkey_Runtime', 'current_assurance",'CF-01 assurance still consumes legacy non-epoch-aware passkey projection'),
 array($a,'SAUTH_Passkey_Runtime::current_assurance','CF-01 assurance bypasses hardened passkey runtime'),
 array($s,'null === $remaining_raw','session revoke-all can accept a failed postcondition read as zero'),
 array($s,"session_registry_read_failed",'session authentication projection does not fail closed on registry DB error'),
 array($p,'password_binding','professional reauthentication is not password-state bound'),
 array($ac,'block_unmanaged_password_authentication','unmanaged password authentication remains exposed'),
 array($sm,'challenge_invalidated_by_safe_mode','pre-containment passkey challenges can survive Safe Mode')
);
foreach($checks as $c){if(false===strpos($c[0],$c[1]))$fail[]=$c[2];}
if(false!==strpos($a,'SAUTH_Passkeys::file00_assurance'))$fail[]='active CF-01 assurance still calls legacy passkey assurance directly';
if($fail){fwrite(STDERR,"R316 regressions:\n- ".implode("\n- ",$fail)."\n");exit(1);} echo 'R316 session/assurance hardening regression PASS ('.(count($checks)+1)." assertions).\n";
