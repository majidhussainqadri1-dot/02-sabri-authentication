# Changelog

All notable changes to Sabri Authentication and Accounts are recorded here.

## 1.0.0 — Source Candidate

### Added

- Complete File 00 account-registration consumer contract and explicit National ID/Passport handoff.
- Signed one-time email verification with canonical-email binding, expiry, resend/attempt limits and replay protection.
- Native password authentication with WordPress password APIs, dummy hashing, generic errors and brute-force controls.
- New-device/network/recent-failure risk evaluation and File 00-owned step-up challenge.
- Loop-safe account-completion resolver with same-origin owner routes.
- Hardened Google OAuth/link/unlink flows with state, nonce, PKCE and collision protection.
- HMAC-only opaque session registry with individual, other and all-session revocation.
- Privacy-minimized authentication event outbox with retry and dead-letter states.
- Provider circuit breakers, bounded HTTP behavior and redacted health projection.
- System Check, Safe Mode, guarded File 02-only repair and File 01/File 20 manifests.
- Additive 1.0.0 schema, retention cleanup, privacy export/erasure and non-destructive uninstall.
- Deterministic package builder, manifest/checksum generation and clean-extract CI.
- Architecture, contracts, data, migration, rollback, backup, incident, threat, privacy and staging documentation.

### Changed

- Promoted plugin/runtime schema from development 0.4.0 to source candidate 1.0.0.
- Authentication visual identity uses the platform green primary color and accessible responsive controls.
- Safe redirect handling now distinguishes an intentionally empty fallback from the default Home fallback.

### Security

- No raw passwords, reset/verification tokens, OAuth tokens, TOTP/recovery codes, session tokens or full IP addresses are retained in File 02 events/diagnostics/session projections.
- Authentication remains separate from domain authorization; File 00 and native object owners are revalidated.

## 0.3.0

- Added File 00-owned step-up assurance and professional reauthentication bridges.
- Removed direct access to private File 00 MFA storage.

## 0.2.0

- Made File 00 a hard dependency and removed parallel role/profile ownership.
- Hardened Google linking, rate limiting and private-page headers.

## 0.1.0

- Initial authentication and account foundation.
