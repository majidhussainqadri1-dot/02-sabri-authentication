# File 02 — Release Manifest — 1.1.0

## Release identity

- Module: `02 — Authentication and Accounts`
- Candidate version/schema: `1.1.0 / 1.1.0`
- Candidate branch: `codex/file02-three-plan-completion-1.1.0`
- Canonical repository: `02-sabri-authentication-and-accounts`
- Current transport repository: `02-sabri-authentication`
- Package root: `02-sabri-authentication`
- Package: `02-sabri-authentication-1.1.0-SOURCE-CANDIDATE.zip`
- Manifest: `02-sabri-authentication-1.1.0-MANIFEST.json`
- Checksums: `CHECKSUMS.sha256` and exact-head CI checksum record
- SBOM: `SBOM.spdx.json`
- Required File 00 provider: `smc.authentication-account 1.1.0`

## Installable runtime inventory

The deterministic builder includes only:

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

Tests, CI workflows, historical baseline locks, development reports and committed archives are excluded from the installable ZIP.

## Exact-head evidence rule

The current GitHub Actions head must:

1. lint every PHP source file on PHP 7.4 and 8.3;
2. execute all security, assurance, registration, completion and three-plan suites;
3. validate JavaScript syntax and CSS structure;
4. build the package twice from a fixed source epoch;
5. prove byte-identical ZIP and manifest output;
6. reject archive traversal, unexpected roots, secrets and forbidden files;
7. clean-extract and lint every packaged PHP file;
8. publish the ZIP, manifest and checksums as a retained workflow artifact.

The actual package digest is intentionally produced from the immutable workflow head rather than guessed or copied from an earlier release.

## Completion boundary

This manifest proves source/package identity only after the current CI succeeds. Hostinger staging, real SMTP/Google, browser/RTL/WCAG, performance/load, backup/restore, rollback, Founder acceptance, live deployment and operations remain separate gates.
