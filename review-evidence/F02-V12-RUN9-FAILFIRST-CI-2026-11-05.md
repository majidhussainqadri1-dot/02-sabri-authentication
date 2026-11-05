# File 02 v12 — Run 9: confirmed remote fail-first CI (2026-11-05)

## Immutable source and scope
- Primary clauses carried forward from Run 6 direct v12 DOCX read: F02-X24-011 and F02-X24-012. This run did not reopen the DOCX; no other clauses assumed.
- Main baseline per PR #18: `224c39bcb8c28f77504c7348dbad41226753c7e8` (PR base, **not** independently verified as current main).
- PR #18 candidate head `40aa85f2781855cb696a864f202ef796cd96b336`; open, unmerged.
- PR #21 draft head `9f7589ff4fcd825b6b5d4572f8449ecf88e98221` at inspection; base PR #18 head; open, unmerged.
- Source orchestrator blob `2d01c5ad20ce52b46ed6de13a6bad79d5ec06c0a`; test blob `4805c0178dbd6965876b681eda84867f33d9cd7a`.
- Candidate release 1.4.0, DB 1.4.0, passkey schema 1.1.0 as previously documented, not live verified.

## New verified CI evidence (PR #21 exact head)
GitHub Actions for commit `9f7589ff4fcd825b6b5d4572f8449ecf88e98221`:
- `41939569349` File 02 Release Integrity: SUCCESS
- `41939569393` File 00 1.2.44 Integration: SUCCESS
- `41939569452` Canonical Storage and Documentation: SUCCESS
- `41939569429` Review Branch Integrity: SUCCESS
- `41939569399` **File 02 recovery one-time claim regression: FAILURE**.
- Failure job `126956587606`: PHP syntax validation SUCCESS (step 4); fail-first ordering regression FAILURE (step 5). This is **expected red evidence** confirming the structural gate executes; not a production runtime exploit or a completed fix. Four workflow successes do not negate the fifth failure. Individual assertions and live parity not counted.

## Source reasoning / traceability
- A / F02-X24-011: `apply_recovery_change()` invokes `sauth_apply_recovery_change_v1` before conditional `pending -> applied` update.
- A / F02-X24-012: `resolve_collision_case()` invokes `sauth_resolve_collision_case_v1` before conditional `pending -> applied` update.
- B: Structural PHP gate checks textual pre-hook claim ordering; no two-connection WP/MariaDB behavioral concurrency test. The gate could be fooled by irrelevant textual matches, so no green gate would establish safety alone.
- C: Canonical File 00 hook consumer idempotency, side-effect/rollback guarantees, schema migration/lease recovery, staging, founder acceptance, and deployed runtime remain unverified.
- This run: 2 requirements re-examined; 0 newly discovered defects; 2 known ordering gaps still open; 0 source fixes; 0 new test files; 5 remote CI workflows inspected (4 success, 1 failure), 1 failed job and its failing step confirmed.

## Next exact action
1. Inspect canonical File 00 hook consumers and confirm owner operation semantics before modifying recovery/collision.
2. Build real WP/MariaDB re-entrant and crash/failure-path tests, prove fail-first in CI.
3. Implement minimal atomic pending->processing claim before callbacks, with a governed retry/lease and durable idempotency strategy; avoid unsafe reset to pending after possible side effects. If owner callbacks are not idempotent, coordinate canonical owner contract rather than masking with a local status flip.
4. Rerun relevant PHP/static, integration, adversarial, release and package checks. Keep PR draft and main/live untouched.

## Five-field truth
Repository HEAD: PR #21 inspected `9f7589ff4fcd825b6b5d4572f8449ecf88e98221`; PR #18 `40aa85f2781855cb696a864f202ef796cd96b336`; current main unknown.
Deployed Version: unknown.
DB Version: live unknown (candidate declared 1.4.0).
Migration State: no migration executed in this run; live unknown.
Live Verification Status: not performed. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
