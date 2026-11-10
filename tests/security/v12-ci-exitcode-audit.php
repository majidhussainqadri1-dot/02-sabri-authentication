<?php
/**
 * F02-NFR-003/008 CI truth: status-printing PHP exit("FAIL") returns zero!
 * Scan the inherited and new v12 security/consumer source tests for that
 * false-green pattern. This is a static suite gate, not production acceptance.
 */
declare(strict_types=1);
$root=dirname(__DIR__,2);
$paths=[
  "tests/security/v12-registered-breach-null-failfirst.php",
  "tests/security/v12-recovery-date-failfirst.php",
  "tests/security/v12-collision-finalization-failfirst.php",
  "tests/security/v12-dpop-htu-jwk-failfirst.php",
  "tests/security/v12-timeline-private-evidence-failfirst.php",
  "tests/security/v12-timeline-read-failure-failfirst.php",
  "tests/security/breach-hibp-optin-no-network.php",
  "tests/security/breach-prefix-provider-failfirst.php",
  "tests/security/dpop-crypto-claim-failfirst.php",
  "tests/security/recovery-finalization-failfirst.php",
  "tests/security/v12-i18n-source.php",
  "tests/security/v12-extended-views-i18n.php",
  "tests/security/v12-48-release-register.php",
  "tests/security/v12-green-rtl-source.php",
  "tests/security/v12-timeline-local-time-source.php",
  "tests/security/fedcm-finish-guard-source.php",
  "tests/security/collision-owner-handoff-preservation-source.php",
  "tests/integration/file26-authentication-noindex-source.php",
  "tests/integration/file24-assurance-consumer-mock.php",
  "tests/integration/file25-visual-token-bridge-source.php"
];
$count=0;
foreach($paths as $path){
 $name=$root.'/'.$path;
 if(!is_file($name)){fwrite(STDERR,"FAIL: QA fixture missing ".$path."\n");exit(1);}
 $content=file_get_contents($name);
 if(!is_string($content) || ''===$content){fwrite(STDERR,"FAIL: QA fixture unreadable ".$path."\n");exit(1);}
 if(preg_match('/\\b(?:exit|die)\\s*\\(\\s*[\"\\\']/i',$content)){
  fwrite(STDERR,"FAIL: PHP exit(string) false-green detected in ".$path."\n");exit(1);
 }
 if(strpos($content,'exit(1)')===false && strpos($content,'exit(2)')===false){
  fwrite(STDERR,"FAIL: fixture has no nonzero failure exit ".$path."\n");exit(1);
 }
 $count++;
}
if($count!==20){fwrite(STDERR,"FAIL: expected 20 exact fixtures, found ".$count."\n");exit(1);}
echo "PASS: ".$count." File02 v12 focused source fixtures fail with nonzero status (static CI QA).\n";
