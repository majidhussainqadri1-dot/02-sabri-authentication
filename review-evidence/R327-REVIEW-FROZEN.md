# R327 — Complete Review Frozen Before Correction

Review scope: WordPress privacy export/erasure, asynchronous recovery/resend privacy barriers, File 02 event outbox persistence/dispatch/anonymization, retained event evidence, and R307/R317 privacy regressions.

No R327 product correction was started before this ledger was frozen.

## Frozen defect ledger

1. **High-volume authentication-event erasure can terminate as incomplete instead of continuing pagination.** The outbox anonymization loop processes at most 5,000 matching rows per table, then treats any remaining matching rows as a generic failure and returns `done => true`. Under the WordPress eraser contract, a user with more than 5,000 matching File 02 events can therefore stop after the first batch with retained direct actor/subject identifiers and no automatic next page.

## Evidence considered

- The erasure barrier is raised before destructive work and intentionally remains raised on failure.
- Recovery/resend jobs carry a privacy epoch and are invalid after erasure rotates that epoch.
- Current File 02 outbox emitters use privacy-minimized payloads; no unsupported claim was made that current event payloads contain direct email/login fields.
- The defect is specifically the 5,000-row continuation contract: remaining rows are known work, not an unknown fatal condition.

## Correction requirements

- Distinguish a real DB/postcondition failure from a valid “more outbox rows remain” condition.
- Keep the privacy barrier raised and return `done => false` when another outbox batch is required.
- Do not clear the barrier until all direct actor/subject identifiers are anonymized and every other erasure postcondition has passed.
- Add permanent R327 regression coverage for this continuation behavior.
