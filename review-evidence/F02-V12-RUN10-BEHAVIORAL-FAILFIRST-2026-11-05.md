# File 02 v12 — Run 10, behavioral race-loser gate (2026-11-05)

## Authority and requirement traceability
Source: Sabri-Website-Master-Plan-v12.docx, File 02, F02-X24-011 (canonical-owner-mediated recovery cooling-off, 1h–7d/default 24h), F02-X24-012 (one-time fingerprint-bound collision resolution, no silent merge). These are the 2 requirements inspected this run; the other 46 identified File 02 requirements remain outside this run's acceptance.

## Frozen GitHub evidence
- Repository: majidhussainqadri1-dot/02-sabri-authentication, main base of PR #18: 224c39bcb8c28f77504c7348dbad41226753c7e8 (PR metadata, not a newly read main branch tip).
- PR #18 candidate HEAD: 40aa85f2781855cb696a864f202ef796cd96b336, open, unmerged.
- PR #21 previous HEAD: 570284f2ce1873e4000a5d67898f5cefb156d2ff, draft, open; base PR #18.
- New test commit: 6c39ac12e3d93dd422f3a4c02af8872afbb4d8f8.
- New workflow commit: 3307a7d06f38fd2a93b8c765e7482a9cd9f286c2.
- Orchestrator blob on previous HEAD: 2d01c5ad20ce52b46ed6de13a6bad79d5ec06c0a; no production source change.
- Structural test blob: 4805c0178dbd6965876b681eda84867f33d9cd7a.
- Historical workflow 41939569399 job 126956587606: PHP syntax passed; structural claim ordering regression failed on the PR merge checkout 72debadbb37d6572116723dfdba8f529a111db9d. Four other historical workflow conclusions were reported success, but not retested here.

## Source observation and classification
(A) Both `apply_recovery_change` and `resolve_collision_case` call side-effect-capable canonical owner filters before a conditional pending->applied update; a losing concurrent worker may already have invoked the callback. Confirmed source-ordering defect. Actual double mutation requires owner-consumer and real DB proof.
(B) No real WordPress/MariaDB two-connection reentrancy or crash-recovery test yet. New behavioral test is a PHP mock, NOT production integration.
(C) Deployed artifact, live DB version, migrations, staging, browser, and Founder acceptance unverified.

## Actual work
- Added `tests/recovery-claim-reentrancy.php`: stubs WordPress dependencies and models an update/query returning zero (another worker won). Requires neither owner hook to be invoked by the losing worker, requires a claim attempt, and requires a failure result. Current source is expected to fail this test.
- Updated `.github/workflows/file02-recovery-claim-gate.yml` to lint and execute this new mock harness, with path trigger.
- No production fix, schema migration, main merge or deployment.
- Local PHP 8.4.24 exists but local git clone failed due DNS inability to resolve github.com. No local test execution, and fresh CI for the two commits has not been verified in this note.

## Next exact action
Check the new workflow's actual run and logs on PR #21 (and dispatch if the PR-path workflow does not start). Inspect File 00 canonical callback consumers and side-effect/idempotency semantics at immutable SHAs. Design atomic pending->processing claim with crash-safe, fail-closed recovery and ownership rules, then implement minimal source fix, real WP/MariaDB two-connection regression, rollback/crash tests, all historical regressions and CI. Do not label the mock as concurrency acceptance or claim 100% completion.

## Five-state boundary
Repository: PR #21 new HEAD 3307a7d06f38fd2a93b8c765e7482a9cd9f286c2 (before this note); current main tip not reverified.
Deployed version: unknown. DB version: live unknown; candidate source 1.4.0. Migration state: live unknown; no new migration. Live verification: not performed.
Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
