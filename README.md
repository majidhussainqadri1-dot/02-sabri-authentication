# File 02 — Authentication and Accounts

**Canonical repository name:** `02-sabri-authentication-and-accounts`  
**Current historical GitHub transport repository:** `02-sabri-authentication`  
**Current review branch:** `review/file02-r337-fresh-audit-2026-08-16`  
**Package folder / WordPress slug:** `02-sabri-authentication` / `sabri-authentication`  
**Source candidate:** `1.2.6`  
**Database schema:** `1.2.1`  
**Passkey schema:** `1.0.1`  
**Governing corpus:** Definitive Master Plan v3.0, `SSH-F02-PLAN-2026-v1.0`, and later approved File 02/File 00 ownership refinements.

## Canonical ownership

File 02 owns email/password, Google OAuth and WebAuthn/passkey authentication ceremonies and surfaces, account-entry orchestration, linking/unlinking, recovery, session/device presentation, login-risk challenge and account-completion routing.

File 00 remains the sole owner of platform identity, membership eligibility, declared account class, age/guardian truth, roles/capabilities, verification, suspension, institutional authority and MFA policy. File 24 may contribute risk/assurance policy. Authentication never grants native object authorization.

## Current R337 source scope

R337 is a fresh review/fix cycle after the R331–R336 line. The complete R337 review was frozen before correction at exact HEAD `972f5fd2cc59fe69bf465b844ac36c740533f7dd`. The frozen ledger is `review-evidence/R337-REVIEW-FROZEN.md`.

R337 found seven verified defects: four High and three Medium. The corrective source now:

- requires File 00 runtime `1.2.44+`, the first reviewed current provider candidate that accepts the canonical account taxonomy;
- aligns the WordPress minimum to `6.4+`, matching the mandatory File 00 dependency;
- applies adult-only local prevalidation to canonical professional/institutional declarations: doctor, teacher, researcher, pharmacy, clinic and publisher;
- proves passkey credential state persistence before any assurance/session success;
- fails the synchronous authentication request closed when File 02 session-registry persistence cannot be proved;
- preserves WordPress `confirm_admin_email` instead of redirecting that WordPress-owned administrative ceremony into File 02 login; and
- aligns release/status evidence to the R337 review branch without advancing package, staging, live or operational claims.

The exact multi-file corrective commit is `e04cfdf51a6d876f70c0296acfb9692fef5a54df`; later commits add regression/evidence alignment, so final QA must always be attributed to the final immutable branch HEAD rather than that intermediate commit.

The candidate also retains the earlier hardening for email verification, password recovery, Google OIDC, WebAuthn/passkeys, risk gating, session controls, privacy export/erasure, provider circuits, Safe Mode, guarded repair, canonical routes, additive migrations and deterministic-release infrastructure.

## Required File 00 boundary

File 00 must be runtime `1.2.44+` and provide `smc.authentication-account 1.1.0+` plus the current membership-assurance provider. The Advanced Trust consumer may read the File 02 passkey projection through `smc_file02_authentication_assurance_v1` contract `1.0.0` and must independently validate owner/version/freshness/revalidation before elevation.

The older paired repository integration run `31850253635` used File 02 `f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9` with File 00 `1d7f215193d778b0977c8e50d738c42e1e5f66c2` / runtime `1.2.44`. It remains **historical pre-R337** evidence only. Because R337 changes File 02 source after that run, exact post-R337 paired revalidation is required before cross-file integration success can be attributed to the current candidate.

## Truthful status

| Gate | Status |
|---|---|
| Specified | Complete |
| Source coding | **R337 corrective candidate**; frozen review ledger and corrections present |
| Packaged | Pending final exact-head package proof |
| Automated QA | Pending final exact-head green proof |
| Post-R337 File 00 paired integration | Pending |
| Hostinger staging | Pending |
| Real WebAuthn/SMTP/Google/browser/RTL/WCAG/load | Pending |
| Backup/restore and rollback rehearsal | Pending |
| Founder acceptance | Pending |
| Live/Operational | Not claimed |

Real-environment acceptance gates are external to source coding and must never be silently represented as completed.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
