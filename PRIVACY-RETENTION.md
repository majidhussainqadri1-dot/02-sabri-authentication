# File 02 Privacy and Retention Register — 1.3.0

## Governing principles

File 02 applies data minimization, purpose limitation, Islamic privacy and dignity, no secret disclosure, subject-bound access and expiry-based deletion. Authentication data never becomes profile, search, ranking, analytics or advertising data merely because it exists.

File 00 remains the canonical owner of identity, account class, age/guardian truth, verification, suspension and consents. File 03 owns the profile photograph. File 02 owns only authentication challenges, session/device projections, provider-link projections and operational evidence.

## Data classes and default lifecycle

| Data | Storage | Default lifecycle | Privacy action |
|---|---|---|---|
| Rate-limit bucket | HMAC-only `sauth_rate_limits` | window plus bounded cleanup | expires; not user-readable raw telemetry |
| Email-verification challenge | HMAC email/token binding | 30-minute validity; expired rows cleaned | export status/timestamps; erase when lawful |
| Login-risk challenge | opaque/HMAC bindings | approximately 10 minutes | expired deletion |
| Trusted-device projection | HMAC fingerprint/network plus generalized labels | bounded inactivity period | export and erase |
| Session projection | HMAC token/device plus generalized labels | until expiry/revocation plus bounded cleanup | export and erase; WordPress session also revoked |
| Authentication attempts | HMAC network/device and reason category | bounded security window | subject ID anonymized on erasure; residual minimized security evidence may remain |
| Event outbox | sanitized versioned fact | published/dead-letter retention per security policy | no secrets; erasure/retention-hold coordination |
| Google link projection | provider subject, verified matching email, timestamps, optional picture candidate | until unlink/erasure/retention hold | export and erase; no access/refresh token stored |
| Provider configuration | dedicated-`SA_MASTER_KEY` AES-256-GCM v3 secret plus non-secret client ID/status | while configured | operator-controlled deletion/rotation |
| Passkey credentials | opaque credential lookup, encrypted presentation copy, server-derived public key, lifecycle metadata | active plus bounded revoked/compromised retention | paginated export; row/user-handle/assurance-epoch erasure with postcondition verification |
| Legacy `sa_*` tables/options | rollback evidence only after 1.1 migration | until separately approved purge | not active source; protected and included in privacy scan |

## Parent-plan registration fields

City, declared account type, separate Ethical Conduct consent and profile-photograph completion truth are stored by File 00 through `smc.authentication-account 1.1.0`. City and any Google picture candidate supplied to File 00 are encrypted with purpose/context binding. File 02 does not duplicate them into public profile data.

## Prohibited data

File 02 does not retain raw passwords, reset keys, email-verification tokens, OAuth access/refresh/ID tokens, authorization codes, TOTP secrets, recovery codes, raw WordPress session tokens, full IP addresses or raw identity-document evidence in events, diagnostics or exports.

## Export

The privacy exporter returns provider-link metadata plus bounded 50-row pages of session, device, risk-challenge, attempt, event and passkey status. It must never reveal HMAC bindings, encrypted provider secrets, raw tokens, full network identifiers or internal collision keys. File 00 separately exports its owned registration/consent data.

## Erasure and anonymization

- Google projections and local challenge/session/device/passkey rows are deleted in bounded batches from canonical and preserved legacy stores where no legal/security hold applies; passkey user handle and assurance epoch are removed only after credential rows reach zero.
- Active WordPress sessions are revoked before or with session-projection erasure.
- Authentication-attempt subject IDs are erased with File 02-owned rows while bounded, identity-free security evidence may remain under separately governed retention.
- Outbox actor/subject IDs are zeroed and identity-bearing payload keys are removed recursively with compare-and-set/readback proof; events then follow their privacy class and retention policy.
- File 02 never erases File 00 identity/membership/guardian/consent truth or File 03 profile media.
- Preserved legacy tables must be included in erasure verification until an approved purge removes them.

## Diagnostics and logs

System Check, provider circuits and repair reports expose reason categories, counts and states only. They exclude credentials, raw payloads, full IP addresses, identity evidence and private File 00 facts. Support and analytics roles receive no blanket access.

## Review gates

Retention periods and exceptions require a dated policy source, purpose, owner, affected tables, privacy/Sharīʿah review, migration/erasure behavior, tests, rollback and Founder approval. Discovery of a new data path reopens review.
