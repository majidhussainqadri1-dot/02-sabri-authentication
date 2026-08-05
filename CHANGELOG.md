# Changelog

All notable changes to Sabri Authentication and Accounts are recorded here.

## 1.1.0 — Three-Plan Source Candidate

### Added

- Secure Google-first account registration with state, nonce, PKCE S256, fingerprint binding, exact issuer/audience/azp/time checks, verified email and one-time registration context.
- Required city and declared account-type fields.
- Separate versioned Islamic, professional and institutional Ethical Conduct consent.
- Required profile-photograph completion acknowledgement and File 03/File 00 completion hooks.
- Adult-only professional/institutional account declarations.
- File 00 `smc.authentication-account 1.1.0` contract for all parent-plan fields.
- Canonical nested `/account/sessions/` route and permanent compatibility redirect.
- Canonical `SAUTH_` constants/classes/actions/options with bounded legacy aliases.
- Three-plan architecture and source guards.
- JavaScript/CSS validation and retained exact-head package artifacts in GitHub Actions.

### Corrected

- Missing city, account type, profile-photo requirement and Ethical Conduct consent.
- Password-only initial registration limitation.
- Non-canonical account-session route.
- Stale 0.2.0/0.4.0 README, status, release-lock and manifest records.
- File 00 completion state that omitted the new parent-plan fields.
- Unbounded session quarantine fallback by delegating to WordPress session-token ownership.

### Compatibility

- Package folder and text domain remain stable.
- Legacy `SA_` identifiers and the old sessions page remain bounded compatibility paths; new integrations use `SAUTH_` and `/account/sessions/`.
- The existing GitHub repository is a transport location until its owner-level rename to `02-sabri-authentication-and-accounts`.

### Security

- Google-first registration never treats the provider as membership or role authority.
- Declared account type never grants capabilities.
- Google registration link completion fails closed on collision, changed browser fingerprint, expired proof or File 00 failure.
- Secrets, tokens, full IP addresses and raw session material remain excluded from events and diagnostics.

## 1.0.0 — Full File 02 Source Candidate

### Added

- File 00 registration contract, signed email verification and password authentication.
- New-device/network/recent-failure risk evaluation and File 00-owned step-up.
- Loop-safe account-completion resolver and same-origin routing.
- Google login/link/unlink with state, nonce, PKCE and collision protection.
- HMAC-only opaque session registry and scoped revocation.
- Privacy-minimized event outbox, provider circuit breakers, Safe Mode and System Check.
- Additive schemas, privacy lifecycle, non-destructive uninstall and deterministic package builder.

## 0.3.0

- Added File 00-owned step-up assurance and professional reauthentication bridges.
- Removed direct access to private File 00 MFA storage.

## 0.2.0

- Made File 00 a hard dependency and removed parallel role/profile ownership.
- Hardened Google linking, rate limiting and private-page headers.

## 0.1.0

- Initial authentication and account foundation.
