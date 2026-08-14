# R334 — Real MariaDB Passkey Migration Defect Frozen Before Correction

Exact failing integration run: `31849148191`, File 02 head `69584f04ae8d34a61a8fcadb8537b9c6b07fa218`, exact File 00 head `1d7f215193d778b0977c8e50d738c42e1e5f66c2`.

Before this failure, the run proved the corrected File 00 lifecycle, File 02 activation, and two-sided canonical account taxonomy parity all pass on fresh WordPress 7.0 / MariaDB 11.4.

The next supported-upgrade rehearsal intentionally converted the File 02 passkey table from canonical columns `credential_lookup_hash` / `credential_id_ciphertext` to the legacy `credential_hash` / `credential_cipher` shape, reset File 02/passkey schema version markers, and reactivated File 02.

Reactivation failed inside `SAUTH_Passkeys::maybe_install()` / WordPress `dbDelta()` with MariaDB reporting:

`Key column 'UNIQUE' doesn't exist in table ... ALTER TABLE wp_sauth_passkeys ADD PRIMARY KEY (id,UNIQUE,UNIQUE,KEY,status,KEY)`

## Exact source root cause

`SAUTH_Passkeys::maybe_install()` emits all five index definitions on one SQL line:

`PRIMARY KEY (id), UNIQUE KEY public_id (...), UNIQUE KEY credential_lookup_hash (...), KEY user_status (...), KEY revoked_at (...)`

WordPress `dbDelta()` parses CREATE TABLE definitions line-by-line. Combining multiple key definitions on one line causes the first `PRIMARY KEY` definition to absorb later index tokens when reconciling an existing legacy table, producing the invalid ALTER statement proven by the real MariaDB run.

This is a **File 02 runtime/migration product defect**, not a File 00 defect and not merely a test-fixture defect.

No R334 product correction was started before this review ledger was frozen.

## Required correction

1. Put every passkey index definition on its own CREATE TABLE line so `dbDelta()` can reconcile legacy tables correctly.
2. Preserve physical DB identity `1.2.1`, passkey schema identity `1.0.1`, and passkey assurance contract `1.0.0`; the desired schema shape is unchanged.
3. Because runtime migration behavior changes materially, advance File 02 runtime identity from `1.2.4` to `1.2.5` and synchronize current release evidence/tests/workflows while keeping historical evidence immutable.
4. Add a permanent regression that proves dbDelta-compatible one-index-per-line formatting and rejects the former combined-key line.
5. Re-run complete source regression plus the full fresh WordPress/MariaDB sequence: File 00 deferred bootstrap, File 02 fresh activation, canonical taxonomy parity, legacy passkey-column upgrade, legacy logical-identity collision migration and final version/schema boundaries.
6. Staging/Live/Operational remain unclaimed.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
