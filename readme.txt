=== Sabri Authentication and Accounts ===
Contributors: sabrihomeopathy
Tags: authentication, google login, accounts, homeopathy
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later

Google authentication and account-recovery integration for the Sabri Social Homeopathy Platform.

== Required dependency ==

File 00 — Sabri Membership Core is mandatory and remains the exclusive authority for registration, identity evidence, member profiles, account types, WordPress roles, institutional verification, password/TOTP login, recovery codes, and approval status.

File 02 does not create users, roles, verification status, or parallel profile records.

== Installation ==

1. Install and activate Sabri Membership Core first.
2. Upload and activate Sabri Authentication and Accounts.
3. Open Sabri Shell > Authentication, or Settings > Authentication when the unified shell is unavailable.
4. Configure the Google Web OAuth Client ID, encrypted Client Secret, and exact HTTPS redirect URI.
5. Complete Google consent-screen, Privacy Policy, Terms, and staging validation before enabling Google sign-in.

== Google account flow ==

1. A user first creates and completes a verified Membership Core account.
2. The account must be approved or verified and have Membership Core two-factor authentication enabled.
3. The signed-in user explicitly links a Google account with the exact same verified email.
4. Linking and every Google sign-in require the current Membership Core Authenticator or one-time recovery code.
5. New WordPress users are never auto-created from Google claims.
6. Google access and refresh tokens are not retained.

== Security ==

The module uses OAuth state, OpenID nonce, PKCE, issuer/audience/authorized-party/time checks, nonce-protected explicit account linking, a concurrency lock, Membership Core 2FA, per-user fixed-window atomic rate limits, no-cache/noindex/no-referrer account pages, safe redirects, authenticated Google Client Secret encryption, privacy export/erasure, session revocation on unlink, and auditable link/login/unlink events.

== Changelog ==

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
