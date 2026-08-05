# File 02 Data Dictionary — Version 1.1.0

## Classification legend

- **Security** — restricted operational evidence; never public or searchable.
- **Account-private** — visible only to the subject and authorized operators.
- **Derived-minimized** — HMAC, generalized label or bounded category; not raw source data.
- **Operational** — non-secret health/queue state.

`wp_` below represents the actual WordPress database prefix.

## Canonical File 02 tables

### `wp_sauth_rate_limits`

Atomic fixed-window abuse counters keyed by an HMAC bucket. Stores hit count, window start, expiry and update time. **Classification:** Security / Derived-minimized. Short-lived.

### `wp_sauth_auth_outbox`

Versioned authentication facts with event UUID, trace ID, privacy class, actor/subject IDs, sanitized JSON payload, delivery state, retry count and timestamps. **Classification:** Security. No secrets permitted.

### `wp_sauth_email_verifications`

One row per user: HMAC email binding, HMAC token binding, status, attempts, sent/expiry/verified timestamps. Raw email and raw token are not stored. **Classification:** Account-private / Derived-minimized.

### `wp_sauth_auth_sessions`

Opaque public session ID, subject ID, HMAC session-token binding, HMAC device binding, generalized device/network labels, risk band, status, expiry/revocation/activity timestamps. **Classification:** Account-private / Derived-minimized.

### `wp_sauth_auth_devices`

Opaque device ID, subject ID, HMAC fingerprint/network bindings, generalized labels, trust status, bounded risk score and first/last-seen timestamps. **Classification:** Security / Derived-minimized.

### `wp_sauth_auth_risk_challenges`

Opaque challenge ID, HMAC token binding, subject ID, HMAC fingerprint binding, bounded risk score/reason category, safe destination, sanitized completion state, status/attempts/expiry. **Classification:** Security. Short-lived.

### `wp_sauth_auth_attempts`

Opaque attempt ID, subject ID where known, HMAC fingerprint/network bindings, result, reason category, risk score and timestamp. **Classification:** Security / Derived-minimized. Subject bindings are anonymized on privacy erasure and records expire.

## Storage migration and rollback compatibility

Pre-1.1 installations may contain corresponding `wp_sa_*` tables. Activation/repair creates every canonical `wp_sauth_*` table and copies rows idempotently through `INSERT IGNORE`. `SAUTH_Storage_Router` rewrites retained compatibility queries to the canonical tables. Legacy tables are not deleted automatically; they remain rollback evidence until a separately approved purge.

## Canonical WordPress options

- `sauth_version`, `sauth_db_version` — runtime/schema versions.
- `sauth_page_map` — managed File 02 page IDs.
- `sauth_google_enabled`, `sauth_google_client_id` — non-secret provider configuration.
- `sauth_google_client_secret` — AES-256-GCM encrypted provider secret.
- `sauth_dummy_password_hash` — dummy WordPress password hash used for anti-enumeration timing.
- `sauth_safe_mode` — reversible operational gate.
- `sauth_rewrite_version` — canonical nested-route rewrite version.
- `sauth_legacy_table_migration_version` — completed compatibility-copy version.

Legacy `sa_*` options are bounded mirrors/read fallbacks only and are not the canonical 1.1.0 configuration names.

## File 02-owned user metadata

Google link projection only: provider subject mapping, verified matching email, link version/timestamps and optional provider picture candidate. This projection never creates a platform identity, account class, role or verification state.

## File 00-owned private registration state

Through `smc.authentication-account 1.1.0`, File 00 owns and protects city, declared account type, Ethical Conduct consent, profile-photograph completion requirement, age/guardian context, identity evidence and completion truth. File 02 passes validated data but does not become its second source of truth.
