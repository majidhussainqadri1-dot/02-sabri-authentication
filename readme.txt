=== Sabri Authentication and Accounts ===
Contributors: sabrihomeopathy
Tags: authentication, google login, accounts, step-up assurance, homeopathy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later

Google authentication, account-recovery, and session-bound authentication-assurance integration for the Sabri Social Homeopathy Platform.

== Required dependency ==

File 00 — Sabri Membership Core 1.2.7 or later is mandatory and remains the exclusive authority for registration, identity evidence, member profiles, account types, WordPress roles, institutional verification, password/TOTP login, recovery codes, guardian state, and approval status.

File 02 does not create users, roles, verification status, parallel profile records, clinical records, or clinical authorization. It does not read File 00 private TOTP or recovery storage.

== Authentication assurance ==

Contract `sa.cf01.authentication-assurance` version `1.0.0` consumes the versioned File 00 provider contract, then binds accepted second-factor evidence to the authenticated WordPress session, approved purpose, opaque scope, client fingerprint, method, assurance level, trace ID, and short expiry.

A File 02 assurance is authentication evidence only. CF-01 and every other native owner must separately recheck membership, treating relationship, consent/guardian, practitioner eligibility, object, field, purpose, record version, and other action-time authorization.

No raw session token, TOTP code, recovery code, provider secret, Google token, patient data, or clinical content is returned in the public assertion.

== Installation ==

1. Install and activate Sabri Membership Core 1.2.7 or later first.
2. Upload and activate Sabri Authentication and Accounts.
3. Open Sabri Shell > Authentication, or Settings > Authentication when the unified shell is unavailable.
4. Configure the Google Web OAuth Client ID, encrypted Client Secret, and exact HTTPS redirect URI.
5. Complete Google consent-screen, Privacy Policy, Terms, and staging validation before enabling Google sign-in.

== Google account flow ==

1. A user first creates and completes a verified Membership Core account.
2. The account must be approved or verified and have Membership Core two-factor authentication enabled.
3. The signed-in user explicitly links a Google account with the exact same verified email.
4. Linking, unlinking, and every Google sign-in require the current Membership Core Authenticator or one-time recovery code through the File 00 provider contract.
5. Login evidence is temporarily pending until the generated WordPress session token exists, then it is promoted and bound to that exact session.
6. New WordPress users are never auto-created from Google claims.
7. Google access and refresh tokens are not retained.

== Security ==

The module uses OAuth state, OpenID nonce, PKCE, issuer/audience/authorized-party/time checks, nonce-protected explicit account linking, a concurrency lock, versioned Membership Core assertions, per-user fixed-window atomic rate limits, purpose/scope/session/fingerprint-bound assurance receipts, no-cache/noindex/no-referrer account pages, safe redirects, authenticated Google Client Secret encryption, privacy export/erasure, session receipt revocation on logout, other-session revocation on unlink, and auditable link/login/unlink events.

== Changelog ==

= 0.3.0 =
* Removed direct reads of File 00 private two-factor metadata and secrets.
* Requires the versioned File 00 CF-01 membership and step-up provider contract.
* Added `sa.cf01.authentication-assurance` version 1.0.0 with valid/invalid/unknown results.
* Binds accepted evidence to the exact WordPress session, purpose, opaque scope, client fingerprint, method, trace, and expiry.
* Added pre-login pending evidence promotion through the WordPress logged-in-cookie token event.
* Added fail-closed provider schema/version/time validation, verified transient writes, rollback on index failure, session-token rotation invalidation, and logout cleanup.
* Added Google login/link/unlink purpose and scope binding without converting authentication into clinical authorization.
* Added architecture and runtime adversarial tests.

= 0.2.0 =
* Made Sabri Membership Core a mandatory dependency and removed fallback role/profile ownership.
* Removed direct File 02 registration, user creation, password login, and role mutation.
* Added explicit Google linking for existing approved Membership Core accounts only.
* Required Membership Core Authenticator or recovery code after Google identity verification.
* Added consistent Google link metadata, nonce-protected and concurrency-locked linking, and a 2FA-protected unlink flow that revokes other sessions.
* Added atomic fixed-window database rate limiting, a fixed-window fallback, per-user 2FA limits, and success resets.
* Added noindex, noarchive, no-store/private, no-referrer, frame, content-type, and permissions headers for private account pages.
* Completed File 02 privacy export and erasure coverage, including legacy metadata and the legacy WordPress biography.
* Moved admin integration to the Unified Application Shell when available.
* Added controlled page ownership, dependency failure handling, pinned CI actions, and architecture checks.

= 0.1.0 =
* Initial authentication and account foundation.
