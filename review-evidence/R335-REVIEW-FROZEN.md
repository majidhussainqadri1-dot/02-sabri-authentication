# R335 — Legacy Passkey Index-Name Collision Review Frozen Before Correction

Reviewed exact File 02 head: `9bcadde28d27136ad823f26312239a810e297e26`.

Exact paired File 00 head: `1d7f215193d778b0977c8e50d738c42e1e5f66c2` / runtime `1.2.44`.

Exact real WordPress 7.0 / MariaDB 11.4 integration run: `31849716761`, job `94923067583`.

No R335 correction was started before this ledger was frozen.

## Evidence established before this review

The R334 correction successfully removed the earlier dbDelta malformed-primary-key failure. On exact `1.2.5`, the integration now passes all earlier stages: File 00 queued activation, supported deferred administrator bootstrap to DB `1.4.5`, File 02 fresh activation, File 02 DB `1.2.1`, passkey schema `1.0.1`, and two-sided canonical account-taxonomy parity.

The supported legacy passkey-column upgrade then fails with a new, narrower MariaDB error:

`Duplicate key name 'credential_lookup_hash' ... ALTER TABLE wp_sauth_passkeys ADD UNIQUE KEY credential_lookup_hash (credential_lookup_hash)`

The fixture reaches this state by renaming canonical columns back to legacy names with:

`CHANGE credential_lookup_hash credential_hash ... CHANGE credential_id_ciphertext credential_cipher ...`

and then reactivating File 02 so its supported migration path reconciles the legacy shape.

## Exact source reviewed

`SAUTH_Passkeys::prepare_legacy_credential_columns()` currently:

1. detects legacy `credential_hash` / `credential_cipher` columns;
2. adds new canonical `credential_lookup_hash` / `credential_id_ciphertext` columns when absent;
3. copies legacy values into the canonical columns;
4. then `dbDelta()` is allowed to reconcile the canonical CREATE TABLE and indexes.

The code does not inspect or reconcile pre-existing index names before `dbDelta()`.

## Frozen defect hypothesis requiring direct schema proof

MariaDB may preserve the old unique index name `credential_lookup_hash` when the indexed column is renamed from `credential_lookup_hash` to `credential_hash`. If so, after File 02 adds a new canonical column named `credential_lookup_hash`, the legacy table already contains an index **named** `credential_lookup_hash` but pointing at the legacy `credential_hash` column. `dbDelta()` then attempts to create the canonical unique index with the same key name, causing the proven duplicate-key-name failure.

This hypothesis is strongly indicated by the exact error but is **not yet accepted as root cause until `SHOW INDEX` / `SHOW COLUMNS` proves the actual MariaDB state**.

## Required diagnosis before any product correction

1. In the exact integration fixture, after the legacy `CHANGE` and before File 02 reactivation, capture non-secret `SHOW COLUMNS FROM wp_sauth_passkeys` and `SHOW INDEX FROM wp_sauth_passkeys`.
2. Prove whether key name `credential_lookup_hash` remains while its indexed `Column_name` is `credential_hash`.
3. If proven, correct only File 02 migration ownership: reconcile stale/misbound legacy index names before dbDelta without deleting valid canonical evidence or weakening uniqueness.
4. Add permanent regression coverage for the exact stale-index-name collision.
5. If runtime migration semantics change, use a distinct runtime identity; DB/passkey schema identities may remain unchanged only if the intended physical schema shape remains unchanged.
6. Re-run complete source regression and the entire exact WordPress/MariaDB integration sequence before clearing any blocker.

Staging-Accepted, Live-Deployed and Operational remain unclaimed.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
