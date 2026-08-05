# File 02 Architecture — Sabri Authentication and Accounts 1.0.0

## Governing boundary

File 02 owns authentication surfaces and orchestration: registration presentation, password authentication, email verification, Google OAuth/linking, password recovery, login-risk challenge, session presentation/revocation and account-completion routing.

File 00 remains the sole canonical owner of membership legitimacy, identity assurance, age/guardian truth, roles/capabilities, suspension, institutional authority, verification and MFA policy. Authentication success is never a domain authorization grant.

## Runtime layers

1. **Entry surfaces** — shortcodes/templates for login, registration, email confirmation, risk challenge, recovery, sessions and provider management.
2. **Security utilities** — atomic rate limits, same-origin redirects, cryptographic tokens and encrypted provider configuration.
3. **File 00 contracts** — fail-closed consumers for registration, email completion, membership assertions and step-up assurance.
4. **Risk and session controls** — privacy-minimized trusted-device projections, risk challenges and an HMAC-only opaque session registry.
5. **Reliability** — versioned authentication-event outbox, retries/dead-letter, provider circuit breakers and bounded HTTP behavior.
6. **Operations** — additive schema migration, System Check, Safe Mode, guarded repair and File 01/File 20 manifests.
7. **Privacy lifecycle** — export, erasure/anonymization, bounded retention and scheduled cleanup.

## Architectural invariants

- No File 02 role creation, identity approval, guardian decision or verification bypass.
- No raw password, reset key, verification token, OAuth token, TOTP/recovery code, raw session token or full IP in events, diagnostics or exports.
- Every protected mutation rechecks current provider/state and fails closed on uncertainty.
- Provider events are past-tense facts, not commands or permissions.
- User-specific and token-bearing routes are `noindex`, `noarchive` and `no-store`.
- Companion data is never repaired or deleted by File 02.
- Uninstall is non-destructive; destructive purge requires a separate governed operation.

## Tables owned by File 02

- `sa_rate_limits`
- `sa_auth_outbox`
- `sa_email_verifications`
- `sa_auth_sessions`
- `sa_auth_devices`
- `sa_auth_risk_challenges`
- `sa_auth_attempts`

All tables are additive, idempotently created through `dbDelta`, and scoped to File 02-owned projections/evidence.
