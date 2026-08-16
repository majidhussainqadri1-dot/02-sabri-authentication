# File 02 — R338 Source Review Manifest — Recoverable Runtime Marker 1.3.0

## R338 source-lineage release block

**Packaging is not authorized for this tree.** The current recoverable source reports runtime/DB `1.3.0 / 1.3.0` and passkey schema `1.0.1`, while the Founder-approved amendment `SSH-F02-AMD-2026-08-08-X24` requires passkey schema `1.1.0`, Modern Auth, Authentication Assurance v2, Shared Signals, the X24 data plane and routes. The later reviewed corpus records runtime `1.3.8 / DB 1.3.0 / passkey 1.1.0`; exact source bytes are not recovered in the current evidence.

`SOURCE-LINEAGE-LOCK.json` is machine-authoritative and sets `packaging_allowed=false`, `staging_allowed=false`, and `deployment_allowed=false`. Therefore this document is a **source-review manifest**, not an installable release manifest.

## Source identity

- Module: `02 — Authentication and Accounts`
- Current recoverable runtime/DB marker: `1.3.0 / 1.3.0`
- Current recoverable passkey schema/assurance: `1.0.1 / 1.0.0`
- Approved later reviewed lineage: `1.3.8 / DB 1.3.0 / passkey 1.1.0` — exact source bytes unrecovered
- R338 branch: `review/file02-r338-fresh-review-fix-2026-08-16`
- R338 frozen pre-correction HEAD: `6e007be952817c400efe93fbecbd5101689dfeb7`
- Current repository `main`: `0f011b1876e217b7ee46f92903e5315538c1025e`
- Intended canonical repository name: `02-sabri-authentication-and-accounts` (owner-level rename remains external)
- Current transport repository: `02-sabri-authentication`
- Package root if/when lineage is eventually cleared: `02-sabri-authentication`
- Installable package: **BLOCKED — none authorized from this lineage**
- Package manifest/checksums: **BLOCKED with package**
- SBOM source evidence: `SBOM.spdx.json`
- Required File 00 runtime: `1.2.44+`
- Required File 00 provider: `smc.authentication-account 1.1.0+`
- Minimum WordPress: `6.4+`
- Exact paired File 00 repository candidate used by prior integration infrastructure: `1d7f215193d778b0977c8e50d738c42e1e5f66c2`, runtime `1.2.44`, DB `1.4.5`
- Assurance producer present in current recoverable tree: File 02 passkey assurance `1.0.0`, owner `file02`

## Current recoverable hardening scope

The recoverable tree preserves substantial authentication hardening from the R321–R337 line: registration/recovery, email-verification CAS protections, Google mutation locking, exact session binding, passkey migration/index reconciliation, passkey counter/backup/RSA hardening, risk/session controls, privacy export/erasure, canonical account taxonomy and File 00 ownership boundaries.

Those corrections remain useful source work, but they do **not** establish Founder-approved X24 completeness. The current tree lacks the complete six-class X24 ownership layer, required X24 tables, passkey schema `1.1.0`, approved account-security/collision-resolution routes and v2/Modern Auth/Shared Signals contracts.

## Historical cross-file evidence boundary

Historical run `31850253635` passed paired repository integration for exact File 02 `f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9` / runtime `1.2.6` with File 00 `1d7f215193d778b0977c8e50d738c42e1e5f66c2` / runtime `1.2.44` on WordPress 7.0 / MariaDB 11.4.

That run remains valid only for those exact historical inputs. It does not prove the current R338 branch and does not recover the approved X24/1.3.8 source lineage.

A future exact-head paired integration of this recoverable R338 tree may be useful as source-hardening evidence, but **cannot** by itself close the larger source-lineage blocker.

## Source-review QA rule

While `SOURCE-LINEAGE-LOCK.json` remains blocked, repository QA may only prove the recoverable source state. It must:

1. prove checkout equals the immutable source HEAD;
2. lint every PHP source file on PHP 7.4 and 8.3;
3. execute all applicable security, assurance, registration, completion, WebAuthn, migration, privacy, route and R29x–R338 regressions;
4. verify File 00 `1.2.44+` and WordPress `6.4+` dependency truth;
5. verify WordPress-owned `confirm_admin_email` is not intercepted;
6. enforce `SOURCE-LINEAGE-LOCK.json` and the current approved-scope incompleteness disclosure;
7. validate JavaScript syntax and CSS structure; and
8. confirm temporary correction machinery is absent.

It must **not** generate or retain an installable release artifact. `tools/build-package.sh` is deliberately expected to refuse packaging while `packaging_allowed=false`.

## Conditions required before packaging can ever be re-enabled

- Recover exact reviewed File 02 `1.3.8` source bytes or prove a separately reviewed later superseding exact source.
- Verify recovered source/artifact identity against the recorded approved hashes.
- Reconcile R337/R338 hardening without dropping F02-X24-001..024.
- Complete two fresh full review/fix/retest cycles after the last coding change.
- Achieve exact-head QA and exact File 00 integration on the reconciled later source.
- Only then update `SOURCE-LINEAGE-LOCK.json` through a separately reviewed evidence change and permit deterministic package generation.

Hostinger staging, real WebAuthn/Google/SMTP/browser/RTL/WCAG testing, backup/restore, rollback, Founder acceptance, live deployment and operations remain separate later gates.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
