# File 02 — Four-Plan Plan-to-Code Traceability

**Governing sources used for this candidate**

1. Definitive Master Plan v3.0.
2. File 02 Complete Master Plan `SSH-F02-PLAN-2026-v1.0`, including its later central-plan completion appendix.
3. Consolidated All-Chats Directives v2.1.
4. Continuous-Value / Top-20 Superset plan, especially CV-005 Passkey/MFA, CV-006 Device/Session Center and CV-010 Account Recovery.
5. Later cross-file ownership refinement: File 02 owns password/Google/passkey authentication ceremony and authentication assurance; File 00 owns membership, identity, guardian, roles/capabilities and eligibility and consumes the versioned File 02 assurance claim.

**Candidate branch:** `fix/file02-passkey-index-reconciliation-1.2.6`
**Repository main re-verified during R329:** `0f011b1876e217b7ee46f92903e5315538c1025e`
**Candidate version/schema:** `1.2.6 / 1.2.1`; passkey schema `1.0.1`
**Paired File 00 account contract:** `smc.authentication-account 1.1.0`
**Authentication-assurance producer:** `smc_file02_authentication_assurance_v1` / `1.0.0`

## Ownership and constitution

| Constitution item | Resolution |
|---|---|
| Intended canonical repository | `02-sabri-authentication-and-accounts`; owner-level rename remains external |
| Current transport repository | `02-sabri-authentication` |
| Package folder | `02-sabri-authentication` |
| WordPress slug/text domain | `sabri-authentication` |
| Public PHP prefix | `SAUTH_`; legacy `SA_` identifiers are bounded compatibility aliases |
| Canonical session route | `/account/sessions/`; legacy route redirects permanently |
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

| Requirement | Version 1.2.x source evidence | Status |
|---|---|---|
| F02-FR-001 Account registration | mandatory fields, password/Google methods, idempotency and File 00 provider | Implemented |
| F02-FR-002 Email verification | signed one-time token, canonical email, expiry, resend/attempt controls, atomic replay protection and audit | Implemented |
| F02-FR-003 Password authentication | WordPress hashing APIs, dummy verification, generic errors, rate controls and File 00 rechecks | Implemented |
| F02-FR-004 Google OAuth | state, nonce, PKCE, issuer/audience/azp/time/email validation for login and registration | Implemented |
| F02-FR-005 Linking/unlinking | exact-email, current session, reauthentication, duplicate lock, unlink and session revocation | Implemented |
| F02-FR-006 Password recovery | non-enumerating initiation, one-time key, strength check and all-session revocation | Implemented |
| F02-FR-007 Sessions | HMAC-only bindings, opaque IDs, current marker, generalized presentation and scoped revoke operations | Implemented |
| F02-FR-008 Login risk | new device/network/failure/provider state; elevated password risk requires a separate File 02 passkey sign-in | Implemented |
| F02-FR-009 Completion | File 00 state including city/type/ethics/photo, same-origin route and loop prevention | Implemented |
| F02-FR-010 Redirect safety | same-origin allowlist and canonical route validation | Implemented |
| F02-FR-011 Audit events | versioned outbox, trace IDs, retries/dead-letter, secret stripping and authentication facts | Implemented |
| F02-FR-012 Degraded UX | provider circuits, Safe Mode, bounded HTTP, explicit failure states and public-read preservation | Implemented |

## Later Continuous-Value / Advanced Trust additions

| ID | Requirement | Source implementation | Status |
|---|---|---|---|
| CV-005 | Passkey/WebAuthn strong login for high-trust actors, recovery and step-up boundary | `SAUTH_Passkeys`, hardened runtime, WebAuthn create/get, File 02 assurance producer | **Implemented at source level** |
| CV-005-A | File 02 owns passkey/WebAuthn ceremony; File 00 consumes assurance | `smc_file02_authentication_assurance_v1`, `owner=file02`, no File 00 private factor metadata | Implemented |
| CV-005-B | RP ID/origin/challenge/user-verification binding | server `clientDataJSON`, authenticatorData validation, `userVerification=required` | Implemented |
| CV-005-C | Registration public key must be authenticator-derived | server parses attestationObject CBOR and COSE EC2/RSA key; client public key rejected by architecture guard | Implemented |
| CV-005-D | Replay/concurrency resistance | transient challenge plus atomic unique option claim, expiry and fingerprint binding | Implemented |
| CV-005-E | Credential lifecycle | add/list/revoke/compromised states, privacy export/erasure, bounded cleanup | Implemented |
| CV-005-F | Strong assurance without false provenance | five-minute session-bound level-3 claim; `hardware_backed=false` under attestation=none | Implemented |
| CV-006 | Device/session center | `/account/sessions/`, active sessions, generalized device/network, revoke one/others/all | Implemented |
| CV-010 | Account recovery | non-enumerating password recovery/reset, throttling, all-session revocation and support-safe copy | Implemented |

## Passkey migration hardening

R334 corrected dbDelta-incompatible combined key definitions by placing every passkey index definition on its own CREATE TABLE line. R335 then used direct MariaDB schema evidence to prove and correct the stale-index-name condition where unique key `credential_lookup_hash` remains bound to renamed legacy column `credential_hash`. The migration recognizes only that exact state, preserves legacy uniqueness, frees the canonical key name, fails closed on unexpected conflicts, and leaves DB identity `1.2.1` / passkey schema `1.0.1` unchanged because the intended final schema is unchanged.

## Cross-file release boundary

R331 aligned File 02 public account choices to the File 00 canonical taxonomy: `member`, `patient`, `student`, `doctor`, `teacher`, `researcher`, `pharmacy`, `clinic`, and `publisher`, with no aliases or lossy remap. File 00 runtime `1.2.44` corrected its `smc.authentication-account 1.1.0` provider to derive from the same canonical taxonomy.

The former taxonomy/provider cross-file release blocker is now **closed at repository/integration level** by exact WordPress 7.0 / MariaDB 11.4 run `31850253635`, pairing File 02 `f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9` with File 00 `1d7f215193d778b0977c8e50d738c42e1e5f66c2`. That run also proved File 00 deferred bootstrap, File 02 fresh activation, legacy passkey column/index upgrade, legacy logical-identity collision migration and final paired runtime/schema boundaries.

## Completion truth

This traceability file describes repository/source and exact repository-integration evidence only. It does not establish the final package, Hostinger staging, live deployment or operational state. Those gates require their own exact evidence and may not be inferred from source/integration success.
