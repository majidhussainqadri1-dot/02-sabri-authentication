# File 02 v12 — Run 11: atomic one-time claim source fix (2026-11-05)

## Authority / traceability
Original Library file `Sabri-Website-Master-Plan-v12.docx` retrieved this run, p.84:
- **F02-X24-011** recovery cooling-off 1h–7d, default 24h, canonical owner mediated pending change.
- **F02-X24-012** one-time fingerprint-bound collision resolution, no silent merge.
Both are **A** (previously demonstrated pre-claim owner callback ordering defect); 46 other identified requirements are not audited in this run. **B** behavioral concurrency/crash integration pending; **C** staging/live/Founder pending.

## Frozen evidence and actual change
Repo `majidhussainqadri1-dot/02-sabri-authentication`, Draft PR #21 branch `audit/file02-recovery-failfirst-20261105`, base `codex/file02-modern-auth-24-1.4.0`. Previous PR HEAD `9d01ecfb4529096f91ca1f7d35b298507961a104`; source blob `2d01c5ad20ce52b46ed6de13a6bad79d5ec06c0a`. New production source commit **`3b8e4d3b0fbc1bbf309d529a775b3949742aacb2`**, new orchestrator blob **`5b69387fcb58d1199bca7e545819e32026cc011d`**.

Root cause: both `apply_recovery_change` and `resolve_collision_case` invoked a side-effect-capable File 00 canonical owner filter while the record was still pending; a later conditional applied update could not prevent duplicate callbacks. Minimal patch: conditional `pending -> processing` DB update before each callback; reject when zero rows claimed; conditional `processing -> applied` after owner success. No automatic processing-state retry, deliberately fail closed because owner-side effect completion may be ambiguous after a crash. This **does not** guarantee exactly-once external side effects, recover abandoned processing automatically, or establish File 00 idempotency. A manual reconciliation protocol, owner callback failure handling and real DB tests are still required before acceptance.

## QA evidence / limits
Prior exact `9d01ecfb4529096f91ca1f7d35b298507961a104` PR-triggered CI: 4 successful workflows (41954611593, 41954611602, 41954611627, 41954611612); 1 failed (41954611557), whose PHP syntax step passed and structural gate failed; behavioral harness skipped. On this run local git clone blocked by github.com DNS; local PHP 8.4.24 exists, but no checkout or executed tests. Fresh CI for new source commit had **zero workflow runs returned at initial check**. Do not count tests as passing. No WP/MariaDB concurrency test, schema migration, ZIP checksum, staging, deployment, or live verification.

## Source/schema and remaining work
Candidate plugin v1.4.0, DB source v1.4.0, passkey schema v1.1.0 are historical declared versions, not live versions. No new schema fields or migration introduced: `status` varchar(24) supports `processing`. File 00 owner consumer semantics still unverified. At least 2 requirements remain **not accepted**, despite source-ordering fix candidate. Next: collect fresh CI for source commit, inspect File 00 consumers at immutable SHAs, build real WP/MariaDB two-connection race/crash/rollback regression, test ambiguous owner failures and reconciliation, rerun historical suites and packaging checks. Keep PR draft, main/live unchanged.

## Five fields
Repository HEAD: candidate source commit `3b8e4d3b0fbc1bbf309d529a775b3949742aacb2` (before this note); fresh main SHA not independently verified.
Deployed Version: unverified.
DB Version: live unverified.
Migration State: no migration in this run; live unverified.
Live Verification Status: not performed.
Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
