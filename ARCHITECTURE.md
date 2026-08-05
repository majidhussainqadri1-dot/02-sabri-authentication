# File 02 Architecture — Authentication and Accounts 1.1.0

## Governing boundary

File 02 owns authentication surfaces and orchestration: password and Google-first registration presentation, password authentication, email verification, Google OAuth/linking, password recovery, login-risk challenge, session presentation/revocation and account-completion routing.

File 00 remains the sole canonical owner of membership legitimacy, identity assurance, declared account class, age/guardian truth, roles/capabilities, suspension, institutional authority, verification and MFA policy. Authentication success is never a domain authorization grant.

## Runtime layers

1. **Entry surfaces** — semantic templates for login, complete registration, email confirmation, risk challenge, recovery, sessions and provider management.
2. **Security utilities** — atomic rate limits, same-origin redirects, cryptographic tokens and encrypted provider configuration.
3. **File 00 contracts** — fail-closed consumers for `smc.authentication-account 1.1.0`, membership assertions and step-up assurance.
4. **Google-first bridge** — state, nonce, PKCE, claims validation and one-time context; Google proves email ownership only.
5. **Risk and session controls** — privacy-minimized trusted-device projections, one-time risk challenges and an HMAC-only opaque session registry.
6. **Reliability** — versioned authentication-event outbox, retries/dead-letter, provider circuit breakers and bounded HTTP behavior.
7. **Operations** — additive schema migration, System Check, Safe Mode, guarded repair and File 01/File 20 manifests.
8. **Privacy lifecycle** — export, erasure/anonymization, bounded retention and scheduled cleanup.

## Architectural invariants

- No File 02 role creation, account-class approval, identity approval, guardian decision or verification bypass.
- No raw password, reset key, verification token, OAuth token, TOTP/recovery code, raw session token or full IP in events, diagnostics or exports.
- Every protected mutation rechecks current provider/state and fails closed on uncertainty.
- Provider events are past-tense facts, not commands or permissions.
- User-specific and token-bearing routes are `noindex`, `noarchive` and `no-store`.
- Companion data is never repaired or deleted by File 02.
- Uninstall is non-destructive; destructive purge requires a separate governed operation.
- File 20 is the only shell/layout owner; File 02 supplies semantic content and versioned route manifests.
- File 03 owns profile photographs; File 00 exposes completion truth and File 02 only orchestrates the next step.

## Canonical public constitution

| Element | Value |
|---|---|
| Canonical repository | `02-sabri-authentication-and-accounts` |
| Package folder | `02-sabri-authentication` |
| WordPress slug/text domain | `sabri-authentication` |
| Public PHP prefix | `SAUTH_` |
| Canonical session route | `/account/sessions/` |

Pre-1.1 `SA_` classes, options, actions, page metadata and SQL literals are bounded compatibility inputs. New public integrations use only the canonical values.

## Tables owned by File 02

- `sauth_rate_limits`
- `sauth_auth_outbox`
- `sauth_email_verifications`
- `sauth_auth_sessions`
- `sauth_auth_devices`
- `sauth_auth_risk_challenges`
- `sauth_auth_attempts`

All canonical tables are additive and idempotently created through `dbDelta`. `SAUTH_Activator::migrate_legacy_tables()` copies old `sa_*` rows through `INSERT IGNORE` without deletion. `SAUTH_Storage_Router` rewrites any retained compatibility query to canonical storage before execution, except the explicit one-way migration query. Legacy tables remain rollback evidence and are not the active 1.1.0 source of truth.
