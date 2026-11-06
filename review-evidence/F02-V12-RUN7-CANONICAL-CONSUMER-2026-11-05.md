# File 02 v12 — Run 7: canonical-owner inspection and immutable ref freeze (2026-11-05)

## Authority
Continue original Master Plan v12 F02-X24-011 (cooling-off and canonical owner) and F02-X24-012 (fingerprint-bound one-time collision resolution), as quoted in prior Run 6 evidence. Original DOCX was **not reopened in this run**; these IDs are carried from the preceding primary-source verification, not newly certified here. No v13 assumptions.

## GitHub source freeze (connector reads in this run)
- File 02 main ref: `224c39bcb8c28f77504c7348dbad41226753c7e8` (independently fetched Git ref).
- File 02 PR #18 open, not merged; base main `224c39bcb8c28f77504c7348dbad41226753c7e8`; head `40aa85f2781855cb696a864f202ef796cd96b336` (PR metadata).
- File 02 PR #21 open, **draft**, not merged; base `40aa85f2781855cb696a864f202ef796cd96b336`; head before this evidence update `d0abed8463986cfcd4383e8f750161a6bfa94c3d`.
- PR #21 immutable source tree at `d0abed8463986cfcd4383e8f750161a6bfa94c3d` enumerated without truncation. Orchestrator blob `2d01c5ad20ce52b46ed6de13a6bad79d5ec06c0a`, fail-first static gate blob `4805c0178dbd6965876b681eda84867f33d9cd7a`.
- File 00 previously tested candidate PR #55 `b3e9253d06a2897f092981f3a1f77e16b4b78578` inspected as immutable tree (not assumed current PR HEAD). Canonical contract source blobs: `source/sabri-membership-core/includes/class-smc-authentication-contract.php` `64623bffab340baab734f8cf2bac74b0d4a687e0` (contract 1.0.0), and `class-smc-authentication-contract-v11.php` `2851d416214e6fcf29dd5724073a7dc14366a11f` (contract 1.1.0). Both were read directly.

## Narrow source finding and contract boundary
- File 02 `resolve_collision_case` calls `apply_filters('sauth_resolve_collision_case_v1', ...)` before conditional `pending -> applied` update (orchestrator lines 252–283).
- File 02 `apply_recovery_change` calls `apply_filters('sauth_apply_recovery_change_v1', ...)` before conditional `pending -> applied` update (lines 329–340).
- Both File 00 contract classes examined have registration/completion/privacy hooks but neither displayed a consumer for these two **exact** `sauth_*` filters. Default-branch scoped GitHub code search for both names returned zero indexed matches. **Neither observation proves global absence:** other File 00 classes, external plugins, nondefault branches and deployment wiring remain to be inspected.
- Crucially, the File 02 filter returns `null` unless some canonical owner attaches a callback; absence of a callback means fail-closed behavior, not proof of exploitable duplicate side effects. If a callback is attached and mutates state before returning, the post-callback conditional update does not itself enforce at-most-once. Thus classify as **A: source ordering safety gap / conditional risk**, not confirmed live double mutation.
- Current gate `tests/recovery-once-claim-gate.php` is a structural heuristic. It checks whether a conditional pending-to-processing/claimed database operation occurs before each hook. It does not establish two-connection execution, callback idempotency, rollback or lease/crash recovery.

## Requirement traceability
| ID | Classification | Evidence | Next acceptance |
|---|---|---|---|
| F02-X24-011 | A (source ordering gap), B (runtime not tested), C (owner/live unverified) | orchestrator lines 329–340, static gate | canonical owner consumer + fail-first WP/MariaDB two-connection test, idempotent recovery claim |
| F02-X24-012 | A (source ordering gap), B (runtime not tested), C (owner/live unverified) | orchestrator lines 252–283, static gate | fingerprint-bound collision concurrent and crash/retry test, owner decision semantics |

## Work and test accounting
2 requirements source-inspected, 2 previously known source-ordering gaps reconfirmed, 0 newly confirmed defects, 0 production fixes, 0 tests executed, 0 new CI results. No schema changes, main merge, staging or live deploy. This evidence-only update must not be described as a production fix or 100% acceptance.

## Next exact action
Read the remaining File 00 classes and installed consumer bindings for both exact hooks; determine whether canonical owner handlers are pure approval or mutating. Execute existing fail-first gate against a real checkout, then implement a WordPress/MariaDB two-connection behavioral regression and minimal safe atomic claim + recovery/lease/idempotency protocol, with owner approval/failure rollback rules verified. Preserve Draft PR #21.

## Five-field truth
Repository HEAD: main `224c39bcb8c28f77504c7348dbad41226753c7e8`; PR #18 `40aa85f2781855cb696a864f202ef796cd96b336`; PR #21 pre-note `d0abed8463986cfcd4383e8f750161a6bfa94c3d`.
Deployed Version: unknown.
DB Version: live unknown; previous candidate declaration 1.4.0 not independently verified.
Migration State: live unknown; no migration performed.
Live Verification Status: not performed. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
