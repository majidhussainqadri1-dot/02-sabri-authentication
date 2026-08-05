=== Sabri Authentication and Accounts ===
Contributors: sabrihomeopathy
Tags: authentication, google login, accounts, recovery, sessions
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later

Authentication surfaces and orchestration for the Sabri Social Homeopathy Platform. File 00 — Sabri Membership Core remains the exclusive identity, membership, guardian, role, verification and MFA-policy authority.

== Current truthful status ==

Version 0.4.0 is a plan-harmonization development candidate. It is not packaged, staging-accepted, live-deployed or operationally approved. See PLAN-TRACEABILITY.md for requirement-by-requirement status.

== Required dependency ==

File 00 — Sabri Membership Core 1.2.7 or later with the approved assurance contract is mandatory.

The File 00 account-orchestration provider contract `smc.authentication-account` version 1.0.0 is required for registration, canonical email-verification completion and account-completion state. When that contract is unavailable, these operations fail closed rather than creating a second identity, role, guardian or verification authority.

== Implemented source capabilities ==

* Full File 02 registration surface for name, email, phone, password, sex/date-of-birth, address/country, identity reference, guardian reference and Terms/Privacy consent handoff to File 00.
* Registration validation, male/female platform age baselines, under-18 guardian requirement, per-IP/per-account rate limits and idempotent File 00 transaction handoff.
* Signed one-time email verification with 30-minute expiry, HMAC-only token storage, resend throttle, explicit confirmation, canonical-email binding, provider completion, replay denial and audit/event evidence.
* Native email/username and password authentication using WordPress password APIs, dummy hashing for unknown accounts, generic errors, brute-force controls and File 00 membership/completion rechecks before session creation.
* Hardened Google OAuth using state, OpenID nonce, PKCE, issuer/audience/authorized-party/time checks and minimal scopes.
* Explicit same-email Google account linking; no Google-based automatic WordPress account creation.
* File 00-owned second-factor verification for Google login, link and unlink.
* Session-bound authentication assurance contracts for CF-01 and professional verification workflows.
* Privacy-minimized authentication event outbox with trace IDs, retries and dead-letter state.
* Non-enumerating password-recovery request and canonical reset completion with all-session revocation.
* Authenticated session summary, generalized device/network presentation, revoke-other-sessions and sign-out-everywhere controls.
* Atomic fixed-window rate limiting, safe redirects, noindex/noarchive/no-store account pages, privacy export/erasure and encrypted Google Client Secret storage.
* Green primary visual identity, responsive logical CSS, focus-visible handling and reduced-motion behavior.

== Still required for plan completion ==

* Accepted File 00 registration/email/completion provider implementation and staging contract tests.
* Safe opaque per-session registry for individual session revocation.
* New-device/location risk challenge and File 00/File 24 step-up integration for password sign-in.
* Account-completion resolver loop prevention and formal state-transition tests.
* File 19/email-provider delivery integration and provider circuit breakers.
* Complete event inspection/reconciliation, System Check, repair, metrics and alerts.
* File 01 route registry and File 20 layout-placement contracts.
* Full migration/rollback, authorization/IDOR, privacy/retention, accessibility/RTL, browser/device, load and restore evidence.
* Two final fresh review/fix rounds after the remaining implementation.
* Deterministic installable package, SBOM, manifest, checksums, source/package parity and Hostinger staging.

== Security ==

Passwords, reset keys, verification tokens, OAuth tokens, TOTP/recovery codes, raw session tokens and provider secrets are never included in authentication events. Authentication success never grants membership, professional, clinical, publishing or financial authorization; native owners must revalidate every protected action.

== Changelog ==

= 0.4.0 =
* Started full reconciliation with SSH-F02-PLAN-2026-v1.0.
* Added versioned File 00 account-orchestration consumer boundary.
* Added full File 02 registration surface and validated File 00 handoff.
* Added signed, expiring, resend-throttled and replay-safe email verification.
* Added native WordPress-password authentication with generic errors and brute-force controls.
* Added privacy-safe authentication event outbox with retry/dead-letter state.
* Added canonical password reset completion and all-session revocation.
* Added session presentation, revoke-other-sessions and sign-out-everywhere controls.
* Added green responsive authentication UI, privacy lifecycle coverage and plan-to-code traceability.

= 0.3.0 =
* Removed direct reads of File 00 private two-factor metadata and secrets.
* Added session/purpose/scope-bound authentication assurance contracts.
* Added professional reauthentication bridge and adversarial contract tests.

= 0.2.0 =
* Made File 00 mandatory and removed parallel role/profile ownership.
* Hardened Google linking, privacy, rate limiting and account-page headers.

= 0.1.0 =
* Initial authentication and account foundation.
