# File 02 — Release Manifest — 1.3.0

## R338 source-lineage release block

**Packaging is not authorized for this tree.** The current recoverable runtime marker is `1.3.0 / DB 1.3.0 / passkey 1.0.1`, while the Founder-approved X24 1.3.x architecture requires passkey `1.1.0`, Modern Auth, Authentication Assurance v2, Shared Signals, the X24 data plane and routes. The later reviewed corpus records runtime `1.3.8`; exact source bytes are not recovered here. `SOURCE-LINEAGE-LOCK.json` is machine-authoritative and sets `packaging_allowed=false`, `staging_allowed=false`, and `deployment_allowed=false`. Historical package names below are descriptive of the prior candidate only and are not authorized R338 outputs.


## Release identity

- Module: `02 — Authentication and Accounts`
- Candidate version/schema: `1.3.0 / 1.3.0`
- Passkey schema/assurance: `1.0.1 / 1.0.0`
- Candidate branch: `review/file02-r338-fresh-review-fix-2026-08-16`
- Current repository `main` re-verified during R319: `0f011b1876e217b7ee46f92903e5315538c1025e`
- Historical incident baseline main: `8192c45b595b34e13e09934e3b2d554aa2d8553f`
- Intended canonical repository name: `02-sabri-authentication-and-accounts` (owner-level rename is still external)
- Current transport repository: `02-sabri-authentication`
- Package root: `02-sabri-authentication`
- Package: **BLOCKED — no R338 installable candidate is authorized from this lineage**
- Manifest: **BLOCKED with package until source-lineage reconciliation**
- Checksums: `CHECKSUMS.sha256` plus exact-head CI checksum record
- SBOM: `SBOM.spdx.json`
- Required File 00 provider: `smc.authentication-account 1.1.0`
- Exact paired File 00 repository candidate: `1d7f215193d778b0977c8e50d738c42e1e5f66c2`, runtime `1.2.44`, DB `1.4.5`
- Advanced Trust projection: File 02 passkey assurance `1.0.0`, owner `file02`
- Current cross-file blocker: exact File 00 `1.2.44` / File 02 `1.3.0` integration is pending; run `31850253635` is retained only as historical File 02 `1.2.6` evidence.

## Current hardening release

Version 1.3.0 preserves the R321–R336 line and adds R337 comprehensive remediation. The repaired legacy copy uses explicit compatibility-router suspension instead of trusting SQL text; DB identity advances to `1.3.0` so supported installations rerun that copy. Email verification uses compare-and-set state transitions. Google registration/login/link/unlink share ordered database locks and exact postconditions. Password, Google and passkey success require exact WordPress/File 02 session binding plus persisted risk evidence. Passkeys enforce immutable backup eligibility, strict counter regression, RSA-2048/65537 and exact receipt ownership. Privacy export/erasure is bounded, covers canonical and preserved legacy stores, and proves recursive event anonymization.

Passkey schema identity remains `1.0.1` because the intended passkey table schema did not change. This source identity remains separate from exact-head CI, staging and live completion.

## Cross-file integration evidence boundary

Current exact-head File 02 `1.3.0` integration with File 00 `1d7f215193d778b0977c8e50d738c42e1e5f66c2` is pending. The workflow must prove:

1. immutable paired input verification;
2. WordPress 7.0 / MariaDB 11.4 installation;
3. File 00 activation in queued state plus supported deferred administrator bootstrap to DB 1.4.5;
4. File 02 fresh activation at runtime 1.3.0 / DB 1.3.0 / passkey schema 1.0.1;
5. two-sided canonical account taxonomy parity for `member`, `patient`, `student`, `doctor`, `teacher`, `researcher`, `pharmacy`, `clinic`, `publisher`;
6. legacy passkey column/index upgrade on real MariaDB;
7. legacy logical-identity collision migration;
8. active-router one-way legacy-table migration; and
9. final paired runtime/schema boundary verification.

Historical run `31850253635` passed the analogous boundary for File 02 `f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9` / runtime `1.2.6`; it is not evidence for this candidate.

## Historical bootstrap correction

File 02 1.2.0 contained `includes/class-sauth-storage-router.php` and invoked `SAUTH_Storage_Router::init()` during `plugins_loaded`, but the main plugin file did not require that class. Real WordPress cross-repository testing therefore activated the plugin and then reproduced a fatal on the next WordPress request. Version 1.2.1 loaded the storage-router source before startup registration and added permanent source, package and real WordPress reload guards.

## Installable runtime inventory

The deterministic builder includes only the plugin runtime and release-facing documentation:

```text
sabri-authentication.php
uninstall.php
readme.txt
admin/
assets/
includes/
templates/
ARCHITECTURE.md
CONTRACTS.md
DATA-DICTIONARY.md
MIGRATION.md
ROLLBACK.md
BACKUP-RESTORE.md
INCIDENT.md
STAGING-ACCEPTANCE.md
THREAT-MODEL.md
PRIVACY-RETENTION.md
CHANGELOG.md
SBOM.spdx.json
PACKAGE-MANIFEST.json (generated)
```

Tests, CI workflows, historical review evidence, development reports and committed archives are excluded from the installable ZIP.

## Exact-head evidence rule

The corrected current GitHub Actions head must:

1. prove checkout equals the immutable source HEAD;
2. lint every PHP source file on PHP 7.4 and 8.3;
3. execute all security, assurance, registration, completion, plan, WebAuthn and R29x–R337 permanent regressions;
4. enforce the release-lock-driven architecture guard, including File 02/File 00 ownership and rejection of client-supplied WebAuthn public keys;
5. prove the storage-router source is loaded before File 02 startup;
6. validate JavaScript syntax and CSS structure;
7. build the 1.3.0 package twice from a fixed source epoch and prove byte identity;
8. reject archive traversal, unexpected roots, secrets and forbidden files;
9. clean-extract and lint every packaged PHP file and prove bootstrap/migration invariants survive packaging;
10. pass the separate exact WordPress/File 00 integration against the same File 02 head; and
11. never infer staging/live completion from repository/package success.

The actual package digest may be claimed only from the successful immutable workflow head.

## External completion boundary

Current repository/source integration with File 00 is pending as described above. Hostinger staging, real production-domain WebAuthn authenticators, real SMTP/Google, browser/RTL/WCAG, other cross-file/theme/LiteSpeed integrations, performance/load, backup/restore, rollback, Founder acceptance, live deployment and operations remain separate gates.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
