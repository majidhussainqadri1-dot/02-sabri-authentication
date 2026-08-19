<?php
$root = dirname( __DIR__ );
$baseline = file_get_contents( $root . '/.github/workflows/baseline-integrity.yml' );
$docs = file_get_contents( $root . '/.github/workflows/canonical-storage-and-docs.yml' );
$integration = file_get_contents( $root . '/.github/workflows/file00-1.2.44-real-integration.yml' );
$incident = file_get_contents( $root . '/INCIDENT.md' );
$architecture = file_get_contents( $root . '/ARCHITECTURE.md' );
$contracts = file_get_contents( $root . '/CONTRACTS.md' );
$staging = file_get_contents( $root . '/STAGING-ACCEPTANCE.md' );
$sbom = file_get_contents( $root . '/SBOM.spdx.json' );
$adapter = file_get_contents( $root . '/includes/class-sa-membership-adapter.php' );
$fail = array();
$checks = array(
  array($baseline, "lock.get('release_version')=='1.3.3'", 'release CI does not enforce current release-lock runtime identity'),
  array($baseline, 'tests/r33*-regression.php', 'release CI omits prior corrective regressions'),
  array($baseline, 'tests/r34*-regression.php', 'release CI omits current R340 corrective regression family'),
  array($baseline, 'tests/r339-file00-canonical-route-contract-regression.php', 'release CI does not require the R339 route-contract regression'),
  array($baseline, 'tests/r340-passkey-assurance-cycle-regression.php', 'release CI does not require the R340 passkey-assurance cycle regression'),
  array($docs, 'table_indexes_ready', 'storage/docs gate does not assert material index readiness'),
  array($docs, 'tests/r33*-regression.php', 'storage/docs gate omits prior corrective regressions'),
  array($integration, '1d7f215193d778b0977c8e50d738c42e1e5f66c2', 'integration gate is not pinned to exact corrected File00 1.2.44 candidate'),
  array($integration, "FILE00_VERSION: '1.2.44'", 'integration gate does not assert File00 1.2.44 runtime'),
  array($integration, 'c4ab298b3ba2b870d507d32b36b1b4afd2771621', 'integration gate does not pin the exact File00 1.2.43 route baseline'),
  array($integration, 'Prove File 02 resolves exact File 00 canonical membership routes', 'integration gate lacks canonical File00 membership-route proof'),
  array($integration, 'Prove canonical account taxonomy parity on both runtimes', 'integration gate lacks two-sided canonical taxonomy proof'),
  array($integration, 'Rehearse legacy 1.2.1 passkey-column upgrade on real MariaDB', 'integration gate lacks supported passkey upgrade rehearsal'),
  array($integration, 'Rehearse exact deployed stale passkey user_status index on real MariaDB', 'integration gate lacks the exact live stale-index recovery rehearsal'),
  array($adapter, "MEMBERSHIP_APPLICATION_KEY  = 'application'", 'adapter lacks canonical File00 application key'),
  array($adapter, "MEMBERSHIP_SECURITY_KEY     = 'security'", 'adapter lacks canonical File00 security key'),
  array($adapter, "MEMBERSHIP_STATUS_KEY       = 'status'", 'adapter lacks canonical File00 status key'),
  array($incident, 'live symptom → live evidence → exact deployed version → DB/schema state → deployment parity → root cause → repository code', 'incident runbook lacks Live-First order'),
  array($incident, 'Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status', 'incident report lacks mandatory truth fields'),
  array($architecture, 'dedicated-`SA_MASTER_KEY`', 'architecture omits dedicated provider-secret key authority'),
  array($contracts, 'retired File 00 factor codes', 'contracts still imply File00 factor ceremony'),
  array($staging, 'All eight File 02 tables/indexes', 'staging checklist still counts pre-passkey tables'),
  array($sbom, 'live-deployment claim', 'SBOM no longer states an explicit live-deployment evidence boundary'),
);
foreach ($checks as $c) { if (false === strpos($c[0], $c[1])) $fail[] = $c[2]; }
foreach (array('sabri_profile','sabri_security_center','sabri_verification_status') as $forbidden) { if (false !== strpos($adapter,$forbidden)) $fail[] = 'invented File00 membership route remains: '.$forbidden; }
if ($fail) { fwrite(STDERR, "R309 regressions:\n- ".implode("\n- ",$fail)."\n"); exit(1); }
echo 'R309 current release-truth invariants PASS ('.(count($checks)+3)." assertions).\n";
