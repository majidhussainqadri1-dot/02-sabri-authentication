<?php
$root = dirname( __DIR__ );
$baseline = file_get_contents( $root . '/.github/workflows/baseline-integrity.yml' );
$docs = file_get_contents( $root . '/.github/workflows/canonical-storage-and-docs.yml' );
$integration = file_get_contents( $root . '/.github/workflows/file00-1.2.43-real-integration.yml' );
$incident = file_get_contents( $root . '/INCIDENT.md' );
$architecture = file_get_contents( $root . '/ARCHITECTURE.md' );
$contracts = file_get_contents( $root . '/CONTRACTS.md' );
$staging = file_get_contents( $root . '/STAGING-ACCEPTANCE.md' );
$sbom = file_get_contents( $root . '/SBOM.spdx.json' );
$fail = array();
$checks = array(
  array($baseline, "'intended_canonical_repository': '02-sabri-authentication-and-accounts'", 'release CI expects obsolete lock schema'),
  array($baseline, 'tests/r308-route-ui-regression.php', 'release CI omits latest permanent regressions'),
  array($docs, 'SAUTH_Passkeys::maybe_install( true )', 'storage/docs gate asserts obsolete repair call'),
  array($integration, 'c4ab298b3ba2b870d507d32b36b1b4afd2771621', 'integration gate is not pinned to R309 File00 main truth'),
  array($incident, 'live symptom → live evidence → exact deployed version → DB/schema state → deployment parity → root cause → repository code', 'incident runbook lacks Live-First order'),
  array($incident, 'Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status', 'incident report lacks mandatory truth fields'),
  array($architecture, 'dedicated-`SA_MASTER_KEY`', 'architecture omits dedicated provider-secret key authority'),
  array($contracts, 'retired File 00 factor codes', 'contracts still imply File00 factor ceremony'),
  array($staging, 'All eight File 02 tables/indexes', 'staging checklist still counts pre-passkey tables'),
  array($sbom, 'live-deployment claim', 'SBOM no longer states an explicit live-deployment evidence boundary'),
);
foreach ($checks as $c) { if (false === strpos($c[0], $c[1])) $fail[] = $c[2]; }
if ($fail) { fwrite(STDERR, "R309 regressions:\n- ".implode("\n- ",$fail)."\n"); exit(1); }
echo 'R309 release truth regression PASS ('.count($checks)." assertions).\n";
