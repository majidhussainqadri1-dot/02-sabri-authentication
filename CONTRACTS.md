# File 02 Contract Register — Version 1.1.0

## Required consumers

### `smc.authentication-account` 1.1.0

Provider: File 00 — Sabri Membership Core 1.2.11 candidate through `SMC_Authentication_Contract_V11`.

Methods:

- `register_account(payload, context)`
- `mark_email_verified(user_id, email, context)`
- `get_completion_state(user_id, context)`

The registration payload includes the File 02 plan fields plus the parent-plan completion fields:

```text
name, email, phone
password/password_confirm OR verified Google registration proof
sex, date_of_birth, address, city, country
account_type declaration
identity_type, identity_reference, guardian_reference
profile_photo_required
terms_version, privacy_version, ethical_conduct_version
```

A declared account type never grants a role, capability or verification. File 00 remains the sole account-class and membership authority. Google proves canonical email ownership only.

Required response envelope:

```text
contract
contract_version
result: allow | deny | unknown
reason_code
```

Registration success additionally returns `user_id` and canonical `subject_uuid`. Completion state returns `missing_steps` and a same-origin `next_route`; the v1.1 provider includes city, account type, Ethical Conduct and profile-photograph completion. Missing, incompatible or malformed responses fail closed.

### `smc.cf01.membership-assurance` 1.0.0

Provider: File 00. Supplies current membership, suspension, verification and MFA-readiness assertions and performs canonical step-up verification. File 02 never reads private File 00 TOTP or recovery-code storage.

## File 02 producers

### `sa.cf01.authentication-assurance` 1.0.0

Session-, purpose- and scope-bound authentication assurance for approved clinical and professional consumers. It does not authorize the consumer’s native object or action.

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

Redacted operational status for versions, contracts, canonical `sauth_*` schema, routes, cron, Safe Mode and provider circuits. It excludes credentials and sensitive identity data.

### File 01 / File 20 manifests

File 02 publishes its module and route manifests. File 20 remains the only global shell/layout owner. The canonical authenticated session route is `/account/sessions/`; the old `/account-sessions/` page is compatibility-only and redirects permanently.

## Authentication event schema 1.0.0

Allowed events include:

- `AccountAuthenticationSucceeded.v1`
- `AccountAuthenticationFailed.v1`
- `EmailVerified.v1`
- `PasswordResetCompleted.v1`
- `AuthSessionRevoked.v1`
- `GoogleAccountLinked.v1`
- `GoogleAccountUnlinked.v1`

Every event has an event UUID, trace ID, schema version, privacy class, bounded sanitized payload, retry/dead-letter state and timestamps. Events never contain passwords, verification/reset tokens, OAuth tokens, TOTP/recovery codes, provider secrets, raw session tokens or full IP addresses.

## Compatibility law

- Major or minimum-version contract mismatch: operation returns `unknown` and fails closed.
- Minor additive fields: consumers ignore unknown fields.
- Internal tables/meta are not public integration contracts.
- Feature detection never substitutes for authorization.
- Canonical 1.1 public identifiers use `SAUTH_`; legacy `SA_` identifiers are bounded compatibility aliases only.
