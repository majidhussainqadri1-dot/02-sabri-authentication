# File 02 Contract Register — Version 1.2.0

## Required consumers

### `smc.authentication-account` 1.1.0

Provider: File 00 — Sabri Membership Core through `SMC_Authentication_Contract_V11`.

Methods:

- `register_account(payload, context)`
- `mark_email_verified(user_id, email, context)`
- `get_completion_state(user_id, context)`

The registration payload includes the File 02 plan fields plus parent-plan completion fields:

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

Registration success additionally returns `user_id` and canonical subject identity. Completion state returns `missing_steps` and a same-origin `next_route`. Missing, incompatible or malformed responses fail closed.

### `smc.cf01.membership-assurance` 1.0.0

Provider: File 00. Supplies current membership, suspension, verification and MFA-readiness assertions and performs canonical step-up verification. File 02 never reads private File 00 TOTP or recovery-code storage.

## File 02 producers

### `sa.cf01.authentication-assurance` 1.0.0

Session-, purpose- and scope-bound authentication assurance for approved clinical/professional consumers. It never authorizes the consumer's native object or action.

### `smc_file02_authentication_assurance_v1` / File 02 Advanced Trust projection 1.0.0

Consumer: File 00 Advanced Trust. Producer: `SAUTH_Passkeys::file00_assurance()`.

A successful fresh passkey sign-in may project only:

```text
contract_version: 1.0.0
owner: file02
level: 3
method: webauthn_passkey
passkey_asserted: true
hardware_backed: false unless independently proven
verified_at: unix timestamp
```

Binding law:

- current logged-in subject must equal the requested File 00 subject;
- current WordPress session token is HMAC-bound and never exposed;
- client fingerprint is privacy-preserving and bound to the receipt;
- receipt lifetime is at most five minutes;
- File 00 independently checks owner, version, freshness and its own revalidation floor;
- under `attestation=none`, File 02 does **not** claim hardware provenance;
- absence/malformed/stale receipt leaves File 00's baseline unchanged and cannot elevate authority.

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

Redacted operational status for versions, contracts, canonical schema, routes, cron, Safe Mode and provider circuits. It excludes credentials and sensitive identity data. Passkey/WebAuthn has an additional local environment gate for HTTPS/RP ID/OpenSSL and fails closed when unavailable.

### File 01 / File 20 manifests

File 02 publishes its module and route manifests. File 20 remains the only global shell/layout owner. The canonical authenticated session route is `/account/sessions/`; the old `/account-sessions/` page is compatibility-only and redirects permanently. Passkey manager content is a private File 02 account-security surface and never creates a second global shell.

## WebAuthn ceremony contract 1.0.0

### Registration

- secure HTTPS origin (localhost exception only for standards-permitted development);
- random 32-byte challenge, five-minute TTL, fingerprint/RP/origin binding;
- atomic unique replay claim before completion;
- discoverable credential and `userVerification=required`;
- `attestation=none` for privacy;
- server parses `attestationObject` CBOR and `authenticatorData`;
- server extracts COSE public key; client-supplied public key is rejected by design;
- only ES256 (`-7`) EC2 P-256 and RS256 (`-257`) RSA are accepted;
- unique stable SHA-256 credential-ID index prevents duplicate registration;
- management requires fresh reauthentication; File 00 2FA-enabled accounts cannot use password-only management.

### Authentication

- usernameless discoverable-credential request;
- `clientDataJSON.type=webauthn.get`;
- exact challenge, origin and RP ID hash validation;
- UP + UV flags required;
- optional userHandle must equal the account's random opaque File 02 handle;
- assertion signature verified over `authenticatorData || SHA256(clientDataJSON)`;
- non-zero signature-counter regression marks credential compromised;
- membership/suspension/completion rechecked before session issuance;
- successful session remains subject to File 00 and every native domain owner's authorization.

## Authentication event schema 1.0.0

Allowed events include:

- `AccountAuthenticationSucceeded.v1`
- `AccountAuthenticationFailed.v1`
- `EmailVerified.v1`
- `PasswordResetCompleted.v1`
- `AuthSessionRevoked.v1`
- `GoogleAccountLinked.v1`
- `GoogleAccountUnlinked.v1`
- `PasskeyRegistered.v1`
- `PasskeyAuthenticated.v1`
- `PasskeyRevoked.v1`

Every event has an event UUID, trace ID, schema version, privacy class, bounded sanitized payload, retry/dead-letter state and timestamps. Events never contain passwords, verification/reset tokens, OAuth tokens, TOTP/recovery codes, passkey credential IDs/private keys, provider secrets, raw session tokens or full IP addresses.

## Compatibility law

- Major/minimum-version contract mismatch returns `unknown` or leaves baseline unchanged and fails closed for privileged action.
- Minor additive fields are ignored by older consumers unless explicitly required.
- Internal tables/meta are not public integration contracts.
- Feature detection never substitutes for authorization.
- Canonical public identifiers use `SAUTH_`; legacy `SA_` identifiers are bounded compatibility aliases only.
- WebAuthn browser feature availability never proves account eligibility or authorization.
