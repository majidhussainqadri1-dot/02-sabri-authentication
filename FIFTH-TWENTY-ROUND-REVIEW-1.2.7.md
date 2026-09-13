# File 02 — Fifth Twenty-Round Review — R81–R100 — Review-Only 1.2.7

**Method:** every round completed its full review before any correction began. The completed round's full defect ledger was then corrected together, cumulative regression rerun, and only then did the next round begin.

| Round | Result | Finding / correction after full round |
|---|---|---|
| R81 | DEFECT | Google registration stale claim/link-lock ownership race → exact-value compare-and-delete. |
| R82 | DEFECT | Schema upgrade stale lock-owner deletion race → conditional compare-and-delete. |
| R83 | DEFECT | Outbox could rewrite occurrence history, publish corrupt JSON, and allow stale worker state overwrite → immutable storage metadata + corrupt-payload failure + attempt-generation-bound updates. |
| R84 | DEFECT | Guarded repair could claim success when provider health reset was not durably cleared → read-back reset verification and aggregate failure. |
| R85 | DEFECT | Rate-limit reset persistence could fail silently; old R15 static assertion was stale → observable/read-back reset and semantic regression update. |
| R86 | DEFECT | Google last-login projection was unverified and unlink session pruning could throw after mutation → read-back evidence + Throwable containment. |
| R87 | DEFECT | Session cookie projection lacked durable postconditions → exact row read-back. |
| R88 | DEFECT | File 02 duplicated age/guardian/professional thresholds and timezone semantics → consume File 00 `smc_effective_minimum_age`, `smc_policy` and WordPress timezone. |
| R89 | CROSS-FILE BLOCKER | File 00 core account taxonomy differs from its current v1.1 account provider / older File 02 declaration vocabulary → no lossy remap; explicit release block owned at File 00 provider boundary. |
| R90 | DEFECT | Google registration proof equality omitted name/picture → full mutation-relevant context binding. |
| R91 | CLEAN | Cryptographic entropy/fallback review found no new defect. |
| R92 | CLEAN | Provider-health circuit behavior remained availability-only; auth boundaries still fail closed. |
| R93 | CLEAN | Generic File 02 privacy exporter remained bounded/pageable/fail-closed. |
| R94 | DEFECT | File 00 completion allow response lacked requested-subject binding → exact `user_id` check / `provider_subject_mismatch`. |
| R95 | DEFECT | CF-01 membership allow could be trusted without full contract/action/purpose/UUID validation → strict assertion validation + File 00 UUID binding. |
| R96 | CLEAN | Provider-failure observability review found no additional justified source correction. |
| R97 | DEFECT | Passkey privacy export was unbounded and over-selected credential columns → 50-row pagination + minimal metadata. |
| R98 | CLEAN | Passkey erasure/assurance cleanup remained fail-closed and containment-aware. |
| R99 | DEFECT | Google settings could report success despite provider-health reset persistence failure → explicit failure. |
| R100 | DEFECT | R81–R99 lacked cumulative regression/report/manifest; current identity/docs/SBOM/lineage were stale at 1.2.6; prior regressions froze that old current version; R89 was not in structured release block → added fifth regression/evidence, advanced only the local review identity to 1.2.7, aligned local current evidence and recorded R89 blocker. |

## Accounting

- Repository/code defect rounds: **R81, R82, R83, R84, R85, R86, R87, R88, R90, R94, R95, R97, R99, R100**.
- Cross-file release-blocking discrepancy: **R89**.
- Clean rounds: **R91, R92, R93, R96, R98**.
- Therefore **15/20** rounds found a defect or release-blocking discrepancy; **5/20** were clean.

## Local closure evidence

- Local corrected review identity: `1.2.7 / DB 1.2.0`.
- Local candidate commit: `0da07b310b639ffe229c614c6c61e8dfabd37ee5`.
- PHP lint: `43/43 PASS`.
- JavaScript syntax: `1/1 PASS`.
- Ten-round + R1–R20 + R21–R40 + R41–R60 + R61–R80 + R81–R100 regressions: PASS.
- `git diff --check`: PASS.
- Conflict markers: 0.
- Unsupported File 00 membership-action runtime calls: 0.
- Fifth corrective runtime/tests patch SHA-256: `1eb6b35f07c031acc60e4e5a916a3c4d8f8b0d18ff8552397be4223be947e807`.
- Corrected PHP/JS tree SHA-256: `c5c25e5f0a18059d91605687fa7bc203dec1dfb08882767dcde654e89fd6dd3b`.
- DO-NOT-INSTALL review bundle SHA-256: `6ceba6d137d318bef09431c48bffaa59de32881d6b1373c9fbd0d3514f002db0`.

These runtime/test results are **local review-workspace evidence**. The corrected 1.2.7 runtime source is deliberately absent from this GitHub PR, so workflow checks on this PR do not validate local 1.2.7.

## Release truth

The corrected 1.2.7 runtime source is deliberately **not** applied to this GitHub review branch or `main`. The approved later File 02 corpus records `1.3.8 / DB 1.3.0 / passkey schema 1.1.0` F02-X24 source lineage, whose exact source bytes are not currently recovered. R89 also leaves an unresolved canonical File 00 account-taxonomy/provider contract discrepancy. Required sequence: recover exact 1.3.8-or-later source → verify hashes/manifest → resolve File 00 taxonomy/provider contract → reconcile R1–R100 hardening without dropping X24 scope → exact-head/package/WordPress+MariaDB/upgrade/rollback/traceability QA → staging → separately authorized live re-test.

The reported website outage remains **OPEN / NOT RESOLVED**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
