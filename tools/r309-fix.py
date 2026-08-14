#!/usr/bin/env python3
from pathlib import Path
import json
ROOT = Path(__file__).resolve().parents[1]

def rep(path, old, new, count=1):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    n = text.count(old)
    if n != count:
        raise SystemExit(f'{path}: expected {count}, found {n}: {old[:180]!r}')
    p.write_text(text.replace(old, new, count), encoding='utf-8')

def rep_all(path, old, new, minimum=1):
    p = ROOT / path
    text = p.read_text(encoding='utf-8')
    n = text.count(old)
    if n < minimum:
        raise SystemExit(f'{path}: expected at least {minimum}, found {n}: {old[:180]!r}')
    p.write_text(text.replace(old, new), encoding='utf-8')

# -------------------------------------------------------------------------
# R309-A: release CI must understand the current lock schema and execute every
# permanent review regression before producing a package candidate.
# -------------------------------------------------------------------------
rep(
    '.github/workflows/baseline-integrity.yml',
    "              'canonical_repository': '02-sabri-authentication-and-accounts',\n              'package_folder': '02-sabri-authentication',",
    "              'intended_canonical_repository': '02-sabri-authentication-and-accounts',\n              'transport_repository': '02-sabri-authentication',\n              'package_folder': '02-sabri-authentication',"
)
rep(
    '.github/workflows/baseline-integrity.yml',
    "              'tests/architecture-check.py', 'tests/security-unit.php',\n",
    "              'tests/architecture-check.py', 'tests/security-unit.php',\n              'tests/r291-r300-review-regression.php',\n              'tests/r299-cross-flow-regression.php',\n              'tests/r302-migration-postcondition-regression.php',\n              'tests/r303-registration-recovery-regression.php',\n              'tests/r304-google-auth-regression.php',\n              'tests/r305-passkey-lifecycle-regression.php',\n              'tests/r306-session-assurance-regression.php',\n              'tests/r307-privacy-operations-regression.php',\n              'tests/r308-route-ui-regression.php',\n"
)
rep(
    '.github/workflows/baseline-integrity.yml',
    "          php tests/three-plan-completion-unit.php\n",
    "          php tests/three-plan-completion-unit.php\n          php tests/storage-migration-unit.php\n          php tests/r291-r300-review-regression.php\n          php tests/r299-cross-flow-regression.php\n          php tests/r302-migration-postcondition-regression.php\n          php tests/r303-registration-recovery-regression.php\n          php tests/r304-google-auth-regression.php\n          php tests/r305-passkey-lifecycle-regression.php\n          php tests/r306-session-assurance-regression.php\n          php tests/r307-privacy-operations-regression.php\n          php tests/r308-route-ui-regression.php\n"
)

# Canonical storage/docs gate had stale exact-string checks and too little
# regression coverage for the current migration/passkey lifecycle.
rep(
    '.github/workflows/canonical-storage-and-docs.yml',
    "          php tests/passkey-webauthn-unit.php\n",
    "          php tests/passkey-webauthn-unit.php\n          php tests/r302-migration-postcondition-regression.php\n          php tests/r305-passkey-lifecycle-regression.php\n          php tests/r307-privacy-operations-regression.php\n          php tests/r308-route-ui-regression.php\n"
)
rep(
    '.github/workflows/canonical-storage-and-docs.yml',
    "          grep -Fq \"SAUTH_Passkeys::maybe_install()\" includes/class-sauth-operations.php\n",
    "          grep -Fq \"SAUTH_Passkeys::maybe_install( true )\" includes/class-sauth-operations.php\n"
)
rep(
    '.github/workflows/canonical-storage-and-docs.yml',
    "              'RELEASE-MANIFEST.md': ('1.2.1', '1.2.1-SOURCE-CANDIDATE.zip', 'WebAuthn', 'retained workflow artifact', 'class-sauth-storage-router.php'),",
    "              'RELEASE-MANIFEST.md': ('1.2.1', '1.2.1-SOURCE-CANDIDATE.zip', 'WebAuthn', 'Exact-head evidence rule', 'class-sauth-storage-router.php'),"
)

# Current dependency parity must be tested against the actual File 00 main
# frozen for this review, not an earlier correction candidate with the same version.
rep(
    '.github/workflows/file00-1.2.43-real-integration.yml',
    "  FILE00_REF: a71dc91b8ce80774adac35a90d7517999054f120",
    "  FILE00_REF: c4ab298b3ba2b870d507d32b36b1b4afd2771621"
)
rep(
    '.github/workflows/file00-1.2.43-real-integration.yml',
    "          php /tmp/wp-cli.phar config set SMC_MASTER_KEY_ID 'file02-integration-key-v1' --type=constant --path=/tmp/wordpress\n",
    "          php /tmp/wp-cli.phar config set SMC_MASTER_KEY_ID 'file02-integration-key-v1' --type=constant --path=/tmp/wordpress\n          php /tmp/wp-cli.phar config set SA_MASTER_KEY 'file02-integration-dedicated-master-key-2026' --type=constant --path=/tmp/wordpress\n"
)

# -------------------------------------------------------------------------
# R309-B: current documentation must describe repository/integration evidence
# accurately and must not call an integration reproduction 'live'.
# -------------------------------------------------------------------------
rep_all('README.md', 'live-proven bootstrap defect', 'WordPress-integration-proven bootstrap defect')
rep('README.md', '**Source coding | **Complete candidate**; current exact-head CI must pass |', '**Source coding | **Review candidate**; R301–R310 closure and exact-head CI are required |') if False else None
# exact table text contains pipe; use direct replacement below.
rep('README.md', '| Source coding | **Complete candidate**; current exact-head CI must pass |', '| Source coding | **Review candidate**; R301–R310 closure and exact-head CI are required |')
rep('README.md', 'The current paired repository candidate is File 00 `1.2.43`; it remains a separate unmerged source truth until its own exact-head release gate is green against the merged File 02 correction.', 'The File 00 integration dependency is a separate repository truth. R309 freezes its current `main` at `c4ab298b3ba2b870d507d32b36b1b4afd2771621` (runtime 1.2.43 / DB 1.4.5); File 02 release evidence must prove compatibility with that exact dependency or a later explicitly approved replacement.')

rep_all('CHANGELOG.md', 'Live-Proven Storage Router Bootstrap Correction', 'WordPress-Integration-Proven Storage Router Bootstrap Correction')
rep('CHANGELOG.md', '- Prevented password-only passkey management when File 00 two-factor protection is enabled; current passkey or File 00 step-up is required.', '- Historical 1.2.0 review initially coupled passkey management to File 00 step-up; the later ownership correction retires that coupling. Current File 02 management uses fresh File 02 passkey assurance or current-password reauthentication.')

rep('ARCHITECTURE.md', '# File 02 Architecture — Authentication and Accounts 1.2.0', '# File 02 Architecture — Authentication and Accounts 1.2.1')
rep('ARCHITECTURE.md', 'File 00 contracts** — fail-closed consumers for `smc.authentication-account 1.1.0`, membership assertions and step-up assurance; File 00 consumes the fresh File 02 passkey assurance projection.', 'File 00 contracts** — fail-closed consumers for `smc.authentication-account 1.1.0` and membership/eligibility assertions; File 00 consumes the fresh File 02 passkey assurance projection. Retired File 00 factor codes are not authentication ceremonies.')
rep('ARCHITECTURE.md', '- Passkey enrollment/revocation requires fresh reauthentication. If File 00 2FA is enabled, password-only management fails closed and File 00 step-up is required.', '- Passkey enrollment/revocation requires fresh File 02 reauthentication: a current File 02 passkey assurance when present, otherwise current-password verification. Retired File 00 Authenticator/recovery codes are neither solicited nor accepted.')
rep('ARCHITECTURE.md', 'Pre-1.1 `SA_` classes/options/actions/page metadata and SQL literals are bounded compatibility inputs. New public integrations use only the canonical values.', 'Pre-1.1 `SA_` classes/options/actions/page metadata and SQL literals are bounded compatibility inputs. New public integrations use only the canonical values. Google provider secrets are written only as dedicated-`SA_MASTER_KEY` AES-256-GCM `v3:` envelopes; WordPress auth salts are migration-only legacy decrypt inputs, never the current encryption authority.')

rep('CONTRACTS.md', '# File 02 Contract Register — Version 1.2.0', '# File 02 Contract Register — Version 1.2.1')
rep('CONTRACTS.md', 'Provider: File 00. Supplies current membership, suspension, verification and MFA-readiness assertions and performs canonical step-up verification. File 02 never reads private File 00 TOTP or recovery-code storage.', 'Provider: File 00. Supplies current membership, suspension, verification and eligibility assertions. File 02 never reads private File 00 TOTP/recovery-code storage and does not delegate its password/Google/passkey ceremony to retired File 00 factors.')
rep('CONTRACTS.md', '- management requires fresh reauthentication; File 00 2FA-enabled accounts cannot use password-only management.', '- management requires fresh File 02 reauthentication: fresh passkey assurance when available, otherwise current-password verification; retired File 00 factor codes are not accepted.')

rep('DATA-DICTIONARY.md', '# File 02 Data Dictionary — Version 1.2.0', '# File 02 Data Dictionary — Version 1.2.1')
rep('DATA-DICTIONARY.md', '- `sauth_google_client_secret` — AES-256-GCM encrypted provider secret.', '- `sauth_google_client_secret` — AES-256-GCM `v3:` provider-secret envelope derived only from a dedicated `SA_MASTER_KEY`; legacy salt-derived ciphertext is migration input only.')
rep('DATA-DICTIONARY.md', "- `_sauth_passkey_user_handle_v1`: random opaque WebAuthn user handle. It is not a WordPress user ID, email, phone or deterministic salt-derived identity. It is removed by File 02 passkey privacy erasure.", "- `_sauth_passkey_user_handle_v1`: random opaque WebAuthn user handle. It is not a WordPress user ID, email, phone or deterministic salt-derived identity. It is removed by File 02 passkey privacy erasure.\n- `_sauth_passkey_assurance_epoch_v1`: File 02 passkey-assurance invalidation epoch. It is rotated/revalidated for credential changes and removed by passkey privacy erasure.")
rep_all('DATA-DICTIONARY.md', 'canonical 1.2.0 configuration names', 'canonical 1.2.1 configuration names')

# Migration guide had 1.2.0 targets in the supported-path section.
rep('MIGRATION.md', '1. Fresh installation of 1.2.0.\n2. Upgrade from every repository-supported File 02 release to 1.2.0.', '1. Fresh installation of 1.2.1.\n2. Upgrade from every repository-supported File 02 release to 1.2.1.')
rep('MIGRATION.md', '4. Upgrade from 1.1.0 to 1.2.0 with additive passkey table/page creation and no password/Google/session data loss.', '4. Upgrade from 1.1.0/1.2.0 to 1.2.1 with additive passkey table/page creation and no password/Google/session data loss.')
rep('MIGRATION.md', '- Back up database, WordPress files and encryption-key configuration; prove isolated restore.', '- Back up database, WordPress files and encryption-key configuration; prove isolated restore. A dedicated `SA_MASTER_KEY` (32+ characters) is mandatory before enabling or migrating an encrypted Google Client Secret.')

rep('STAGING-ACCEPTANCE.md', '# File 02 Staging Acceptance — Version 1.0.0', '# File 02 Staging Acceptance — Version 1.2.1')
rep('STAGING-ACCEPTANCE.md', '- [ ] All seven File 02 tables/indexes and all managed pages are correct.', '- [ ] All eight File 02 tables/indexes (seven authentication tables plus `sauth_passkeys`) and all managed pages are correct; canonical version/schema markers are published only after storage postconditions.')
rep('STAGING-ACCEPTANCE.md', '- [ ] File 00 registration, email-completion, membership and step-up contracts pass positive/negative tests.', '- [ ] File 00 registration, email-completion and membership/eligibility contracts pass positive/negative tests; retired File 00 factor codes are not treated as File 02 authentication.')
rep('STAGING-ACCEPTANCE.md', '- [ ] New-device/network risk challenge and invalid/exhausted step-up.', '- [ ] New-device/network risk allow/challenge/deny behavior, including File 02 passkey step-up and unavailable-passkey fail-closed behavior.')
rep('STAGING-ACCEPTANCE.md', '- [ ] Google link/unlink and exact-email collision behavior.', '- [ ] Google login/link/unlink and exact-email collision behavior, risk evaluation, rollback postconditions and linkage-failure containment.')
rep('STAGING-ACCEPTANCE.md', '- [ ] Privacy export/erasure/anonymization and retention cleanup pass.', '- [ ] Privacy export pagination, passkey export/erasure/assurance-epoch cleanup, anonymization and retention cleanup pass.')

rep('THREAT-MODEL.md', '# File 02 Threat Model — Version 1.0.0', '# File 02 Threat Model — Version 1.2.1')
rep('THREAT-MODEL.md', '| Silent account merge | Explicit authenticated linking, exact canonical-email match, collision checks and File 00 step-up. |', '| Silent account merge | Explicit authenticated linking, exact canonical-email match, collision checks and fresh File 02 passkey assurance for sensitive link/unlink mutations. |')
rep('THREAT-MODEL.md', '| Secret leakage | Encrypted Google secret, redacted diagnostics/events, no raw tokens/passwords/full IP, secret scans. |', '| Secret leakage / key coupling | Google secret uses dedicated `SA_MASTER_KEY` AES-256-GCM v3 envelope; redacted diagnostics/events; no raw tokens/passwords/full IP; repository secret scans. |')
rep('THREAT-MODEL.md', '| Migration/repair damage | Additive `dbDelta`, File 02-only guarded repair, non-destructive uninstall, backup/restore and rollback rules. |', '| Migration/repair damage | Additive `dbDelta`, table/page postconditions before version markers, File 02-only guarded repair, Safe Mode containment, non-destructive uninstall, backup/restore and rollback rules. |\n| WebAuthn replay/key substitution | Atomic challenge claim, exact RP/origin/UV binding, server-parsed CBOR/COSE key, signature verification and counter-regression containment. |\n| Forged success UI | Server-signed notice receipts and one-time settings-success receipt; arbitrary query strings cannot establish authoritative success. |')

rep('PRIVACY-RETENTION.md', '# File 02 Privacy and Retention Register — 1.1.0', '# File 02 Privacy and Retention Register — 1.2.1')
rep('PRIVACY-RETENTION.md', '| Provider configuration | encrypted secret and non-secret client ID/status | while configured | operator-controlled deletion/rotation |', '| Provider configuration | dedicated-`SA_MASTER_KEY` AES-256-GCM v3 secret plus non-secret client ID/status | while configured | operator-controlled deletion/rotation |\n| Passkey credentials | opaque credential lookup, encrypted presentation copy, server-derived public key, lifecycle metadata | active plus bounded revoked/compromised retention | paginated export; row/user-handle/assurance-epoch erasure with postcondition verification |')
rep('PRIVACY-RETENTION.md', '- Google projections and local challenge/session/device rows are deleted where no legal/security hold applies.', '- Google projections and local challenge/session/device/passkey rows are deleted where no legal/security hold applies; passkey user handle and assurance epoch are removed and erasure reports retained data if any postcondition fails.')

# Incident runbook must implement the project-wide Live-First law explicitly.
rep('INCIDENT.md', '# File 02 Authentication Incident Runbook — 1.0.0', '# File 02 Authentication Incident Runbook — 1.2.1')
rep(
    'INCIDENT.md',
    '## Immediate actions\n\n1. Name incident commander, security operator, communications owner and rollback owner.',
    '## Immediate actions\n\n1. Freeze live reality before repository diagnosis: active File 02/plugin version, exact deployed package/files and checksum where possible, WordPress/PHP/runtime dependencies, File 02 database/schema version, relevant tables/columns/rows, `wp_options` migration/version/Safe-Mode state, active configuration and the contemporaneous runtime error/log. Live, staging and GitHub are separate realities.\n2. Name incident commander, security operator, communications owner and rollback owner.'
)
# Renumber the remaining original immediate list for readability.
rep('INCIDENT.md', '2. Enable Safe Mode or disable the affected provider/action; preserve public reading.\n3. Preserve exact source/package hashes, UTC timeline, redacted logs, trace IDs and System Check.\n4. Revoke affected sessions/challenges and rotate secrets only when evidence supports it.\n5. Do not delete evidence, expose private account data or instruct users to send passwords/OTP/recovery codes.', '3. Enable Safe Mode or disable the affected provider/action; preserve public reading.\n4. Preserve exact source/package hashes, UTC timeline, redacted logs, trace IDs and System Check.\n5. Revoke affected sessions/challenges and rotate secrets only when evidence supports it.\n6. Do not delete evidence, expose private account data or instruct users to send passwords/OTP/recovery codes.')
rep('INCIDENT.md', '- Identify affected versions, routes, providers, subjects and time window.', '- Follow the mandatory order: live symptom → live evidence → exact deployed version → DB/schema state → deployment parity → root cause → repository code. If GitHub and deployed code differ, stop ordinary debugging and perform a Deployment-Parity Audit.\n- Identify affected versions, routes, providers, subjects and time window.')
rep('INCIDENT.md', 'Complete post-incident root cause, timeline, data impact, corrective actions, residual risk, owner and due dates. Permanent product changes require change-control ratification.', 'Complete post-incident root cause, timeline, data impact, corrective actions, residual risk, owner and due dates. Every live incident report must separately state Repository HEAD / Deployed Version / DB Version / Migration State / Live Verification Status. Never call a defect resolved without deploy + live re-test + parity confirmation. If deployed source is unavailable, record exactly: “Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔” Permanent product changes require change-control ratification.')

# SBOM language must not elevate an integration reproduction to live evidence.
p = ROOT / 'SBOM.spdx.json'
sbom = json.loads(p.read_text(encoding='utf-8'))
for pkg in sbom.get('packages', []):
    if pkg.get('SPDXID') == 'SPDXRef-Package-File02':
        pkg['comment'] = 'Patch release 1.2.1 preserves DB schema 1.2.0 and corrects a bootstrap omission reproduced in real WordPress/File 00 integration testing; that integration evidence is not a live-deployment claim. Runtime includes bounded CBOR/COSE WebAuthn verification using PHP OpenSSL, dedicated-SA_MASTER_KEY Google secret encryption, and no bundled biometric/private-key component. The deterministic package manifest supplies the per-file SHA-256 inventory.'
p.write_text(json.dumps(sbom, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')

# Release manifest should name the current File00 parity input and the review state.
rep('RELEASE-MANIFEST.md', '- Required File 00 provider: `smc.authentication-account 1.1.0`', '- Required File 00 provider: `smc.authentication-account 1.1.0`; R309 integration freeze: File 00 `c4ab298b3ba2b870d507d32b36b1b4afd2771621` / runtime 1.2.43 / DB 1.4.5')

# Permanent regression proving R309 release truth.
(ROOT / 'tests/r309-release-truth-regression.php').write_text(r'''<?php
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
  array($sbom, 'not a live-deployment claim', 'SBOM elevates integration evidence to live truth'),
);
foreach ($checks as $c) { if (false === strpos($c[0], $c[1])) $fail[] = $c[2]; }
if ($fail) { fwrite(STDERR, "R309 regressions:\n- ".implode("\n- ",$fail)."\n"); exit(1); }
echo 'R309 release truth regression PASS ('.count($checks)." assertions).\n";
''', encoding='utf-8')
print('R309 corrections staged.')
