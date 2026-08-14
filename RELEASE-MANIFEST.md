# File 02 — Release Manifest — 1.2.6

## Release identity

- Module: `02 — Authentication and Accounts`
- Candidate version/schema: `1.2.6 / 1.2.1`
- Passkey schema/assurance: `1.0.1 / 1.0.0`
- Candidate branch: `fix/file02-passkey-index-reconciliation-1.2.6`
- Current repository `main` re-verified during R319: `0f011b1876e217b7ee46f92903e5315538c1025e`
- Historical incident baseline main: `8192c45b595b34e13e09934e3b2d554aa2d8553f`
- Intended canonical repository name: `02-sabri-authentication-and-accounts` (owner-level rename is still external)
- Current transport repository: `02-sabri-authentication`
- Package root: `02-sabri-authentication`
- Package: `02-sabri-authentication-1.2.6-SOURCE-CANDIDATE.zip`
- Manifest: `02-sabri-authentication-1.2.6-MANIFEST.json`
- Checksums: `CHECKSUMS.sha256` plus exact-head CI checksum record
- SBOM: `SBOM.spdx.json`
- Required File 00 provider: `smc.authentication-account 1.1.0`
- Exact paired File 00 repository candidate: `1d7f215193d778b0977c8e50d738c42e1e5f66c2`, runtime `1.2.44`, DB `1.4.5`
- Advanced Trust projection: File 02 passkey assurance `1.0.0`, owner `file02`
- Former taxonomy/provider cross-file blocker: **closed at repository/integration level** by exact run `31850253635`; this is not a staging/live claim.

## Current hardening release

Version 1.2.6 carries the R321–R336 source/release hardening line. R334 corrected dbDelta-incompatible passkey index formatting discovered by real MariaDB upgrade rehearsal. R335 then corrected the proven MariaDB state in which the unique key name `credential_lookup_hash` survives a legacy column rename while becoming bound to `credential_hash`; File 02 now reconciles only that exact stale binding before dbDelta, preserves uniqueness, and fails closed on unexpected conflicts. DB identity remains 1.2.1 and passkey schema identity remains 1.0.1 because the intended physical schema did not change.

R336 records the successful exact paired File 00/File 02 integration and replaces the stale hard-coded architecture release identity with `RELEASE-LOCK.json`-driven identity. This source identity remains separate from staging/live completion.

## Exact cross-file integration evidence

GitHub Actions run `31850253635` passed against File 02 `f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9` and File 00 `1d7f215193d778b0977c8e50d738c42e1e5f66c2`:

1. immutable paired input verification;
2. WordPress 7.0 / MariaDB 11.4 installation;
3. File 00 activation in queued state plus supported deferred administrator bootstrap to DB 1.4.5;
4. File 02 fresh activation at runtime 1.2.6 / DB 1.2.1 / passkey schema 1.0.1;
5. two-sided canonical account taxonomy parity for `member`, `patient`, `student`, `doctor`, `teacher`, `researcher`, `pharmacy`, `clinic`, `publisher`;
6. legacy passkey column/index upgrade on real MariaDB;
7. legacy logical-identity collision migration; and
8. final paired runtime/schema boundary verification.

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
3. execute all security, assurance, registration, completion, plan, WebAuthn and R29x–R336 permanent regressions;
4. enforce the release-lock-driven architecture guard, including File 02/File 00 ownership and rejection of client-supplied WebAuthn public keys;
5. prove the storage-router source is loaded before File 02 startup;
6. validate JavaScript syntax and CSS structure;
7. build the 1.2.6 package twice from a fixed source epoch and prove byte identity;
8. reject archive traversal, unexpected roots, secrets and forbidden files;
9. clean-extract and lint every packaged PHP file and prove bootstrap/migration invariants survive packaging;
10. retain the separate exact WordPress/File 00 integration evidence; and
11. never infer staging/live completion from repository/package success.

The actual package digest may be claimed only from the successful immutable workflow head.

## External completion boundary

Repository/source integration with File 00 is proven as described above. Hostinger staging, real production-domain WebAuthn authenticators, real SMTP/Google, browser/RTL/WCAG, other cross-file/theme/LiteSpeed integrations, performance/load, backup/restore, rollback, Founder acceptance, live deployment and operations remain separate gates.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
