# File 02 — Release Manifest — 1.2.0

## Release identity

- Module: `02 — Authentication and Accounts`
- Candidate version/schema: `1.2.0 / 1.2.0`
- Passkey schema/assurance: `1.0.0 / 1.0.0`
- Candidate branch: `codex/file02-four-plan-passkey-completion-1.2.0`
- Canonical repository: `02-sabri-authentication-and-accounts`
- Current transport repository: `02-sabri-authentication`
- Package root: `02-sabri-authentication`
- Package: `02-sabri-authentication-1.2.0-SOURCE-CANDIDATE.zip`
- Manifest: `02-sabri-authentication-1.2.0-MANIFEST.json`
- Checksums: `CHECKSUMS.sha256` plus exact-head CI checksum record
- SBOM: `SBOM.spdx.json`
- Required File 00 provider: `smc.authentication-account 1.1.0`
- Advanced Trust projection: File 02 passkey assurance `1.0.0`, owner `file02`

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

The runtime inventory therefore includes `includes/class-sauth-passkeys.php` and the browser WebAuthn client in `assets/js/authentication.js`. Tests, CI workflows, historical baseline locks, development reports and committed archives are excluded from the installable ZIP.

## Exact-head evidence rule

The current GitHub Actions head must:

1. lint every PHP source file on PHP 7.4 and 8.3;
2. execute all security, assurance, registration, completion, prior three-plan and new WebAuthn CBOR/COSE/signature suites;
3. enforce the fourth-plan architecture guard, including File 02/File 00 ownership and rejection of client-supplied WebAuthn public keys;
4. validate JavaScript syntax and CSS structure;
5. build the package twice from a fixed source epoch;
6. prove byte-identical ZIP and manifest output;
7. reject archive traversal, unexpected roots, secrets and forbidden files;
8. clean-extract and lint every packaged PHP file;
9. verify the passkey runtime file is inside the clean package; and
10. publish the ZIP, manifest and checksums as a retained workflow artifact.

The actual package digest is produced only from the immutable workflow head rather than guessed or copied from an earlier release.

## Source completion boundary

The 1.2.0 source candidate implements the previous 12 File 02 functional requirements plus CV-005 Passkey/MFA ceremony, CV-006 device/session center and CV-010 recovery within File 02's canonical boundary. File 00 remains identity/MFA-policy authority and File 24 remains assurance/risk governance rather than a credential store.

## External completion boundary

This manifest can prove source/package identity and automated evidence only after current exact-head CI succeeds. Hostinger staging, real production-domain WebAuthn authenticators, SMTP/Google, File 00 Advanced Trust integration, browser/RTL/WCAG, performance/load, backup/restore, rollback, Founder acceptance, live deployment and operations remain separate gates.
