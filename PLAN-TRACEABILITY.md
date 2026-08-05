# File 02 — Plan-to-Code Traceability

**Governing specification:** `SSH-F02-PLAN-2026-v1.0`  
**Candidate branch:** `codex/file02-full-plan-harmonization-0.4.0`  
**Candidate version/schema:** `1.0.0 / 1.0.0`  
**Source status:** all F02-FR and F02-NFR source obligations are represented; automated/package/staging/live/operational evidence remain separate gates.

## Governing ownership

File 02 owns authentication surfaces and orchestration for email/password, Google OAuth, recovery, login-risk challenge, session-entry presentation and account completion. File 00 remains the sole owner of platform identity, membership eligibility, roles, guardian state, verification, institutional authority and MFA policy. No File 02 source path creates a parallel platform role, membership truth or provider-based identity.

## Functional requirements

| Requirement | Version 1.0.0 source evidence | Source status |
|---|---|---|
| F02-FR-001 Account registration | `SA_Registration` validates all required fields and invokes only `SAUTH_Account_Contract::register_account()` with rate limits and idempotency. | Implemented |
| F02-FR-002 Email verification | `SAUTH_Email_Verification` provides secure one-time token issue, canonical-email binding, expiry, resend/attempt limits, replay/concurrency protection, File 00 completion and `EmailVerified.v1`. | Implemented |
| F02-FR-003 Password authentication | WordPress password APIs, unknown-user dummy hash, generic errors, per-IP/per-account limits, File 00 assertion/completion checks and risk orchestration. | Implemented |
| F02-FR-004 Google OAuth | State, OpenID nonce, PKCE, exact redirect, issuer/audience/azp/time checks, minimal scopes and verified-email validation. | Implemented |
| F02-FR-005 Account linking | Existing-session and File 00 step-up requirement, exact same-email linking, collision/concurrency controls, unlink and session revocation. | Implemented |
| F02-FR-006 Password recovery | Non-enumerating initiation, WordPress one-time reset key, minimum password policy, replay denial and all-session revocation. | Implemented |
| F02-FR-007 Session management | `SAUTH_Session_Manager` stores HMAC-only session bindings and opaque public IDs; provides current marker, generalized device/network, last activity/risk, individual revoke, revoke others and revoke all. | Implemented |
| F02-FR-008 Login risk challenge | `SAUTH_Login_Risk` evaluates new device, new network, recent failures and provider health; creates expiring one-time challenge and invokes File 00-owned step-up. | Implemented |
| F02-FR-009 Account completion | `SAUTH_Completion_Resolver` consumes File 00 state, validates same-origin owner route, blocks auth-route loops and prevents repeated unresolved redirects. | Implemented |
| F02-FR-010 Safe redirects | `SA_Security::safe_redirect()` plus completion-owner validation are used on login, logout, provider, recovery and completion routes. | Implemented |
| F02-FR-011 Auth audit events | Versioned privacy-minimized outbox with trace IDs, bounded retries/dead-letter and events for auth success/failure, email, reset, linking and session revocation. | Implemented |
| F02-FR-012 Degraded provider UX | Provider circuits, bounded HTTP behavior, Safe Mode, explicit fail-closed messages and redacted System Check preserve public reading and prevent false success. | Implemented |

## Non-functional requirements

| Requirement | Version 1.0.0 source evidence | Source status |
|---|---|---|
| F02-NFR-001 Authorization | File 00 mandatory; action nonces; own-user session controls; object/public-ID binding; current membership/suspension/completion/step-up rechecks; no direct companion writes. | Implemented; staging IDOR matrix pending |
| F02-NFR-002 Privacy lifecycle | Data minimization, HMAC token/device bindings, generalized network labels, event secret stripping, export/erasure, attempt anonymization and bounded cleanup. | Implemented; real provider/deletion evidence pending |
| F02-NFR-003 Reliability | Atomic rate limits, registration idempotency, one-time challenges, concurrency claims, outbox retry/dead-letter, circuit breakers and guarded repair. | Implemented; real failure-injection pending |
| F02-NFR-004 Performance | Bounded table indexes, list/batch limits, provider timeout/redirect bounds, circuit breaker and route asset scoping. | Implemented; measured staging p75/p95 pending |
| F02-NFR-005 Accessibility | Semantic account surfaces, explicit labels, error/status roles, keyboard focus, touch sizing, responsive reflow, logical CSS and reduced-motion handling. | Implemented; human WCAG/RTL/browser evidence pending |
| F02-NFR-006 Observability | Trace IDs, audit/outbox records, provider health, System Check, route/schema/cron diagnostics and redacted reason categories. | Implemented |
| F02-NFR-007 Migration/rollback | Additive idempotent `dbDelta` schemas, version gates, managed-page reconciliation, non-destructive uninstall and guarded repair. | Implemented; real upgrades/rollback pending |
| F02-NFR-008 Operability | Redacted System Check, Safe Mode, File 02-only repair, health menu, provider circuits and File 01/File 20 manifests. | Implemented |
| F02-NFR-009 Compatibility | PHP 7.4/8.3 source target, WordPress APIs, exact-head CI matrix, deterministic package and clean-extract lint workflow. | Implemented; Hostinger/WordPress acceptance pending |
| F02-NFR-010 Localization | Text domain, translation-ready WordPress messages, locale-independent data contracts, logical CSS and mixed RTL/LTR-safe presentation baseline. | Implemented; complete Urdu/Arabic linguistic QA pending |

## Source delivery batches completed

1. Canonical File 02/File 00 ownership and fail-closed contracts.
2. Registration, email verification and password authentication.
3. Google OAuth/link/unlink and assurance boundaries.
4. Password recovery and all-session revocation.
5. Opaque individual-session registry and revoked-token denial.
6. New-device/network login risk challenge and trusted-device lifecycle.
7. Loop-safe account-completion resolver.
8. Privacy-safe event outbox and provider circuit/HTTP controls.
9. System Check, Safe Mode, guarded repair and File 01/File 20 manifests.
10. Privacy/export/erasure/retention coverage, additive migration schemas and non-destructive uninstall.
11. Deterministic package builder and exact-head CI/package-parity workflow.
12. Architecture and no-network policy suites covering all twelve functional requirements.

## Evidence gates still external to source completion

- File 00 provider branch compatibility and cross-repository acceptance;
- exact latest-head CI success and deterministic package digest;
- fresh/upgrade/deactivate/reactivate/non-destructive uninstall on Hostinger staging;
- real SMTP/email, Google OAuth and optional File 19 transport tests;
- File 01/File 20/theme/LiteSpeed/File 24 integration acceptance;
- real-role security/privacy/IDOR/guardian/suspension journeys;
- browser/mobile/RTL/keyboard/screen-reader/zoom/load/provider-outage acceptance;
- database/files/keys backup restore and rollback rehearsal;
- two fresh final review/fix rounds on the immutable release head;
- Founder staging acceptance, production deployment and operational monitoring.

These gates determine **Automated-QA Green**, **Packaged**, **Staging-Accepted**, **Live-Deployed** and **Operational** status; they do not reopen the frozen source scope unless new defects or contract changes are discovered.
