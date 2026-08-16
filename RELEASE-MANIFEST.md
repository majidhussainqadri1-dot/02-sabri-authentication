# File 02 — Release Manifest — 1.2.6

## Release identity

- Module: `02 — Authentication and Accounts`
- Candidate version/schema: `1.2.6 / 1.2.1`
- Passkey schema/assurance: `1.0.1 / 1.0.0`
- Candidate branch: `review/file02-r337-fresh-audit-2026-08-16`
- Current repository `main`: `0f011b1876e217b7ee46f92903e5315538c1025e`
- Historical incident baseline main: `8192c45b595b34e13e09934e3b2d554aa2d8553f`
- Intended canonical repository name: `02-sabri-authentication-and-accounts` (owner-level rename remains external)
- Current transport repository: `02-sabri-authentication`
- Package root: `02-sabri-authentication`
- Runtime dependency: File 00 `1.2.44+`, `smc.authentication-account 1.1.0+`
- Minimum WordPress: `6.4+`
- Advanced Trust projection: File 02 passkey assurance `1.0.0`, owner `file02`

## R337 review identity

R337 is a fresh source/release review performed after the R331–R336 line. The complete R337 review was frozen before correction at exact pre-correction HEAD `972f5fd2cc59fe69bf465b844ac36c740533f7dd` in `review-evidence/R337-REVIEW-FROZEN.md`.

The frozen ledger contains seven verified defects: four High and three Medium. The source correction set includes File 00/WordPress dependency alignment, canonical professional-account age validation, passkey credential-state persistence postconditions, synchronous session-registry failure containment, preservation of WordPress administrative-email confirmation, and release-evidence identity correction.

The exact multi-file corrective commit is `e04cfdf51a6d876f70c0296acfb9692fef5a54df`. Later commits on this same branch add regression and evidence alignment; therefore the releasable source identity is the eventual final exact branch HEAD, not the intermediate correction commit.

## Historical paired integration evidence

GitHub Actions run `31850253635` is retained as **historical pre-R337** repository integration evidence. It passed against File 02 `f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9` and File 00 `1d7f215193d778b0977c8e50d738c42e1e5f66c2` / runtime `1.2.44`, including WordPress 7.0 / MariaDB 11.4 installation, File 00 deferred bootstrap, File 02 fresh activation, canonical nine-type taxonomy parity, passkey migration, logical-identity migration and final paired boundaries.

That run predates R337 source changes. Exact **post-R337** paired File 00 1.2.44 revalidation is therefore still required for the current candidate. The historical run is not Hostinger staging or live evidence.

## Installable runtime inventory

The deterministic builder is intended to include only the plugin runtime and release-facing documentation:

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

Tests, CI workflows, review evidence, temporary correction machinery, development reports and committed archives must remain excluded from an installable ZIP.

## Exact-head evidence rule

The final corrected GitHub Actions head must:

1. prove checkout equals the immutable source HEAD;
2. lint every PHP source file on PHP 7.4 and 8.3;
3. execute all security, assurance, registration, completion, plan, WebAuthn and permanent R29x–R337 regressions;
4. validate the current R337 dependency/taxonomy/passkey/session/core-login postconditions;
5. validate JavaScript syntax and CSS structure;
6. enforce the review-only artifact/correction-machinery boundary;
7. build/package only if the release workflow is deliberately run from that exact head;
8. reject archive traversal, unexpected roots, secrets and forbidden files;
9. retain historical paired File 00 evidence only with its exact source identities;
10. require a new post-R337 File 00 1.2.44 paired integration before attributing cross-file integration success to the current head; and
11. never infer staging/live completion from repository/package success.

## Current completion boundary

- Specified: complete.
- Coded/source: R337 corrective candidate.
- Packaged: pending final exact-head package proof.
- Automated QA: pending final exact-head green proof.
- Staging-Accepted: no.
- Live-Deployed: no.
- Operational: no.

Hostinger staging, real production-domain WebAuthn, SMTP/Google, browser/RTL/WCAG, other cross-file/theme/LiteSpeed integrations, performance/load, backup/restore, rollback, Founder acceptance, production deployment and operations remain separate gates.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
