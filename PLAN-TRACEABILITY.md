# File 02 — Four-Plan Plan-to-Code Traceability

**Governing sources used for this candidate**

1. Definitive Master Plan v3.0.
2. File 02 Complete Master Plan `SSH-F02-PLAN-2026-v1.0`, including its later central-plan completion appendix.
3. Consolidated All-Chats Directives v2.1.
4. Continuous-Value / Top-20 Superset plan, especially CV-005 Passkey/MFA, CV-006 Device/Session Center and CV-010 Account Recovery.
5. Later File 00 Advanced Trust boundary is a cross-file ownership refinement: File 02 owns authentication/login/passkey/WebAuthn ceremony; File 00 owns membership/identity/MFA policy and consumes a versioned File 02 assurance claim.

**Candidate branch:** `review/file02-r291-r300-main-2026-08-14`
**Candidate version/schema:** `1.2.1 / 1.2.0`; passkey schema `1.0.0`
**Paired File 00 account contract:** `smc.authentication-account 1.1.0`
**Advanced Trust producer:** `smc_file02_authentication_assurance_v1` / `1.0.0`

## Ownership and constitution

| Constitution item | Resolution |
|---|---|
| Canonical repository | `02-sabri-authentication-and-accounts`; owner-level rename remains external |
| Package folder | `02-sabri-authentication` |
| WordPress slug/text domain | `sabri-authentication` |
| Public PHP prefix | `SAUTH_`; legacy `SA_` identifiers are bounded compatibility aliases |
| Canonical session route | `/account/sessions/`; legacy route redirects permanently |
| Identity/roles/guardian/verification/MFA policy owner | File 00 only |
| Password/Google/passkey authentication ceremony owner | File 02 only |
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

| Requirement | Version 1.2.0 source evidence | Status |
|---|---|---|
| F02-FR-001 Account registration | mandatory fields, password/Google methods, idempotency and File 00 provider | Implemented |
| F02-FR-002 Email verification | signed one-time token, canonical email, expiry, resend/attempt controls, atomic replay protection and audit | Implemented |
| F02-FR-003 Password authentication | WordPress hashing APIs, dummy verification, generic errors, rate controls and File 00 rechecks | Implemented |
| F02-FR-004 Google OAuth | state, nonce, PKCE, issuer/audience/azp/time/email validation for login and registration | Implemented |
| F02-FR-005 Linking/unlinking | exact-email, current session, step-up, duplicate lock, unlink and session revocation | Implemented |
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
| CV-005 | Passkey/MFA strong login for high-trust actors, recovery and step-up boundary | `SAUTH_Passkeys`, login button, WebAuthn create/get, File 00 assurance filter | **Implemented at source level** |
| CV-005-A | File 02 owns passkey/WebAuthn ceremony; File 00 owns policy | `smc_file02_authentication_assurance_v1`, `owner=file02`, no File 00 private metadata | Implemented |
| CV-005-B | RP ID/origin/challenge/user-verification binding | server `clientDataJSON`, authenticatorData validation, `userVerification=required` | Implemented |
| CV-005-C | Registration public key must be authenticator-derived | server parses attestationObject CBOR and COSE EC2/RSA key; client public key rejected by architecture guard | Implemented |
| CV-005-D | Replay/concurrency resistance | transient challenge plus atomic unique option claim, expiry and fingerprint binding | Implemented |
| CV-005-E | Credential lifecycle | add/list/revoke/compromised states, privacy export/erasure, bounded cleanup | Implemented |
| CV-005-F | Strong assurance without false provenance | five-minute session-bound level-3 claim; `hardware_backed=false` under attestation=none | Implemented |
| CV-006 | Device/session center | `/account/sessions/`, active sessions, generalized device/network, revoke one/others/all | Implemented |
| CV-010 | Account recovery | non-enumerating password recovery/reset, throttling, all-session revocation and support-safe copy | Implemented |

## Passkey security trace

| Control | Implementation / evidence |
|---|---|
| Private key / biometric data | never received or stored by server |
| Attestation privacy | `attestation=none`; attestation object parsed then discarded |
| Allowed key algorithms | ES256 (`-7`) and RS256 (`-257`) only |
| Key trust | COSE public key parsed from authenticatorData; browser `getPublicKey()` not accepted |
| User presence + verification | UP and UV flags required |
| Discoverable credential | resident key required; usernameless authentication supported |
| Origin | exact canonical HTTPS origin; cross-origin clientData rejected |
| RP ID | SHA-256 RP ID hash compared to authenticatorData |
| Challenge | 32 random bytes, five-minute TTL, fingerprint-bound, atomic single-use claim |
| Credential collision | unique stable SHA-256 credential ID index |
| Salt rotation | lookup does not depend on WordPress salts; encrypted presentation copy may fail closed without breaking lookup |
| User handle | random opaque File 02 handle, independent of WordPress salts, erased with passkey data |
| Signature | OpenSSL SHA-256 verify against server-derived SPKI public key |
| Signature counter | non-zero regression marks credential compromised; zero counters permitted for synchronized passkeys |
| Management reauth | fresh File 02 passkey assurance, otherwise current password; retired File 00 factor codes are never solicited or accepted |
| Assurance | current-user + current-session + fingerprint + five-minute receipt; File 00 revalidates freshness/owner/version |
| Hardware-backed claim | intentionally false under attestation=none; no invented provenance |

## Non-functional requirements

| Requirement | Source resolution | Evidence boundary |
|---|---|---|
| F02-NFR-001 Authorization | File 00 mandatory; native object/state checks; passkey authentication never grants domain authorization | Real-role IDOR staging pending |
| F02-NFR-002 Privacy | minimization, encrypted credential-ID presentation, opaque handles, export/erasure/anonymization | Provider/deletion staging pending |
| F02-NFR-003 Reliability | idempotency, atomic challenge claim, retries/dead-letter, circuit breakers | Failure injection staging pending |
| F02-NFR-004 Performance | bounded credential list, bounded CBOR parser, provider timeouts and route-scoped assets | Measured p75/p95 pending |
| F02-NFR-005 Accessibility | semantic controls, status live regions, keyboard-capable browser WebAuthn, logical CSS | Human WCAG/RTL/browser pending |
| F02-NFR-006 Observability | traces, audits, provider state, authentication events, System Check | Implemented; staging alert acceptance pending |
| F02-NFR-007 Migration/rollback | additive File 02/passkey schema, option versioning, non-destructive default uninstall | Real upgrade/rollback pending |
| F02-NFR-008 Operability | Safe Mode, health report, guarded repair baseline and passkey fail-closed behavior | Staging drill pending |
| F02-NFR-009 Compatibility | PHP 7.4/8.3 CI, WordPress APIs, browser WebAuthn feature detection, deterministic package | Hostinger/real authenticators pending |
| F02-NFR-010 Localization | US-English base, RTL-safe account surfaces, localized status/error layer | Linguistic/visual QA pending |

## Consolidated directives

| Directive | Resolution |
|---|---|
| Green primary identity | Authentication CSS retains green identity |
| Islamic privacy and dignity | minimization, no covert device/location tracking, no raw secrets/full IP, explicit consent boundaries |
| One canonical owner | File 02 ceremony vs File 00 identity/MFA policy vs File 24 assurance vs File 20 shell are explicit |
| Fresh review → fix → retest | current cycle records security redesigns and regression tests; exact-head CI remains final automated gate |
| Zero known defect release gate | applies to the evidence scope only; new evidence reopens review |
| Observable progress | branch, exact head, workflows, artifacts, checksums and external gates reported |
| Responsive/RTL/accessibility | source baseline present; real acceptance remains separate |
| Staging first | no live authorization in repository |

## External evidence gates

- owner-level repository rename;
- current exact-head GitHub Actions success and retained 1.2.0 artifact;
- Hostinger fresh install/upgrade/deactivate/reactivate/uninstall;
- real production-domain WebAuthn authenticators, SMTP, Google and File 00 Advanced Trust integration;
- File 01/File 20/File 03/File 24/theme/LiteSpeed integration;
- real roles, IDOR, privacy, challenge replay/race and privilege-loss tests;
- mobile/browser/RTL/WCAG/performance/load;
- backup/restore and rollback rehearsal;
- Founder staging approval, production deployment and operational monitoring.

These external gates do not invalidate completed source coding, but they prohibit a claim that the system is staging-accepted, live or operational before independent evidence exists.
