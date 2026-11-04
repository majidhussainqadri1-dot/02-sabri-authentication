# File 02 v12 — Run 5, fail-first claim ordering (2026-11-05)

## Scope and provenance
Master Plan v12 File 02 p.84 as recorded in Run 4: F02-X24-011 (cooling-off/canonical-owner mediated recovery) and F02-X24-012 (one-time fingerprint-bound collision resolution, no silent merge). The original DOCX was **not independently reopened in this run**; prior clause transcription is the scope limit. No v13 or new feature scope invented.

Repository `majidhussainqadri1-dot/02-sabri-authentication`; candidate PR #18 exact immutable base `40aa85f2781855cb696a864f202ef796cd96b336`. New Draft PR #21 based directly on `codex/file02-modern-auth-24-1.4.0`, whose base SHA GitHub reported as that exact candidate. New test commit `8b28a9e62cd400fd4eab546b72deb280d4ceea2e`. Current main SHA **not reverified**. PR #20 Run 4 note commit `f63e9de37c50a74ddf8b785b95a82d4ccd61af60`.

## Inspected immutable blobs
- `includes/class-sauth-security-orchestrator.php` `2d01c5ad20ce52b46ed6de13a6bad79d5ec06c0a`: recovery lines 329–340 and collision lines 252–283 invoke canonical owner filters before conditional pending→applied update.
- `includes/class-sa-activator.php` `f3c66b79539250aca8e4d96b598d478d82e7f0d2`: `sauth_recovery_changes` has status varchar(24), unique public_id and timestamps; **no explicit claim-token or lease fields**. Database migration design is therefore not yet settled.
- `tests/modern-auth-24-unit.php` `9777d5442ebf43888496d0d0c572b0d31b7f2ab7`: X-QA-24-06 marker checks, not re-entry behavior.
- `PLAN-TRACEABILITY.md` `58a2658841cca32429653be9005522be5a99f574`: candidate v1.4.0, DB v1.4.0, passkey schema v1.1.0; implementation claims are not behavioral proof.

## New work
Created `tests/recovery-once-claim-gate.php` in Draft PR #21. The PHP structural gate requires a conditional DB pending→processing claim **before** `sauth_apply_recovery_change_v1` and `sauth_resolve_collision_case_v1`. The inspected candidate has no such pre-hook claim, so **expected fail-first** on both paths. The test was **not executed against the repository in this run**: GitHub connector can read/write, but the local shell cannot resolve github.com to clone, and no remote execution action was available. This gate is only a structural signal, not a real concurrency or idempotency test. Do not represent it as green CI.

## Classification
- A: F02-X24-011, source-proven owner-side-effect-before-claim race (unfixed).
- A: F02-X24-012, same source ordering and re-entry exposure confirmed; actual owner-side effects depend on File 00 consumer (unfixed).
- B: Existing X-QA-24-06 checks only marker presence, no behavioral test.
- C: WP/MariaDB two-connection test, canonical File 00 consumer, staging/live/founder approval unverified.
- 2 requirements inspected deeply; 1 new structural test added; 0 production fixes; 0 tests executed; 0 new CI outcomes. At least 2 source-level ordering gaps remain.

## Next exact action
1. Fetch File 00 canonical hook consumer and identify idempotency contract, failure/rollback semantics.
2. Add actual WP/MariaDB re-entrant and failure-path integration tests, execute and record red results.
3. Design atomic claim with bounded lease, idempotency key, crash-safe owner handling; migrate schema only with verified dbDelta/legacy parity. Then minimal source fix, rerun regressions and package checks.
4. Keep PR #21 draft and main/live untouched.

## Five-field truth
Repository HEAD: PR #21 test commit `8b28a9e62cd400fd4eab546b72deb280d4ceea2e`; candidate base `40aa85f2781855cb696a864f202ef796cd96b336`; main current unknown.
Deployed Version: unknown.
DB Version: live unknown; candidate declared 1.4.0.
Migration State: live unknown; no migrations run this session.
Live Verification Status: not performed. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
