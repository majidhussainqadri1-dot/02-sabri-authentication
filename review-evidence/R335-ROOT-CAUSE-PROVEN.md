# R335 — Legacy Passkey Index-Name Root Cause Proven Before Correction

Focused MariaDB 11.4 diagnostic run: `31849891214`, job `94923561464`, exact diagnostic head `8d0469045abfc9f2cef38d22a5988ac13da51295`.

No R335 product correction was started before this evidence was recorded.

## Direct schema evidence

The diagnostic recreated the exact canonical passkey column/index pair and applied the same legacy-column `CHANGE` used by the failing WordPress/MariaDB integration fixture.

After:

`CHANGE credential_lookup_hash credential_hash ...`

MariaDB reported the following material index binding:

`credential_lookup_hash => credential_hash / non_unique=0`

Therefore MariaDB **preserves the unique index name `credential_lookup_hash` while retargeting that existing index to the renamed legacy column `credential_hash`**.

The diagnostic then added a new canonical column named `credential_lookup_hash` and attempted the exact canonical index creation:

`ADD UNIQUE KEY credential_lookup_hash (credential_lookup_hash)`

MariaDB raised:

`Duplicate key name 'credential_lookup_hash'`

The diagnostic workflow itself concluded as failure because PHP mysqli threw on the expected duplicate-index DDL before the script's final catch logic; that harness-exit detail does not weaken the root-cause evidence because both the `SHOW INDEX` mapping and exact duplicate-key exception are present in the job log.

## Proven root cause

`SAUTH_Passkeys::prepare_legacy_credential_columns()` reconciles legacy column names but does not reconcile an index whose **name is canonical while its bound column has become legacy**. `dbDelta()` later sees the canonical index missing by column binding and tries to create it under the already-occupied canonical key name, causing activation/upgrade failure.

## Owning correction

File 02 must reconcile this stale/misbound legacy index **before dbDelta**. The correction must:

1. inspect the actual key name, uniqueness and ordered indexed columns;
2. mutate only the proven legacy case: unique key named `credential_lookup_hash` bound exactly to `credential_hash`;
3. fail closed on an unexpected conflicting binding rather than dropping an unknown index;
4. preserve legacy uniqueness while freeing the canonical key name, preferably by atomically replacing the stale key with a clearly legacy-named unique key on `credential_hash`;
5. let dbDelta create/verify the canonical unique `credential_lookup_hash (credential_lookup_hash)` index;
6. be idempotent on retry and preserve data;
7. add permanent regression coverage plus a full exact WordPress/MariaDB retest.

Because this changes runtime migration semantics after 1.2.5, the corrected runtime will use a distinct `1.2.6` identity. DB schema remains `1.2.1`, passkey schema remains `1.0.1`, and passkey assurance contract remains `1.0.0` because the intended physical schema and protocol contracts do not change.

Staging-Accepted, Live-Deployed and Operational remain unclaimed.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
