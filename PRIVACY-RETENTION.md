# File 02 Privacy and Retention Schedule — 1.0.0

## Principles

File 02 collects only authentication data necessary for account entry, provider linking, abuse resistance, session control and operational evidence. File 00 remains the owner of membership identity, guardian, verification and institutional truth. Authentication data is never used as a public profile, ranking or advertising source.

## Lifecycle

| Data class | Purpose | Default lifecycle |
|---|---|---|
| Email challenge HMAC/status | Prove canonical email ownership | Pending until expiry/use; expired/delivery-failed removed after 7 days; verified local evidence after 30 days. |
| Risk challenge HMAC/state | One-time sign-in step-up | Five-minute active life; invalid/consumed state removed after 7 days. |
| Authentication attempts | Abuse/risk defense | 30 days; account bindings anonymized on erasure. |
| Trusted-device projection | New-device risk and user security context | Trusted up to 90 days without activity; then expired and removed under cleanup policy. |
| Session projection | User-visible session control and revocation evidence | Active until session expiry/revocation; inactive projection retained 30 days. |
| Provider health | Reliability/circuit state | Transient, at most 7 days without refresh. |
| Outbox events | Reliable delivery/audit | Pending through bounded retry/dead-letter; operational retention under approved event policy. |
| Google link metadata | Explicit provider-account link | Until unlink, privacy erasure or account lifecycle decision. |
| Encrypted provider secret | OAuth configuration | Until rotation/disable; never exported to users or diagnostics. |

## Rights handling

The WordPress privacy exporter returns human-readable File 02-owned link, verification, session and device information without raw hashes/tokens. Erasure removes File 02-owned challenges, projections and provider metadata, revokes sessions, and anonymizes bounded security-attempt evidence. File 00 records remain under File 00 privacy procedures.

## Restrictions

- No raw passwords, reset keys, verification tokens, OAuth tokens, TOTP/recovery codes or session tokens are retained by File 02.
- Full IP addresses are not stored in File 02 session/device/attempt tables.
- No private authentication data enters public cache, search index, analytics warehouse or ordinary support bundle.
- Legal/security retention exceptions require documented owner, purpose, scope and expiry.
