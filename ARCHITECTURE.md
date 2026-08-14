# File 02 Architecture — Authentication and Accounts 1.2.5

## Governing boundary

File 02 owns authentication surfaces and ceremonies: password and Google-first registration presentation, password authentication, email verification, Google OAuth/linking, WebAuthn/passkey enrollment and authentication, password recovery, login-risk challenge, session/device presentation/revocation and account-completion routing.

File 00 remains the sole canonical owner of membership legitimacy, identity assurance, declared account class, age/guardian truth, roles/capabilities, suspension, institutional authority, verification and MFA policy. File 24 may raise risk requirements. Authentication success is never a domain authorization grant.

## Runtime layers

1. **Entry surfaces** — semantic templates for login, registration, email confirmation, risk challenge, recovery, sessions, passkeys and provider management.
2. **Security utilities** — atomic rate limits, same-origin redirects, cryptographic tokens, encrypted provider configuration and privacy-minimized fingerprints.
3. **File 00 contracts** — fail-closed consumers for `smc.authentication-account 1.1.0` and membership/eligibility assertions; File 00 consumes the fresh File 02 passkey assurance projection. Retired File 00 factor codes are not authentication ceremonies.
4. **Google-first bridge** — state, nonce, PKCE, exact claims validation and one-time context; Google proves email ownership only.
5. **WebAuthn ceremony** — HTTPS/RP ID/origin binding, random one-time challenges, discoverable credentials, required user verification, server-side CBOR/COSE parsing, ES256/RS256 verification and credential lifecycle.
6. **Risk and session controls** — privacy-minimized device/network projections, risk challenges and an HMAC-only opaque WordPress-session registry.
7. **Reliability** — versioned authentication-event outbox, retries/dead-letter, provider circuit breakers and bounded HTTP behavior.
8. **Operations** — additive schema migration, passkey schema reconciliation, System Check, Safe Mode, guarded repair and File 01/File 20 manifests.
9. **Privacy lifecycle** — export, erasure/anonymization, bounded retention, opaque passkey user handles and scheduled cleanup.

## WebAuthn trust boundary

- Registration requests `attestation: none` to minimize device-identifying provenance.
- The server decodes the attestation object and extracts the COSE public key directly from authenticator data. A client-supplied `getPublicKey()` value is not accepted.
- Only ES256 / P-256 and RS256 credentials are accepted.
- User Presence and User Verification flags are mandatory; resident credentials enable usernameless sign-in.
- Challenges are 32 random bytes, RP/origin/fingerprint-bound, expire after five minutes and are atomically claimed exactly once before verification.
- Credential IDs are random opaque WebAuthn identifiers. An encrypted copy is retained for exclusion UI; stable SHA-256 is used for lookup so WordPress salt rotation cannot orphan credentials.
- Each account receives a random opaque File 02 user handle; it is not derived from a WordPress user ID or salt and is included in File 02 privacy erasure.
- Non-zero signature-counter regression compromises the credential; zero counters remain valid for synchronized passkeys.
- Successful passkey authentication issues at most a five-minute, current-session/fingerprint-bound assurance projection to File 00: owner `file02`, contract `1.0.0`, level 3, `passkey_asserted=true`.
- `hardware_backed` remains false under `attestation=none`; no hardware provenance is invented. File 00 may keep hardware-bound policy actions closed until independently proven.
- Passkey enrollment/revocation requires fresh File 02 reauthentication: a current File 02 passkey assurance when present, otherwise current-password verification. Retired File 00 Authenticator/recovery codes are neither solicited nor accepted.

## Architectural invariants

- No File 02 role creation, account-class approval, identity approval, guardian decision, MFA policy or verification bypass.
- No raw password, reset key, verification token, OAuth token, TOTP/recovery code, passkey private key, biometric template, raw session token or full IP in events/diagnostics/exports.
- Every protected mutation rechecks current provider/state and fails closed on uncertainty.
- Provider and authentication events are past-tense facts, not commands or permissions.
- User-specific and token-bearing routes are `noindex`, `noarchive` and `no-store`.
- Companion data is never repaired or deleted by File 02.
- Uninstall is non-destructive; destructive purge requires a separately governed operation.
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
| Passkey assurance contract | `1.0.0`, owner `file02` |

Pre-1.1 `SA_` classes/options/actions/page metadata and SQL literals are bounded compatibility inputs. New public integrations use only the canonical values. Google provider secrets are written only as dedicated-`SA_MASTER_KEY` AES-256-GCM `v3:` envelopes; WordPress auth salts are migration-only legacy decrypt inputs, never the current encryption authority.

## Tables owned by File 02

- `sauth_rate_limits`
- `sauth_auth_outbox`
- `sauth_email_verifications`
- `sauth_auth_sessions`
- `sauth_auth_devices`
- `sauth_auth_risk_challenges`
- `sauth_auth_attempts`
- `sauth_passkeys`

The original seven canonical tables are additive and idempotently created through `dbDelta`; the passkey table has its own additive schema version `1.0.1` and is included in guarded repair. `SAUTH_Activator::migrate_legacy_tables()` copies old `sa_*` evidence without carrying legacy auto-increment IDs, preserves canonical rows on duplicate logical identities, and requires every legacy row's stable logical identity (`bucket_hash`, `event_id`, `user_id` or `public_id`) to be represented in canonical storage before migration success is published. Legacy tables remain rollback evidence and are not the active DB 1.2.1 source of truth.
