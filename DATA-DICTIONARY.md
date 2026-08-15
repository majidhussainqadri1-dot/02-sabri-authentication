# File 02 Data Dictionary — Version 1.3.0

## Classification legend

- **Security** — restricted operational evidence; never public or searchable.
- **Account-private** — visible only to the subject and authorized purpose-bound operators.
- **Derived-minimized** — hash/HMAC/generalized label/bounded category; not raw source data.
- **Operational** — non-secret health/queue state.

`wp_` below represents the actual WordPress database prefix.

## Canonical File 02 tables

### `wp_sauth_rate_limits`
Atomic fixed-window abuse counters keyed by a privacy-preserving bucket. Stores hit count, window start, expiry and update time. **Classification:** Security / Derived-minimized. Short-lived.

### `wp_sauth_auth_outbox`
Versioned authentication facts with event UUID, trace ID, privacy class, actor/subject IDs, sanitized JSON payload, delivery state, retry count and timestamps. **Classification:** Security. No passwords, tokens, passkey credential IDs or private keys permitted.

### `wp_sauth_email_verifications`
One row per user: HMAC email binding, HMAC token binding, status, attempts, sent/expiry/verified timestamps. Status includes transient `issuing`/`verifying`, published `pending`, terminal `verified`/`expired`, and recoverable `delivery_failed`. Raw email and raw token are not stored. **Classification:** Account-private / Derived-minimized.

### `wp_sauth_auth_sessions`
Opaque public session ID, subject ID, HMAC session-token binding, HMAC device binding, generalized device/network labels, risk band, status, expiry/revocation/activity timestamps. **Classification:** Account-private / Derived-minimized.

### `wp_sauth_auth_devices`
Opaque device ID, subject ID, HMAC fingerprint/network bindings, generalized labels, trust status, bounded risk score and first/last-seen timestamps. **Classification:** Security / Derived-minimized.

### `wp_sauth_auth_risk_challenges`
Opaque challenge ID, HMAC token binding, subject ID, HMAC fingerprint binding, bounded risk score/reason category, safe destination, sanitized completion state, status/attempts/expiry. **Classification:** Security. Short-lived.

### `wp_sauth_auth_attempts`
Opaque attempt ID, subject ID where known, HMAC fingerprint/network bindings, result, reason category, risk score and timestamp. **Classification:** Security / Derived-minimized. Subject bindings are anonymized on privacy erasure and records expire.

### `wp_sauth_passkeys`
File 02 WebAuthn credential registry. Fields include opaque public row UUID, WordPress subject ID, stable SHA-256 of the random WebAuthn credential ID for lookup, encrypted base64url credential ID for exclusion/presentation, server-derived SPKI public key, approved algorithm (`-7` ES256 or `-257` RS256), bounded transports, authenticator attachment, discoverable/backup flags, conservative `hardware_backed` evidence flag, signature counter, user nickname, status and lifecycle timestamps. **Classification:** Account-private / Security.

Passkey privacy law:
- no private key or biometric template is ever received or stored;
- attestation object is verified during registration but not retained;
- `attestation=none` means hardware provenance is not asserted;
- credential IDs are opaque random identifiers, not platform identities;
- revoked/compromised credentials are retained only for bounded security evidence then cleaned according to policy.

## Canonical WordPress options

- `sauth_version`, `sauth_db_version` — runtime/schema versions.
- `sauth_page_map` — managed File 02 page IDs including passkey manager.
- `sauth_google_enabled`, `sauth_google_client_id` — non-secret provider configuration.
- `sauth_google_client_secret` — AES-256-GCM `v3:` provider-secret envelope derived only from a dedicated `SA_MASTER_KEY`; legacy salt-derived ciphertext is migration input only.
- `sauth_dummy_password_hash` — dummy WordPress password hash used for anti-enumeration timing.
- `sauth_safe_mode` — reversible operational gate.
- `sauth_rewrite_version` — canonical nested-route rewrite version.
- `sauth_legacy_table_migration_version` — completed compatibility-copy version.
- `sauth_passkey_schema_version` — File 02 passkey table schema version.
- `sauth_pk_claim_*` — short-lived atomic replay-claim options for completed WebAuthn challenges; cleanup removes stale claims.

Legacy `sa_*` options are bounded mirrors/read fallbacks only and are not canonical 1.3.0 configuration names.

## File 02-owned user metadata

- Google link projection: provider subject mapping, verified matching email, link version/timestamps and optional picture candidate. It never grants platform identity, role or verification.
- `_sauth_passkey_user_handle_v1`: random opaque WebAuthn user handle. It is not a WordPress user ID, email, phone or deterministic salt-derived identity. It is removed by File 02 passkey privacy erasure.
- `_sauth_passkey_assurance_epoch_v1`: File 02 passkey-assurance invalidation epoch. It is rotated/revalidated for credential changes and removed by passkey privacy erasure.

## File 00-owned private registration and trust state

Through `smc.authentication-account 1.1.0`, File 00 owns and protects city, declared account type, Ethical Conduct consent, profile-photograph completion requirement, age/guardian context, identity evidence and completion truth. File 02 passes validated data but does not become a second source of truth.

File 00 Advanced Trust also remains the policy owner for MFA/identity assurance. File 02 may expose only a fresh, session-bound versioned passkey authentication-assurance projection; File 00 independently determines whether that evidence is sufficient for a protected action.

## Storage migration and rollback compatibility

Pre-1.1 installations may contain corresponding `wp_sa_*` tables. Activation/repair creates the canonical `wp_sauth_*` baseline and copies legacy rows idempotently through `INSERT IGNORE` while compatibility routing is explicitly suspended. DB `1.3.0` reruns the repaired copy and proves stable logical identities after it. Version 1.2.0 added `wp_sauth_passkeys` without rewriting File 00 data; current hardening still reconciles legacy passkey columns `credential_hash` / `credential_cipher` into canonical `credential_lookup_hash` / `credential_id_ciphertext` before readiness can succeed. Legacy tables and source columns are not destructively purged automatically and remain rollback evidence until a separately approved purge.
