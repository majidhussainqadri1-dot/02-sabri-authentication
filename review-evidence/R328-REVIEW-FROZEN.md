# R328 — Complete Review Frozen Before Correction

Review scope: activation/upgrade/repair, canonical and legacy storage, table/column/index postconditions, managed pages, Google-secret migration, storage-router compatibility, uninstall/rollback policy, and migration regressions/docs.

No R328 product correction was started before this ledger was frozen.

## Frozen defect ledger

1. **Legacy migration copies auto-increment row IDs and uses `INSERT IGNORE`, so an unrelated canonical ID collision can silently drop a distinct legacy record.** The affected evidence tables also have stable logical unique identities (`event_id`, `public_id`, etc.); copying the legacy numeric `id` is unnecessary and can turn an unrelated ID collision into silent evidence loss.
2. **Legacy migration publishes its success marker without proving every legacy logical identity exists in canonical storage.** `INSERT IGNORE` can succeed while skipping a row because of another unique-key conflict; the code checks only `false === $result`. The migration can therefore claim success despite an unrepresented legacy event/session/device/challenge/attempt identity.

## Correction requirements

- Do not copy legacy auto-increment IDs into canonical evidence tables; let canonical storage allocate its own numeric IDs.
- Define and verify the stable logical identity for every legacy table after copy (`bucket_hash`, `event_id`, `user_id`, or `public_id` as appropriate).
- Fail migration and withhold the successful migration/version marker if any legacy logical identity is absent from canonical storage or the reconciliation query is uncertain.
- Preserve canonical rows on duplicate logical identities; do not overwrite potentially newer canonical state with older legacy values.
- Add permanent R328 regression coverage and align storage migration tests/documentation with the logical-identity rule.
