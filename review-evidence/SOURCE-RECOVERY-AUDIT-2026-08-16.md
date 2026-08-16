# File 02 — Exact 1.3.8 Source Recovery Audit — 2026-08-16

## Purpose

This audit executes the recovery step required by R338 before any reconciliation of R337/R338 hardening onto the Founder-approved X24 product lineage. It does **not** reconstruct missing source from plans, branch names or historical summaries.

## Governing evidence law

A historical plan/review document can prove that an artifact existed and can prove its recorded identity/hash. It cannot supply the artifact bytes unless those bytes are actually recovered. A GitHub branch name is not proof of the code version stored at that ref. Reconciliation is forbidden until the exact later source is recovered and checksum/manifest parity is established.

## Historical 1.3.8 identities reconfirmed

The searchable File Library contains the File 02 v2.9 Ninth Eighty-Round Reviewed Corrected Final document. It records the following frozen local artifacts after the ninth 80-round cycle:

| Artifact | Recorded SHA-256 |
|---|---|
| `02-sabri-authentication-1.3.8-SOURCE-CANDIDATE.zip` | `9e93cc0282ec407a7efc5bb37559a6d6e87f9e20c9e9453e33b338e49f494e2f` |
| `02-sabri-authentication-1.3.8-MANIFEST.json` | `5300a3483116fedb7cd7248253ecf98f3d0a0b7ab6621b8a2d70c7b682c72bb8` |
| `02-sabri-authentication-1.3.8-NINTH-EIGHTY-ROUND-SOURCE-REVIEW-BUNDLE.zip` | `f284fbe8296f7d3085c5a10f4710c0962b0aff8ba1997667779605ff9d0bff57` |
| `NINTH-EIGHTY-ROUND-SOURCE-MANIFEST.json` | `5a15260da739353d911568c4ebe2a75d0989a4aff515c3dbd9e17e4c20702108` |

The same document records local runtime `1.3.8`, DB `1.3.0`, passkey schema `1.1.0`, deterministic double-build parity, clean-extract PHP lint, archive-safety checks and 72/72 package-manifest hash parity. It also kept GitHub exact-head, Hostinger staging and live/operational gates separate.

## Search performed in this audit

### 1. Searchable File Library

Exact artifact filenames, exact SHA-256 values and broad `1.3.8 source bundle` queries were searched. The search returned the historical v2.9 evidence document and older review/patch evidence, but did **not** expose the exact 1.3.8 installable ZIP, its manifest, the source-review bundle or the source manifest as recoverable searchable files.

Conclusion: historical identity is proven; exact artifact bytes are **not recovered from searchable File Library evidence**.

### 2. Current GitHub branches

The repository branch inventory was enumerated. There is no branch whose current ref proves the approved 1.3.8 source.

The misleading named refs were checked specifically:

- `codex/file02-modern-auth-24-enhancements-1.3.0` → `c895ec17c631e6a28c86aa659bf947f9d326dc4d`
- `codex/file02-fifth-ten-round-corrective-1.3.4` → `c895ec17c631e6a28c86aa659bf947f9d326dc4d`

The retained GitHub Actions artifact produced from the modern-auth named branch identifies its actual artifact as `file02-authentication-and-accounts-1.2.0-c895ec17c631e6a28c86aa659bf947f9d326dc4d`. Therefore the branch label `1.3.0` cannot be promoted to source-version evidence. The `1.3.4` named branch resolving to the same commit likewise cannot prove 1.3.4 or 1.3.8 source.

### 3. GitHub Releases and Tags

Current repository Releases: none.

Current repository Tags: none.

Therefore no release/tag object currently supplies the historical 1.3.8 bytes.

### 4. Currently retained GitHub Actions artifacts

The retained Actions-artifact inventory was inspected. It exposes current `1.3.0` review/remediation artifacts and older `1.2.x/1.1.x` artifacts, including the `c895ec17...` artifact noted above. It does not expose the historically recorded `1.3.8-SOURCE-CANDIDATE` or `NINTH-EIGHTY-ROUND-SOURCE-REVIEW-BUNDLE` in the currently retained artifact set.

This finding is deliberately bounded: it does **not** assert that the historical local 1.3.8 files never existed; the v2.9 evidence says they did. It states only that the exact bytes are not present in the current searchable/recoverable evidence inspected in this audit.

## Recovery result

**Exact 1.3.8 source recovery: FAILED / BLOCKED — exact bytes not found.**

Historical artifact identity: **VERIFIED FROM GOVERNING REVIEW EVIDENCE.**

Checksum parity against actual 1.3.8 bytes: **NOT POSSIBLE YET.**

R337/R338 → approved X24 reconciliation: **NOT AUTHORIZED YET.**

Reconstruction of 1.3.8 from plans and calling it “recovered”: **PROHIBITED.**

Packaging current recoverable 1.3.0 as the approved product replacement: **BLOCKED.**

Staging/deployment of that incomplete lineage as production-complete File 02: **BLOCKED.**

`SOURCE-LINEAGE-LOCK.json` was refreshed with this audit result and remains fail-closed.

## What exact evidence would unblock recovery

Any one of the following may begin a parity-verification phase, but does not automatically prove parity:

1. the exact `02-sabri-authentication-1.3.8-NINTH-EIGHTY-ROUND-SOURCE-REVIEW-BUNDLE.zip` bytes;
2. the exact `02-sabri-authentication-1.3.8-SOURCE-CANDIDATE.zip` bytes together with sufficient source/manifest evidence;
3. a later exact superseding source whose identity and continuity from the approved X24 lineage can be independently proved.

The recovered bytes must then match the recorded SHA-256/source-manifest identity before any hardening reconciliation begins.

## Required sequence after exact bytes are recovered

1. SHA-256 and source-manifest parity freeze.
2. Exact source inventory and X24/contract/schema verification.
3. Reconcile R337/R338 hardening without dropping F02-X24-001..024.
4. Fresh complete Review → frozen defect ledger → fix → retest cycle 1.
5. Separate fresh adversarial Review → frozen defect ledger → fix → retest cycle 2.
6. Exact-head File 00 integration, source QA, deterministic package QA, WordPress/MariaDB fresh/upgrade/rollback evidence.
7. Hostinger staging acceptance.
8. Separate authorized live deployment → live re-test → deployed/repository parity confirmation.

## Live incident boundary

- Repository review branch: `review/file02-r338-fresh-review-fix-2026-08-16`
- Repository `main`: remains a separate older repository reality.
- Deployed File 02 version/package: unverified.
- Live DB/schema version: unverified.
- Migration state: unverified.
- Live verification: OPEN / NOT RESOLVED.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
