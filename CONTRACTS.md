# File 02 Contract Register — Version 1.0.0

## Required consumers

### `smc.authentication-account` 1.0.0

Provider: File 00 — Sabri Membership Core.

Methods:

- `register_account(payload, context)`
- `mark_email_verified(user_id, email, context)`
- `get_completion_state(user_id, context)`

Required response envelope:

```text
contract
contract_version
result: allow | deny | unknown
reason_code
```

Registration success additionally returns `user_id`. Completion state returns `missing_steps` and a same-origin `next_route`. Missing, incompatible or malformed responses fail closed.

### `smc.cf01.membership-assurance` 1.0.0

Provider: File 00. Supplies current membership, suspension, verification and MFA readiness assertions and performs the canonical step-up verification. File 02 never reads private File 00 TOTP/recovery secrets.

## File 02 producers

### `sa.cf01.authentication-assurance` 1.0.0

Session-, purpose- and scope-bound authentication assurance for approved clinical/professional consumers. It does not authorize the consumer's native object or action.

### `sauth.account-completion-resolver` 1.0.0

Returns:

```text
result
reason_code
missing_steps[]
owner_route
 destination
```

It validates same-origin owner routes and prevents repeated completion loops.

### `sauth.system-check` 1.0.0

Redacted operational status for versions, contracts, schema, routes, cron, Safe Mode and provider circuits. It excludes credentials and sensitive identity data.

## Authentication event schema 1.0.0

Allowed events include:

- `AccountAuthenticationSucceeded.v1`
- `AccountAuthenticationFailed.v1`
- `EmailVerified.v1`
- `PasswordResetCompleted.v1`
- `AuthSessionRevoked.v1`

Every event has an event UUID, trace ID, schema version, privacy class, bounded sanitized payload, retry/dead-letter state and timestamps. Events never contain passwords, verification/reset tokens, OAuth tokens, TOTP/recovery codes, provider secrets, raw session tokens or full IP addresses.

## Compatibility law

- Major contract mismatch: operation denied as `unknown`/provider incompatible.
- Minor additive fields: consumers ignore unknown fields.
- Internal tables/meta are not public integration contracts.
- Feature detection never substitutes for authorization.
