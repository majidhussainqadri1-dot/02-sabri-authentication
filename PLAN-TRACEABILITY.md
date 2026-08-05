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
| F02-FR-001 Account registration | `SAUTH_Account_Contract::register_account()` creates a fail-closed versioned File 00 boundary. Full File 02 registration form, email challenge and accepted File 00 provider are pending. | In progress |
| F02-FR-002 Email verification | Provider method and `EmailVerified.v1` outbox schema are defined. Signed challenge issuance/consumption is pending. | In progress |
| F02-FR-003 Password authentication | Existing login route remains compatibility-delegated. Native File 02 password authentication and risk flow are pending. | Pending |
| F02-FR-004 Google OAuth | State, nonce, PKCE, issuer/audience/azp/time checks, explicit same-email linking and step-up are present from 0.3.0. | Implemented in source; staging pending |
| F02-FR-005 Account linking | Explicit link/unlink, collision checks, concurrency lock, File 00 step-up and session revocation are present. | Implemented in source; staging pending |
| F02-FR-006 Password recovery | Non-enumerating request and canonical one-time reset completion with password validation, all-session revocation and event/audit record are present. Custom canonical email-link issuance remains pending. | Partially implemented |
| F02-FR-007 Session management | Private session summary, current marker, generalized device/network projection, revoke-all-other and sign-out-everywhere controls are present. Per-session arbitrary revocation requires a safe opaque-token registry and remains pending. | Partially implemented |
| F02-FR-008 Login risk challenge | File 00 step-up exists for Google operations. New-device/location/password anomaly policy is pending. | Partially implemented |
| F02-FR-009 Account completion | Fail-closed versioned `get_completion_state` provider boundary is present. Redirect resolver and loop tests are pending. | In progress |
| F02-FR-010 Safe redirects | Same-origin validation exists in `SA_Security::safe_redirect()` and is used on authentication routes. | Implemented in source; staging pending |
| F02-FR-011 Auth audit events | Privacy-minimized reliable outbox, retry/dead-letter state and versioned event allowlist are present. Integration across every success/failure path is pending. | In progress |
| F02-FR-012 Degraded provider UX | File 00 and Google dependency failures fail closed; full email/Google circuit-breaker status and retry guidance remain pending. | Partially implemented |

## Non-functional requirements

| Requirement | Current evidence | Status |
|---|---|---|
| F02-NFR-001 Authorization | File 00 remains mandatory; session controls are own-user only; action nonces are present. Full object/field/IDOR matrix pending. | In progress |
| F02-NFR-002 Privacy lifecycle | Google/legacy export and erasure exist; event payload secret stripping and generalized session presentation added. Retention/legal-hold matrix pending. | In progress |
| F02-NFR-003 Reliability | Atomic rate limits; versioned outbox with retries/dead-letter; fail-closed provider boundary. Reconciliation/admin inspection pending. | In progress |
| F02-NFR-004 Performance | Bounded outbox batch and rate-limit queries. Measured p75/p95 and provider circuit breakers pending. | Pending evidence |
| F02-NFR-005 Accessibility | Semantic account surfaces and no-cache/noindex controls exist. Full WCAG/RTL/device acceptance pending. | Pending evidence |
| F02-NFR-006 Observability | Trace IDs and auditable outbox introduced. Metrics/alerts/dashboard pending. | In progress |
| F02-NFR-007 Migration/rollback | Additive idempotent schema path exists. Full fresh/upgrade/concurrency/rollback suite pending. | Pending evidence |
| F02-NFR-008 Operability | Activation diagnostics exist. System Check, outbox inspection and guarded repair pending. | Pending |
| F02-NFR-009 Compatibility | Source targets PHP 7.4/8.3 and WordPress APIs. Hostinger/WordPress 7.0.1 re-verification pending. | Pending evidence |
| F02-NFR-010 Localization | Text-domain surfaces exist. Full American English/Urdu/Arabic/RTL/date-time acceptance pending. | Pending evidence |

## Current batch scope

1. Freeze File 02/File 00 ownership through a versioned account-orchestration consumer boundary.
2. Introduce privacy-safe authentication event outbox with retry and dead-letter states.
3. Add canonical password reset completion and all-session revocation.
4. Add authenticated session presentation and safe bulk revocation.
5. Align plugin/runtime/database version to `0.4.0` and update CI evidence.

## Mandatory remaining gates

- complete F02-FR-001 through F02-FR-012 without orphan Must requirements;
- accepted File 00 account-orchestration provider contract;
- File 01 route registry and File 20 placement contract;
- two fresh review/fix rounds after final code change;
- deterministic ZIP, manifest, checksums, SBOM and source/package parity;
- fresh install, supported upgrades, migration concurrency and rollback;
- security/privacy/authorization/accessibility/RTL/browser/load/restore suites;
- Hostinger staging with real Google and email providers;
- Founder acceptance before production deployment.
