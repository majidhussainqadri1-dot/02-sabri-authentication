# File 02 Data Dictionary — Version 1.0.0

## Classification legend

- **Security** — restricted operational evidence; never public or searchable.
- **Account-private** — visible only to the subject and authorized operators.
- **Derived-minimized** — HMAC, generalized label or bounded category; not raw source data.
- **Operational** — non-secret health/queue state.

## Tables

### `wp_sa_rate_limits`

Atomic fixed-window abuse counters keyed by an HMAC bucket. Stores hit count, window start, expiry and update time. **Classification:** Security / Derived-minimized. Short-lived.

### `wp_sa_auth_outbox`

Versioned authentication events with event UUID, trace ID, privacy class, actor/subject IDs, sanitized JSON payload, delivery state, retry count and timestamps. **Classification:** Security. No secrets permitted.

### `wp_sa_email_verifications`

One row per user: HMAC email binding, HMAC token binding, status, attempts, sent/expiry/verified timestamps. Raw email and raw token are not stored in this table. **Classification:** Account-private / Derived-minimized.

### `wp_sa_auth_sessions`

Opaque public session ID, subject ID, HMAC session-token binding, HMAC device binding, generalized device/network labels, risk band, status, expiry/revocation/activity timestamps. **Classification:** Account-private / Derived-minimized.

### `wp_sa_auth_devices`

Opaque device ID, subject ID, HMAC fingerprint/network bindings, generalized labels, trust status, bounded risk score and first/last seen timestamps. **Classification:** Security / Derived-minimized.

### `wp_sa_auth_risk_challenges`

Opaque challenge ID, HMAC token binding, subject ID, HMAC fingerprint binding, bounded risk score/reason category, safe destination, sanitized completion state, status/attempts/expiry. **Classification:** Security. Short-lived.

### `wp_sa_auth_attempts`

Opaque attempt ID, subject ID where known, HMAC fingerprint/network bindings, result, reason category, risk score and timestamp. **Classification:** Security / Derived-minimized. Subject bindings are anonymized on privacy erasure and records expire.

## WordPress options

- `sa_version`, `sa_db_version` — runtime/schema versions.
- `sa_page_map` — managed File 02 route page IDs.
- `sa_google_enabled`, `sa_google_client_id` — non-secret provider configuration.
- `sa_google_client_secret` — AES-256-GCM encrypted secret; never autoloaded into public output.
- `sauth_dummy_password_hash` — dummy WordPress password hash used for anti-enumeration timing.
- `sauth_safe_mode` — reversible operational gate.

## User metadata owned by File 02

Google link metadata only: provider subject mapping, verified matching email, link version/timestamps and optional profile-image URL. File 02 does not own membership, role, guardian, identity-document, institutional or verification truth.
