=== Sabri Authentication and Accounts ===
Contributors: sabrihomeopathy
Tags: authentication, google login, accounts, recovery, sessions, security
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Authentication and account-entry orchestration for the Sabri Social Homeopathy Platform. File 00 — Sabri Membership Core remains the exclusive identity, membership, guardian, role, verification and MFA-policy authority.

== Truthful release status ==

Version 1.0.0 is the complete File 02 source candidate for SSH-F02-PLAN-2026-v1.0. Source completion is not the same as Hostinger staging acceptance, live deployment or operational acceptance. Those external evidence gates remain closed until the exact package, real providers, real roles, browsers/devices, backup/restore, rollback and Founder acceptance pass.

== Required dependency ==

File 00 — Sabri Membership Core 1.2.7 or later with:

* `smc.cf01.membership-assurance` 1.0.0 or later; and
* `smc.authentication-account` 1.0.0 or later.

If a required contract is missing, malformed or circuit-open, protected File 02 mutations fail closed without creating a parallel identity, role, guardian or verification authority. Public reading remains available.

== Implemented source capabilities ==

* Complete registration surface with name, email, phone, password, sex/date-of-birth, address/country, identity reference, guardian reference and Terms/Privacy handoff to File 00.
* Platform age baselines, under-18 guardian requirement, duplicate-safe idempotent registration and per-IP/per-account abuse controls.
* Signed one-time email verification with expiry, HMAC-only token storage, canonical-email binding, resend throttle, explicit confirmation, replay/concurrency protection, cleanup and audit/event evidence.
* Email/username and password authentication using WordPress password APIs, unknown-account dummy hashing, generic errors, rate controls and File 00 eligibility/completion rechecks.
* New-device, new-network and recent-failure risk scoring with File 00-owned second-factor step-up, one-time challenges and fail-safe denial.
* Loop-safe account-completion resolution with same-origin owner routes, repeated-route detection and safe fallback.
* Hardened Google OAuth with state, nonce, PKCE, exact issuer/audience/authorized-party/time validation, minimal scopes, explicit same-email linking and no provider-based account auto-creation.
* Opaque per-session registry with current marker, generalized device/network presentation, risk projection, individual revocation, revoke-others and sign-out-everywhere.
* Password reset with one-time WordPress key validation and all-session revocation.
* Session-bound authentication assurance contracts for approved clinical and professional workflows without granting native object authorization.
* Privacy-minimized versioned authentication-event outbox with trace IDs, bounded retry, dead-letter state and secret stripping.
* Provider circuit breakers, bounded HTTP timeouts, TLS/unsafe-URL enforcement and redacted provider-health projections.
* Redacted System Check, Safe Mode, guarded File 02-only repair, cron/schema/route diagnostics and File 01/File 20 manifests.
* Privacy export/erasure for Google links, email challenges, session/device projections and risk challenges; bounded security-attempt anonymization.
* Noindex/noarchive/no-store account pages, same-origin safe redirects, encrypted Google Client Secret and atomic fixed-window rate limits.
* Green primary identity, responsive logical CSS, keyboard focus, reduced motion and RTL-ready structure.

== External acceptance gates ==

* Exact compatible File 00 provider implementation and cross-repository contract tests.
* Deterministic package, manifest, SHA-256, SBOM and clean-extract parity evidence.
* Hostinger staging fresh install and supported upgrade with real MySQL/dbDelta behavior.
* Real SMTP/email and Google OAuth provider testing, timeout/circuit-breaker drills and File 19 delivery integration where installed.
* File 01 route registry, File 20 placement, active theme, LiteSpeed and File 24 assurance acceptance.
* Real Founder/member/minor/guardian/suspended/reviewer/security-operator journeys.
* Authorization/IDOR, privacy/retention, browser/device, RTL, keyboard/screen-reader/zoom, performance/load and provider-outage acceptance.
* Verified database/files/keys backup restore and rollback rehearsal.
* Two fresh final review/fix rounds on the immutable package head, Founder approval, production deployment and monitored rollback window.

== Security ==

Passwords, reset keys, verification tokens, OAuth tokens, TOTP/recovery codes, raw session tokens, full IP addresses and provider secrets are never included in File 02 events or public diagnostics. Authentication success is not authorization: every protected action remains subject to its canonical owner's current object, field, purpose, relationship, consent, guardian, suspension and entitlement checks.

== Changelog ==

= 1.0.0 =
* Completed F02-FR-001 through F02-FR-012 source implementation.
* Added complete registration, signed email verification and native password authentication.
* Added new-device/network risk challenges and File 00 step-up orchestration.
* Added loop-safe account completion and same-origin destination enforcement.
* Added opaque per-session registry and individual/all session revocation.
* Added provider circuit breakers, bounded HTTP controls and degraded UX.
* Added redacted System Check, Safe Mode, guarded repair and File 01/File 20 manifests.
* Completed privacy lifecycle, event outbox, migration schemas and operational controls.
* Promoted the source/schema candidate from 0.4.0 to 1.0.0.

= 0.3.0 =
* Removed direct reads of File 00 private two-factor metadata and secrets.
* Added session/purpose/scope-bound authentication assurance contracts.
* Added professional reauthentication bridge and adversarial contract tests.

= 0.2.0 =
* Made File 00 mandatory and removed parallel role/profile ownership.
* Hardened Google linking, privacy, rate limiting and account-page headers.

= 0.1.0 =
* Initial authentication and account foundation.
