# File 02 Threat Model — Version 1.0.0

## Protected assets

Account credentials, authentication sessions, email ownership, provider links, step-up assurance, registration eligibility handoff, recovery state, security events and provider configuration.

## Trust boundaries

- Browser ↔ WordPress File 02 routes/actions.
- File 02 ↔ File 00 versioned contracts.
- File 02 ↔ Google OAuth endpoints.
- WordPress ↔ SMTP/email delivery.
- File 02 ↔ File 01/File 20 manifests and approved event consumers.
- Administrators ↔ operational settings/System Check.

## Principal threats and controls

| Threat | Controls |
|---|---|
| Account enumeration | Generic login/recovery/resend responses; dummy password hash; bounded timing and rate limits. |
| Credential stuffing/brute force | Atomic per-IP/per-account limits, recent-failure risk score, provider circuits and security events. |
| Session theft/replay | WordPress secure cookies, HMAC-only registry, device binding projection, individual/all revocation and revoked-session denial. |
| Verification/reset replay | One-time token/key, short expiry, attempt limits, atomic status claim and token rotation/zeroing. |
| OAuth CSRF/code interception | State, OpenID nonce, PKCE, exact callback, issuer/audience/azp/time validation and minimal scopes. |
| Silent account merge | Explicit authenticated linking, exact canonical-email match, collision checks and File 00 step-up. |
| Open redirect/phishing | Same-origin `wp_validate_redirect`, exact completion-owner route validation and auth-loop denial. |
| Privilege escalation | File 00 canonical assertions; no role mutation; authentication never grants domain permission. |
| IDOR | Own-user session queries/actions, opaque IDs, nonce and subject binding. |
| Provider outage/amplification | Bounded timeouts/redirects, TLS/unsafe-URL enforcement, circuit breakers, fail-closed UX and Safe Mode. |
| Secret leakage | Encrypted Google secret, redacted diagnostics/events, no raw tokens/passwords/full IP, secret scans. |
| Database race/duplicate effects | Unique keys, idempotency, atomic counters/status claims, outbox event IDs and replay-safe provider operations. |
| Privacy over-collection | HMAC bindings, generalized labels, bounded risk categories, cleanup, export/erasure/anonymization. |
| Migration/repair damage | Additive `dbDelta`, File 02-only guarded repair, non-destructive uninstall, backup/restore and rollback rules. |

## Residual risks requiring staging/operations evidence

Real provider behavior, compromised end-user devices, email-account compromise, hosting/database failures, legal jurisdiction requirements and sophisticated distributed attacks cannot be proven solely by source tests. These remain staging, independent security review and monitored-operation gates.
