# File 02 Threat Model — Version 1.3.0

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
| Silent account merge | Explicit authenticated linking, exact canonical-email match, shared ordered Google-subject/user database locks, collision checks, fresh File 02 passkey assurance and rollback postconditions. |
| Open redirect/phishing | Same-origin `wp_validate_redirect`, exact completion-owner route validation and auth-loop denial. |
| Privilege escalation | File 00 canonical assertions; no role mutation; authentication never grants domain permission. |
| IDOR | Own-user session queries/actions, opaque IDs, nonce and subject binding. |
| Provider outage/amplification | Bounded timeouts/redirects, TLS/unsafe-URL enforcement, circuit breakers, fail-closed UX and Safe Mode. |
| Secret leakage / key coupling | Google secret uses dedicated `SA_MASTER_KEY` AES-256-GCM v3 envelope; redacted diagnostics/events; no raw tokens/passwords/full IP; repository secret scans. |
| Database race/duplicate effects | Unique keys, compare-and-set email/passkey transitions, connection-owned subject locks, exact readbacks, outbox event IDs and replay-safe provider operations. |
| Privacy over-collection or incomplete erasure | HMAC bindings, generalized labels, 50-row export/erasure batches, canonical-and-legacy coverage, recursive outbox identity stripping and postcondition verification. |
| Migration/repair damage | Additive `dbDelta`, table/page postconditions before version markers, File 02-only guarded repair, Safe Mode containment, non-destructive uninstall, backup/restore and rollback rules. |
| WebAuthn replay/key substitution | Atomic challenge claim, request-specific receipt, exact RP/origin/UV/session binding, server-parsed CBOR/COSE key, RSA-2048/65537 minimum, signature verification, immutable backup eligibility and counter-regression containment. |
| Forged success UI | Server-signed notice receipts and one-time settings-success receipt; arbitrary query strings cannot establish authoritative success. |

## Residual risks requiring staging/operations evidence

Real provider behavior, compromised end-user devices, email-account compromise, hosting/database failures, legal jurisdiction requirements and sophisticated distributed attacks cannot be proven solely by source tests. These remain staging, independent security review and monitored-operation gates.
