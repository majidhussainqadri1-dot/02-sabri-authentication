# File 02 — Sabri Authentication

This repository preserves the original File 02 baseline and develops the corrected authentication integration for the Sabri Social Homeopathy Platform.

## Current corrective candidate

- Version: `0.2.0`
- Branch: `audit/file-02-source-review`
- Membership, roles, profiles, identity, verification, and 2FA authority: **File 00 — Sabri Membership Core**
- File 02 scope: explicit same-email Google link/login/unlink integration, recovery routing, access integration, privacy coverage, and authentication-specific rate limiting
- Direct File 02 registration or role mutation: **Removed**
- Original ZIP committed: **No**
- Production approval: **No**
- Live installation authorization: **No**

## Evidence sets

The original baseline evidence remains in:

- `BASELINE-LOCK.json`
- `SOURCE-INVENTORY.tsv`
- `CHECKSUMS.sha256`
- `MANIFEST.md`
- baseline commit `8ce0653b6f1de3beb6899642c4446653c40f0501`

The corrected release evidence is in:

- `RELEASE-LOCK.json`
- `RELEASE-INVENTORY.tsv`
- `RELEASE-CHECKSUMS.sha256`
- `RELEASE-MANIFEST.md`
- `CORRECTIVE-PROVENANCE.md`
- `CORRECTIVE-REVIEW.md`

Passing CI establishes source integrity, architecture guards, no-network security unit checks, and syntax only. It does not establish Google OAuth production readiness, WordPress runtime compatibility, staging acceptance, privacy/legal approval, or production authorization.
