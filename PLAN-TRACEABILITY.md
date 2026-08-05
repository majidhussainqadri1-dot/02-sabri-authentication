# File 02 — Plan-to-Code Traceability

**Governing specification:** `SSH-F02-PLAN-2026-v1.0`  
**Candidate branch:** `codex/file02-full-plan-harmonization-0.4.0`  
**Candidate version:** `0.4.0`  
**Status:** implementation in progress; not staging-accepted, live-deployed or operational.

## Governing ownership

File 02 owns authentication surfaces and orchestration for email/password, Google OAuth, recovery, session-entry presentation and account completion. File 00 remains the sole owner of platform identity, membership eligibility, roles, guardian state, verification, institutional authority and MFA policy.

## Functional requirements

| Requirement | Current 0.4.0 evidence | Status |
|---|---|---|
| F02-FR-001 Account registration | Full File 02 registration surface validates name, email, phone, password, sex, date of birth, address, country, identity reference, guardian reference and consent versions; `SAUTH_Account_Contract::register_account()` performs the fail-closed versioned File 00 handoff with rate limits and idempotency. File 02 does not create a parallel user or role. | Implemented in source; accepted File 00 provider and staging pending |
| F02-FR-002 Email verification | `SAUTH_Email_Verification` issues a 30-minute one-time token, persists only HMAC hashes, binds delivery to the canonical account email, throttles resend/attempts, requires explicit confirmation, performs idempotent File 00 completion, closes concurrent replay and emits `EmailVerified.v1`. | Implemented in source; provider/staging acceptance pending |
| F02-FR-003 Password authentication | File 02 login surface uses WordPress password APIs, a dummy hash for unknown accounts, generic errors, honeypot and per-IP/per-account limits; it rechecks File 00 membership/completion before creating the WordPress session and emits success/failure events. | Implemented in source; risk-device and staging acceptance pending |
| F02-FR-004 Google OAuth | State, nonce, PKCE, issuer/audience/azp/time checks, explicit same-email linking and step-up are present from 0.3.0. | Implemented in source; staging pending |
| F02-FR-005 Account linking | Explicit link/unlink, collision checks, concurrency lock, File 00 step-up and session revocation are present. | Implemented in source; staging pending |
| F02-FR-006 Password recovery | Non-enumerating request and canonical one-time reset completion with password validation, all-session revocation and event/audit record are present. File 19/provider delivery integration remains pending. | Partially implemented |
| F02-FR-007 Session management | Private session summary, current marker, generalized device/network projection, revoke-all-other and sign-out-everywhere controls are present. Per-session arbitrary revocation requires a safe opaque-token registry and remains pending. | Partially implemented |
| F02-FR-008 Login risk challenge | File 00 step-up exists for Google operations. Password login is rate-limited and fail-closed, but new-device/location anomaly policy is pending. | Partially implemented |
| F02-FR-009 Account completion | Fail-closed versioned `get_completion_state` provider boundary is consumed after password authentication and routes incomplete accounts to the owner-provided same-origin destination. Formal loop-state tests remain pending. | Partially implemented |
| F02-FR-010 Safe redirects | Same-origin validation exists in `SA_Security::safe_redirect()` and is used on authentication routes and completion destinations. | Implemented in source; staging pending |
| F02-FR-011 Auth audit events | Privacy-minimized reliable outbox, retry/dead-letter state and versioned events cover password success/failure, email verification, password reset, Google link/unlink and session revocation. Remaining operational inspection/reconciliation is pending. | Implemented in current source scope |
| F02-FR-012 Degraded provider UX | File 00 account-contract, membership and Google dependency failures fail closed with explicit retry guidance; public reading is not blocked. Provider circuit-breaker metrics remain pending. | Partially implemented |

## Non-functional requirements

| Requirement | Current evidence | Status |
|---|---|---|
| F02-NFR-001 Authorization | File 00 remains mandatory; registration writes only through its contract; session controls are own-user only; action nonces are present. Full object/field/IDOR matrix pending. | In progress |
| F02-NFR-002 Privacy lifecycle | Google/legacy export and erasure, email-challenge export/erasure, event secret stripping, canonical-email binding and generalized session presentation are present. Retention/legal-hold reconciliation remains pending. | In progress |
| F02-NFR-003 Reliability | Atomic rate limits; versioned outbox with retries/dead-letter; fail-closed provider boundary; registration idempotency; email replay/concurrency guard. Reconciliation/admin inspection pending. | In progress |
| F02-NFR-004 Performance | Bounded outbox batch and rate-limit queries. Measured p75/p95 and provider circuit breakers pending. | Pending evidence |
| F02-NFR-005 Accessibility | Explicit labels, native validation, focus-visible styles, minimum control heights, logical CSS properties, responsive one-column form and reduced-motion handling are present. Full WCAG/RTL/device acceptance pending. | In progress; human evidence pending |
| F02-NFR-006 Observability | Trace IDs, canonical audit calls and auditable outbox introduced. Metrics/alerts/dashboard pending. | In progress |
| F02-NFR-007 Migration/rollback | Additive idempotent rate-limit, outbox and email-verification schema paths exist. Full fresh/upgrade/concurrency/rollback suite pending. | Pending evidence |
| F02-NFR-008 Operability | Activation diagnostics and safe degraded surfaces exist. System Check, outbox inspection and guarded repair pending. | Pending |
| F02-NFR-009 Compatibility | Exact-head PHP 7.4/8.3 lint and no-network suites are configured. Hostinger/WordPress 7.0.1 re-verification pending. | Automated scope implemented; staging evidence pending |
| F02-NFR-010 Localization | Text-domain baseline and RTL-capable logical CSS exist. Full American English/Urdu/Arabic/date-time translation acceptance pending. | Pending evidence |

## Completed harmonization batches

1. Frozen File 02/File 00 ownership through versioned fail-closed consumer boundaries.
2. Introduced privacy-safe authentication event outbox with retry and dead-letter states.
3. Added canonical password reset completion and all-session revocation.
4. Added authenticated session presentation and safe bulk revocation.
5. Implemented the full File 02 registration surface and File 00 transaction handoff.
6. Implemented signed, one-time, resend-throttled and concurrency-safe email verification.
7. Implemented native password authentication with constant-time WordPress checks, generic errors and brute-force controls.
8. Applied green primary identity, logical responsive CSS, focus treatment and reduced-motion handling.
9. Added architecture, policy and negative-path test coverage for F02-FR-001 through F02-FR-003.

## Mandatory remaining gates

- complete F02-FR-007 individual-session revocation, F02-FR-008 risk challenge and F02-FR-009 loop-safe completion hardening;
- accepted File 00 account-orchestration provider contract in its canonical repository;
- File 01 route registry and File 20 placement contract;
- complete provider event delivery, circuit breakers, System Check, metrics, queue inspection and guarded repair;
- two fresh review/fix rounds after the final coding change;
- deterministic ZIP, manifest, checksums, SBOM and source/package parity;
- fresh install, supported upgrades, migration concurrency and rollback;
- security/privacy/authorization/accessibility/RTL/browser/load/restore suites;
- Hostinger staging with real File 00, Google and email providers;
- Founder acceptance before production deployment.
