# File 02 v12 — Run 8: CI wiring for recovery/collision one-time claim gate

## Primary scope
Master Plan v12, File 02 p.84: F02-X24-011 (canonical-owner mediated cooling-off pending recovery; 1h–7d default 24h), F02-X24-012 (one-time fingerprint-bound collision resolution; no silent merge). Original v12 Library document was independently located/read via indexed passage this run. No v13.

## Immutable references
- Repository main SHA (rechecked): `224c39bcb8c28f77504c7348dbad41226753c7e8`.
- Candidate PR #18 HEAD (rechecked): `40aa85f2781855cb696a864f202ef796cd96b336` (open; not merged).
- Prior audit PR #21 HEAD: `dca6cb24f12e888a8dc3ab875a0dd50bdf2d07e8`.
- This run CI-workflow commit: `2bd45c0cef40cf08a0288131e543f9f5b467d381`.
- Source blob `includes/class-sauth-security-orchestrator.php`: `2d01c5ad20ce52b46ed6de13a6bad79d5ec06c0a`.
- Structural test blob `tests/recovery-once-claim-gate.php`: `4805c0178dbd6965876b681eda84867f33d9cd7a`.
- Candidate source 1.4.0 / candidate DB 1.4.0 / passkey schema 1.1.0 (from earlier traceability; no deployed parity).

## Actual change
Created `.github/workflows/file02-recovery-claim-gate.yml` on PR #21's existing review branch. The workflow invokes PHP 8.3 lint and `php tests/recovery-once-claim-gate.php`. The existing source is expected to FAIL the ordering gate; this is a deliberately red fail-first gate, **not a passing security fix**. Caveat: a workflow added only on a PR branch may not run as a pull_request event until present on the base/default branch; this run did not establish execution of this new workflow. No main merge/deploy was performed.

## Source audit / evidence classes
- F02-X24-011: **A** source ordering defect: `apply_recovery_change` invokes canonical-owner filter before conditional `pending -> applied` update. Impact depends on consumer side effects and reentrancy.
- F02-X24-012: **A** source ordering defect: `resolve_collision_case` invokes canonical-owner filter before conditional update. Fingerprint and owner token are checked before filter, but one-time effect remains unproven.
- **B** Structural gate exists but no fresh executed result or real two-connection WordPress/MariaDB regression; existing CI for candidate is historical, not this new head.
- **C** Live deployment, actual schema, migration parity, staging E2E, Founder acceptance all unverified.

## Fresh checks
At commit `2bd45c0cef40cf08a0288131e543f9f5b467d381`, GitHub check-runs API returned 3 queued checks: PHP 8.3 lint/cumulative regressions, PHP 7.4 lint/cumulative regressions, exact-head source/release constitution. **0 passed, 0 failed at query time; 3 queued**. The new structural workflow did not appear in that check-run list. No local PHP execution or MariaDB integration executed in this run. New source fixes: 0; new defects: 0; existing source-ordering defects still open: 2.

## Next exact action
First obtain actual CI result for PR #21 head and run the structural gate in an environment that can execute it (do not claim pass). Then inspect File 00 canonical-owner consumer behavior and define an atomic, durable claim with crash/retry/idempotency semantics; write a two-connection reentrant WordPress/MariaDB test before minimal production source patch. Do not merge a red gate. Review whether PR-only workflow registration requires a safe alternative for CI execution.

## Five provenance fields
Repository HEAD: main `224c39bcb8c28f77504c7348dbad41226753c7e8`; audit branch before this note `2bd45c0cef40cf08a0288131e543f9f5b467d381`.
Deployed Version: unknown.
DB Version: live unknown; candidate declared 1.4.0.
Migration State: live unverified; no migration executed.
Live Verification Status: not performed; exact deployed code unverified.
