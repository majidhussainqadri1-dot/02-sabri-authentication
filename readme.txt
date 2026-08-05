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

Version 0.4.0 is a plan-harmonization development candidate. It is not staging-accepted, live-deployed or operationally approved. See PLAN-TRACEABILITY.md for requirement-by-requirement status.

== Required dependency ==

File 00 — Sabri Membership Core 1.2.7 or later with the approved assurance contract is mandatory.

The new File 00 account-orchestration provider contract `smc.authentication-account` version 1.0.0 is required before File 02 registration, email-verification and completion-state orchestration can be accepted. When that contract is unavailable, those operations fail closed rather than creating a second identity authority.

== Implemented source capabilities ==

* Hardened Google OAuth using state, OpenID nonce, PKCE, issuer/audience/authorized-party/time checks and minimal scopes.
* Explicit same-email Google account linking; no Google-based automatic WordPress account creation.
* File 00-owned second-factor verification for Google login, link and unlink.
* Session-bound authentication assurance contracts for CF-01 and professional verification workflows.
* Versioned File 00 account-orchestration consumer boundary for registration, email verification and completion state.
* Privacy-minimized authentication event outbox with trace IDs, retries and dead-letter state.
* Non-enumerating password-recovery request and canonical reset completion with all-session revocation.
* Authenticated session summary, generalized device/network presentation, revoke-other-sessions and sign-out-everywhere controls.
* Atomic fixed-window rate limiting, safe redirects, noindex/noarchive/no-store account pages, privacy export/erasure and encrypted Google Client Secret storage.

== Still required for plan completion ==

* Full File 02 registration form and accepted File 00 registration transaction contract.
* Signed email-verification challenge issuance, resend throttling and idempotent consumption.
* Native File 02 password authentication with generic errors and risk challenges.
* Safe opaque per-session registry for individual session revocation.
* Account-completion redirect resolver with loop prevention.
* Complete event coverage, provider circuit breakers, System Check, repair and observability.
* File 01 route registry and File 20 layout-placement contracts.
* Full automated QA, two fresh review/fix rounds, deterministic package/SBOM and Hostinger staging.

== Security ==

Passwords, reset keys, OAuth tokens, TOTP/recovery codes, raw session tokens and provider secrets are never included in authentication events. Authentication success never grants membership, professional, clinical, publishing or financial authorization; native owners must revalidate every protected action.

== Changelog ==

= 0.4.0 =
* Started full reconciliation with SSH-F02-PLAN-2026-v1.0.
* Added versioned File 00 account-orchestration consumer boundary.
* Added privacy-safe authentication event outbox with retry/dead-letter state.
* Added canonical password reset completion and all-session revocation.
* Added session presentation, revoke-other-sessions and sign-out-everywhere controls.
* Added plan-to-code traceability and truthful remaining-gate register.

= 0.3.0 =
* Removed direct reads of File 00 private two-factor metadata and secrets.
* Added session/purpose/scope-bound authentication assurance contracts.
* Added professional reauthentication bridge and adversarial contract tests.

= 0.2.0 =
* Made File 00 mandatory and removed parallel role/profile ownership.
* Hardened Google linking, privacy, rate limiting and account-page headers.

= 0.1.0 =
* Initial authentication and account foundation.
