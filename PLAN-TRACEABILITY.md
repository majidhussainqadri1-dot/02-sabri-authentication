# File 02 — Four-Plan Plan-to-Code Traceability

## R338 governing amendment reconciliation

Founder-approved amendment `SSH-F02-AMD-2026-08-08-X24` permanently adds F02-X24-001..024. Its source architecture requires `SAUTH_Modern_Auth`, `SAUTH_Security_Orchestrator`, `SAUTH_Shared_Signals`, `SAUTH_Password_Safety`, `SAUTH_DPoP`, `SAUTH_FIDO_Trust`; DB `1.3.0` with `sauth_security_timeline`, `sauth_recovery_changes`, `sauth_shared_signals`; passkey schema `1.1.0`; `/account-security/` and `/resolve-account/`; Authentication Assurance v2 `2.0.0`, Modern Auth `1.0.0`, Shared Signals `1.0.0`. The current recoverable source lacks this complete layer and is therefore release-blocked. The later reviewed corpus records a `1.3.8 / DB 1.3.0 / passkey 1.1.0` source lineage whose exact bytes are not recovered here. No missing X24 implementation is inferred from branch names or recreated and called recovered evidence.

**Governing sources used for this candidate**

1. Definitive Master Plan v3.0.
2. File 02 Complete Master Plan `SSH-F02-PLAN-2026-v1.0`, including its later central-plan completion appendix.
3. Founder-approved amendment `SSH-F02-AMD-2026-08-08-X24`.
4. Later reviewed File 02 v2.9 evidence lineage recording runtime `1.3.8 / DB 1.3.0 / passkey 1.1.0`.
5. Consolidated All-Chats Directives v2.1.
6. Continuous-Value / Top-20 Superset plan, especially CV-005 Passkey/MFA, CV-006 Device/Session Center and CV-010 Account Recovery.
7. Later cross-file ownership refinement: File 02 owns password/Google/passkey authentication ceremony and authentication assurance; File 00 owns membership, identity, guardian, roles/capabilities and eligibility and consumes the versioned File 02 assurance claim.

**Candidate branch:** `review/file02-r338-fresh-review-fix-2026-08-16`
**Repository main re-verified during R329:** `0f011b1876e217b7ee46f92903e5315538c1025e`
**Current recoverable version/schema:** `1.3.0 / 1.3.0`; passkey schema `1.0.1`
**Approved later reviewed lineage:** `1.3.8 / 1.3.0`; passkey schema `1.1.0` — exact source bytes currently unrecovered
**Paired File 00 account contract:** `smc.authentication-account 1.1.0`
**Minimum reviewed File 00 runtime:** `1.2.44`
**Authentication-assurance producer in current recoverable tree:** `smc_file02_authentication_assurance_v1` / `1.0.0`

## Ownership and constitution

| Constitution item | Resolution |
|---|---|
| Intended canonical repository | `02-sabri-authentication-and-accounts`; owner-level rename remains external |
| Current transport repository | `02-sabri-authentication` |
| Package folder | `02-sabri-authentication` |
| WordPress slug/text domain | `sabri-authentication` |
| Public PHP prefix | `SAUTH_`; legacy `SA_` identifiers are bounded compatibility aliases |
| Canonical session route | `/account/sessions/`; legacy route redirects permanently |
| Approved X24 account-security route | `/account-security/` — missing in current recoverable tree; release blocker |
| Approved X24 collision-resolution route | `/resolve-account/` — missing in current recoverable tree; release blocker |
| Identity/roles/guardian/verification/eligibility owner | File 00 only |
| Password/Google/passkey authentication ceremony and factor evidence owner | File 02 only |
| Security/risk assurance | File 24 may raise requirements; native File 02/File 00 enforcement is not replaced |
| Shell/layout owner | File 20 only; File 02 supplies manifests and semantic content |
| Profile photograph owner | File 03; File 00 supplies completion truth and route |

## Parent-plan mandatory registration fields

| Field/control | Source evidence | Owner |
|---|---|---|
| Complete name | `templates/signup.php`, `SA_Registration` | File 00 truth |
| Email | password or Google-verified email | File 00/WordPress identity |
| Mobile/phone | registration payload plus File 00 completion | File 00 verification |
| Country and city | required fields and File 00 account contract | File 00 |
| Full address | validated/encrypted by File 00 | File 00 |
| Date of birth and sex | age validation plus File 00 eligibility | File 00 |
| Declared account type | required; never grants privileges | File 00 |
| Password or Google | separate complete registration paths | File 02 |
| Profile photograph | required completion acknowledgement and File 03 hook | File 03/File 00 completion |
| National ID/Passport | explicit type/reference handoff | File 00 |
| Guardian reference | required under governing minor policy | File 00 |
| Terms / Privacy / Ethical Conduct | separate versioned consents | File 00 ledger; File 02 bridge |

## Original functional requirements

| Requirement | Current recoverable source evidence | Status |
|---|---|---|
| F02-FR-001 Account registration | mandatory fields, password/Google methods, idempotency and File 00 provider | Implemented in recoverable tree |
| F02-FR-002 Email verification | signed one-time token, canonical email, expiry, resend/attempt controls, atomic replay protection and audit | Implemented in recoverable tree |
| F02-FR-003 Password authentication | WordPress hashing APIs, dummy verification, generic errors, rate controls and File 00 rechecks | Implemented in recoverable tree |
| F02-FR-004 Google OAuth | state, nonce, PKCE, issuer/audience/azp/time/email validation for login and registration | Implemented in recoverable tree |
| F02-FR-005 Linking/unlinking | exact-email, current session, reauthentication, duplicate lock, unlink and session revocation | Implemented in recoverable tree |
| F02-FR-006 Password recovery | non-enumerating initiation, one-time key, strength check and all-session revocation | Implemented in recoverable tree |
| F02-FR-007 Sessions | HMAC-only bindings, opaque IDs, current marker, generalized presentation and scoped revoke operations | Implemented in recoverable tree |
| F02-FR-008 Login risk | new device/network/failure/provider state; elevated password risk requires a separate File 02 passkey sign-in | Implemented in recoverable tree |
| F02-FR-009 Completion | File 00 state including city/type/ethics/photo, same-origin route and loop prevention | Implemented in recoverable tree |
| F02-FR-010 Redirect safety | same-origin allowlist and canonical route validation | Implemented in recoverable tree |
| F02-FR-011 Audit events | versioned outbox, trace IDs, retries/dead-letter, secret stripping and authentication facts | Implemented in recoverable tree |
| F02-FR-012 Degraded UX | provider circuits, Safe Mode, bounded HTTP, explicit failure states and public-read preservation | Implemented in recoverable tree |

## Continuous-Value / Advanced Trust additions retained in the recoverable line

| ID | Requirement | Current source implementation | Status |
|---|---|---|---|
| CV-005 | Passkey/WebAuthn strong login for high-trust actors, recovery and step-up boundary | `SAUTH_Passkeys`, hardened runtime, WebAuthn create/get, File 02 assurance producer | Implemented at recoverable-source level |
| CV-005-A | File 02 owns passkey/WebAuthn ceremony; File 00 consumes assurance | `smc_file02_authentication_assurance_v1`, `owner=file02`, no File 00 private factor metadata | Implemented |
| CV-005-B | RP ID/origin/challenge/user-verification binding | server `clientDataJSON`, authenticatorData validation, `userVerification=required` | Implemented |
| CV-005-C | Registration public key must be authenticator-derived | server parses attestationObject CBOR and COSE EC2/RSA key; client public key rejected by architecture guard | Implemented |
| CV-005-D | Replay/concurrency resistance | transient challenge plus atomic unique option claim, expiry and fingerprint binding | Implemented |
| CV-005-E | Credential lifecycle | add/list/revoke/compromised states, privacy export/erasure, bounded cleanup | Implemented |
| CV-005-F | Strong assurance without false provenance | session-bound claim; `hardware_backed=false` under attestation=none | Implemented in recoverable line |
| CV-006 | Device/session center | `/account/sessions/`, active sessions, generalized device/network, revoke one/others/all | Implemented |
| CV-010 | Account recovery | non-enumerating password recovery/reset, throttling, all-session revocation and support-safe copy | Implemented |

## Founder-approved X24 amendment status

The current recoverable tree does **not** contain the complete F02-X24-001..024 implementation. In particular, the six approved owner classes (`SAUTH_Modern_Auth`, `SAUTH_Security_Orchestrator`, `SAUTH_Shared_Signals`, `SAUTH_Password_Safety`, `SAUTH_DPoP`, `SAUTH_FIDO_Trust`), the three X24 tables, passkey schema `1.1.0`, the approved account-security/collision-resolution routes and Authentication Assurance v2 / Modern Auth / Shared Signals contracts are not present as a complete implementation. This is an explicit release blocker, not an invitation to infer those features from older branches or documentation.

## Migration and provider hardening retained from R331–R337

The recoverable hardening line contains the dbDelta/passkey-index reconciliation, canonical File 00 account taxonomy, provider locking, session/risk postconditions, email verification concurrency protection, Google linkage containment, passkey counter/backup eligibility hardening and privacy migration corrections from the later R331–R337 source-review work. These corrections remain valuable, but they do not substitute for the missing approved X24 layer.

## R338 source-lineage boundary

`SOURCE-LINEAGE-LOCK.json` is machine-authoritative for this review branch. It records that exact reviewed `1.3.8` source bytes are currently unrecovered, current X24 scope is incomplete, and `packaging_allowed=false`, `staging_allowed=false`, `deployment_allowed=false`. The current branch may undergo source review and corrective hardening only; it must not be represented as the latest approved File 02 product source or as an installable production-complete candidate.

## Completion truth

This traceability file describes current recoverable source evidence and known gaps only. Approved-scope source completeness, Packaged, Automated-QA release readiness, Staging-Accepted, Live-Deployed and Operational are separate gates. The X24/source-lineage blocker must be closed before package/staging/deployment authorization.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
