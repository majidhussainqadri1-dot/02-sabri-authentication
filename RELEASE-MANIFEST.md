# File 02 — Release Manifest — 1.2.1

## Release identity

- Module: `02 — Authentication and Accounts`
- Candidate version/schema: `1.2.1 / 1.2.0`
- Passkey schema/assurance: `1.0.0 / 1.0.0`
- Candidate branch: `fix/live-bootstrap-storage-router-1.2.1`
- Incident baseline main: `8192c45b595b34e13e09934e3b2d554aa2d8553f`
- Canonical repository: `02-sabri-authentication-and-accounts`
- Current transport repository: `02-sabri-authentication`
- Package root: `02-sabri-authentication`
- Package: `02-sabri-authentication-1.2.1-SOURCE-CANDIDATE.zip`
- Manifest: `02-sabri-authentication-1.2.1-MANIFEST.json`
- Checksums: `CHECKSUMS.sha256` plus exact-head CI checksum record
- SBOM: `SBOM.spdx.json`
- Required File 00 provider: `smc.authentication-account 1.1.0`
- Paired File 00 repository candidate: `1.2.43` exact head `a71dc91b8ce80774adac35a90d7517999054f120` until separately superseded/merged
- Advanced Trust projection: File 02 passkey assurance `1.0.0`, owner `file02`

## Patch correction

File 02 1.2.0 contained `includes/class-sauth-storage-router.php` and invoked `SAUTH_Storage_Router::init()` during `plugins_loaded`, but the main plugin file did not require that class. Real WordPress cross-repository testing therefore activated the plugin and then reproduced a fatal on the next WordPress request. Version 1.2.1 loads the storage-router source before startup registration and adds permanent source, package and real WordPress reload guards.

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

The runtime inventory includes `includes/class-sauth-storage-router.php`, `includes/class-sauth-passkeys.php` and the browser WebAuthn client in `assets/js/authentication.js`. Tests, CI workflows, historical baseline locks, development reports and committed archives are excluded from the installable ZIP.

## Exact-head evidence rule

The current GitHub Actions head must:

1. prove checkout equals the immutable PR/source HEAD;
2. lint every PHP source file on PHP 7.4 and 8.3;
3. execute all security, assurance, registration, completion, prior three-plan and WebAuthn CBOR/COSE/signature suites;
4. enforce the architecture guard, including File 02/File 00 ownership and rejection of client-supplied WebAuthn public keys;
5. prove the storage-router source is loaded before File 02 startup;
6. validate JavaScript syntax and CSS structure;
7. build the 1.2.1 package twice from a fixed source epoch and prove byte identity;
8. reject archive traversal, unexpected roots, secrets and forbidden files;
9. clean-extract and lint every packaged PHP file and prove the storage-router bootstrap binding survives packaging;
10. run a real WordPress/MariaDB integration against the exact paired File 00 candidate, activate File 02 and prove at least two subsequent independent WordPress loads remain non-fatal; and
11. publish the ZIP, manifest and checksums as a retained workflow artifact.

The actual package digest is produced only from the immutable workflow head rather than guessed or copied from an earlier release.

## Source completion boundary

The 1.2.1 source candidate preserves the previous 12 File 02 functional requirements plus CV-005 Passkey/MFA ceremony, CV-006 device/session center and CV-010 recovery within File 02's canonical boundary. File 00 remains identity/MFA-policy authority and File 24 remains assurance/risk governance rather than a credential store.

## External completion boundary

This manifest can prove source/package identity and automated evidence only after current exact-head CI succeeds. Hostinger staging, real production-domain WebAuthn authenticators, SMTP/Google, File 00 Advanced Trust integration, browser/RTL/WCAG, performance/load, backup/restore, rollback, Founder acceptance, live deployment and operations remain separate gates.
