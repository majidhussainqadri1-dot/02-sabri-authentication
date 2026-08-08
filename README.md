# File 02 — Authentication and Accounts

**Canonical repository name:** `02-sabri-authentication-and-accounts`  
**Current historical GitHub transport repository:** `02-sabri-authentication`  
**Package folder / WordPress slug:** `02-sabri-authentication` / `sabri-authentication`  
**Source candidate:** `1.2.0`  
**Governing corpus:** Definitive Master Plan v3.0, `SSH-F02-PLAN-2026-v1.0`, Consolidated All-Chats Directives v2.1, Continuous-Value/Top-20 Superset plan and the later File 00 Advanced Trust ownership boundary.

## Canonical ownership

File 02 owns email/password, Google OAuth and WebAuthn/passkey authentication ceremonies and surfaces, account-entry orchestration, linking/unlinking, recovery, session/device presentation, login-risk challenge and account-completion routing.

File 00 remains the sole owner of platform identity, membership eligibility, declared account class, age/guardian truth, roles/capabilities, verification, suspension, institutional authority and MFA policy. File 24 may contribute risk/assurance policy. Authentication never grants native object authorization.

## Version 1.2.0 completion scope

The candidate implements all prior File 02 requirements plus the later strong-authentication scope:

- email/password and secure Google-first registration with every approved completion field and consent bridge;
- signed one-time email verification, password authentication and recovery/reset;
- Google OAuth state, nonce, PKCE, issuer/audience/azp/time/email validation and explicit same-email link/unlink;
- WebAuthn/passkey registration, usernameless sign-in and revocation with required user verification, resident credentials, exact origin/RP binding and replay-safe challenges;
- server-side `attestationObject` CBOR parsing and COSE ES256/RS256 public-key extraction; browser-supplied public keys are never trusted;
- stable credential lookup across WordPress salt rotation, random opaque user handles, signature verification and counter-regression containment;
- fresh five-minute passkey assurance projected to File 00 as a versioned `owner=file02` claim without moving identity/MFA policy into File 02;
- conservative provenance: `attestation=none` never fabricates a hardware-backed assertion;
- opaque session/device registry, canonical `/account/sessions/`, individual/other/all-session revocation and generalized device/network presentation;
- suspicious-login risk challenge and File 00-owned step-up;
- privacy-minimized authentication/passkey events, privacy export/erasure, provider circuits, Safe Mode, System Check and guarded repair;
- File 01/File 20 manifests, migration/rollback/backup/incident documentation and deterministic packaging;
- canonical `SAUTH_` public naming with bounded legacy `SA_` compatibility.

## Required File 00 boundary

File 00 must provide `smc.authentication-account 1.1.0` and the existing assurance provider. The later Advanced Trust consumer reads the File 02 passkey projection through `smc_file02_authentication_assurance_v1` contract `1.0.0` and independently validates owner/version/freshness/revalidation before any elevation.

## Truthful status

| Gate | Status |
|---|---|
| Specified | Complete |
| Source coding | **Complete candidate**; current exact-head CI must pass |
| Packaged | Exact-head deterministic CI gate |
| Automated QA | Exact-head CI gate |
| Hostinger staging | Pending |
| Real WebAuthn/SMTP/Google/browser/RTL/WCAG/load | Pending |
| Backup/restore and rollback rehearsal | Pending |
| Founder acceptance | Pending |
| Live/Operational | Not claimed |

The owner-level repository rename and real-environment acceptance gates are external to source coding and must never be silently represented as completed.
