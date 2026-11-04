# File 02 v12 — Run 4: recovery state-transition audit (2026-11-05)

## Governing source and boundary
Master Plan v12, File 02 page 84: F02-X24-009 emergency lockdown; F02-X24-010 downgrade protection; **F02-X24-011 recovery cooling-off, default 24h, range 1h–7d, canonical-owner mediated pending change**; F02-X24-012 one-time fingerprint-bound collision resolution with no silent merge. Companion File 02 v2.0 catalogue corroborates these IDs. This note is a source-review finding, **not** a completed fix or a passing regression.

## Immutable inspected sources
- Repository: majidhussainqadri1-dot/02-sabri-authentication, PR #18 candidate commit `40aa85f2781855cb696a864f202ef796cd96b336` (historical inspected; branch tip may have changed).
- `includes/class-sauth-security-orchestrator.php` blob `2d01c5ad20ce52b46ed6de13a6bad79d5ec06c0a`; reviewed methods `schedule_recovery_change`, `cancel_recovery_change`, `apply_recovery_change`, `resolve_collision_case`.
- `tests/modern-auth-24-unit.php` blob `9777d5442ebf43888496d0d0c572b0d31b7f2ab7`. X-QA-24-06 only asserts source markers for cooling-off, owner token and hook; it does **not** execute a duplicate/concurrent application.
- `PLAN-TRACEABILITY.md` blob `58a2658841cca32429653be9005522be5a99f574`.
- Main baseline from prior verified run `224c39bcb8c28f77504c7348dbad41226753c7e8`; **current main tip not freshly established**.
- Previous 4 successful workflows / 8 jobs at candidate SHA are **historical evidence**, not new test executions.

## Security finding: F02-X24-011 (A: source-proven order-of-operations defect)
`apply_recovery_change()` reads a pending row, decrypts owner token, then calls the **side-effect-capable** canonical-owner hook `apply_filters('sauth_apply_recovery_change_v1', ...)` *before* attempting the conditional `UPDATE ... WHERE public_id=? AND status='pending'` to mark it applied. Thus two overlapping callers can both read pending and invoke the canonical handler before either marks the row applied; one later CAS fails, but the handler has already run twice. A sequential re-entrant hook can reproduce this without threads: during the first hook invocation, invoke `apply_recovery_change` again with the same case/token; the inner invocation sees pending and invokes the hook again. This violates an effective one-time application boundary, regardless of the later conditional update. This is a **static source-proven race**, not an observed production exploit.

`resolve_collision_case()` similarly calls `sauth_resolve_collision_case_v1` before its conditional pending→applied UPDATE. The owner hook must remain canonical and cannot silently merge; nonetheless side-effecting implementations may be invoked twice. F02-X24-012 warrants a parallel test.

## Required fail-first regression before a production code fix
Build a WordPress/MariaDB integration test with a fake canonical owner hook counting side effects. Create a pending recovery row with valid encrypted token and elapsed cooling period; re-enter `apply_recovery_change` from the first owner-hook invocation; assert exactly one hook invocation, exactly one successful state transition, and subsequent attempts reject. Include concurrent two-connection test if infrastructure permits, and error/rollback cases (canonical handler returns false, exception, DB update failure). Add analogous collision-case re-entry test. Existing static marker test is insufficient.

## Remediation design gate (not yet implemented)
Avoid merely moving the final UPDATE before the hook: that would mark the change applied if the owner handler fails. Require an atomic pending→processing claim with a unique claim token and bounded lease, then canonical owner side effect with idempotency key and recovery/rollback handling. Database schema, canonical File 00 owner contract, retry semantics, crash recovery and privacy must be checked before choosing exact implementation. Preserve 24h/1h–7d window, strong step-up, protected owner token, fingerprint policy and fail-closed behavior. Do not patch-stack without test.

## Status
- Requirements catalogued historically: 48. **Individually inspected this run: 4** (X24-009..012); implementation deep-inspected: X24-011 and X24-012.
- Newly source-proven defects: **1** (recovery apply re-entrancy/race); analogous collision path: **1 suspected**, requires hook contract/test to classify.
- New fixes: **0**; new regression tests executed: **0**; new CI: **0**. Remaining source defects: unknown; at least one unresolved finding.
- No main merge, staging/live deployment, or database changes. Next action: inspect recovery_changes schema and canonical-owner hook consumer; implement fail-first re-entrant test on candidate-derived draft branch; then minimal atomic/idempotent fix and real WP/MariaDB regression.

## Five-field truth
Repository HEAD: historical main `224c39bcb8c28f77504c7348dbad41226753c7e8`; inspected candidate PR #18 `40aa85f2781855cb696a864f202ef796cd96b336`; this note belongs to evidence PR #20.
Deployed Version: unverified.
DB Version: unverified; candidate source declares 1.4.0.
Migration State: historical CI migration successful; live state unverified.
Live Verification Status: not performed. **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
