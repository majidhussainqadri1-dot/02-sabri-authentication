# R327 correction checkpoint

- Complete R327 review was frozen before correction in `R327-REVIEW-FROZEN.md`.
- Frozen-ledger correction completed at `4bed0dd6548d4dac8b2426cc1ea9eb8c1fe90dd9` after cumulative lint/regression success.
- High-volume outbox anonymization now distinguishes “more rows remain” from DB failure, keeps the erasure barrier raised, and returns `done=false` for continuation.
- This checkpoint changes no product code and triggers the permanent PHP 7.4/PHP 8.3 exact-head review gate before R328.
