# File 02 v12 — Run 20 source/consumer boundary audit — 2026-11-05

## Governing source
Original Library file Sabri-Website-Master-Plan-v12.docx (not v13), p.84:
- F02-X24-011: recovery cooling-off default 24h, configurable 1h–7d, canonical-owner-mediated pending change.
- F02-X24-012: collision resolution without silent merge; one-time fingerprint-bound case.
Only these two requirements are audited here; no claim that the other File 02 requirements are complete.

## Frozen evidence (read-only audit)
- File 02 default-branch most recent commit from GitHub commit search: 224c39bcb8c28f77504c7348dbad41226753c7e8 (main inferred from repository default branch; no live parity).
- Prior Draft PR #22 evidence SHA 73dfc7d2186fb9bbd928482bd61b330d0329a1dc; branch audit/f02-run14-claim-failure-replay-20261105; existing Run19 evidence verified on this branch before this write.
- File 02 orchestrator at exact 73dfc7d2186fb9bbd928482bd61b330d0329a1dc: includes/class-sauth-security-orchestrator.php blob df974001dc0f9bd31bc63a95cd95ca6116d9ea59.
- File 00 default-branch most recent commit from GitHub commit search: 2fa7c022ee9cd1b65432e900579512f304532442. File 00 historical PR #55 SHA b3e9253d06a2897f092981f3a1f77e16b4b78578 is NOT equivalent to this default branch.
- File 02 Source/DB version: prior candidate 1.4.0, not independently reverified here. Deployed code, live DB and migrations unknown.

## Source-level audit findings
1. File 02 resolve_collision_case() checks pending state, fingerprint, owner token, JSON payload, and performs conditional pending→processing update before invoking sauth_resolve_collision_case_v1. It marks applied only on owner allow with user_id. A failure leaves processing, intentionally blocking automatic replay.
2. File 02 apply_recovery_change() checks pending, apply_after, token, payload, then performs conditional pending→processing before invoking sauth_apply_recovery_change_v1. Failure leaves processing. The invalid-payload fix is present in the frozen blob.
3. Exact-name GitHub code searches on File 00 **default branch** for sauth_resolve_collision_case_v1 and sauth_apply_recovery_change_v1 returned zero results. This is a limited negative search signal, NOT proof of absence in PR #55, other branches, runtime plugins, or external consumers. Do not call the integration contract complete.
4. Neither inspected method establishes crash-after-owner-before-applied reconciliation. This is a documented unverified safety/liveness boundary, not a newly demonstrated exploitable production defect. No patch without fail-first proof.

## Traceability
| ID | A proven missing/broken | B present but insufficiently tested | C external acceptance |
|---|---|---|---|
| F02-X24-011 | 0 new proven defects; prior malformed payload fixed | Atomic claim, token, cooling-off path visible; no actual concurrent DB or canonical callback idempotency proof | File 00 callback registration on exact consumer SHA, real WP/MariaDB two workers, crash reconciliation, staging/founder |
| F02-X24-012 | 0 new proven defects | One-time fingerprint-bound claim visible, mock tests previously green; no real DB race proof | Exact File 00 owner resolution contract, consumer side effects, staging/founder |

## QA and accounting
Prior SHA 73dfc7d2186fb9bbd928482bd61b330d0329a1dc: 5/5 GitHub workflow runs completed success: 42017143973, 42017141211, 42017141238, 42017141216, 42017141204. This is prior commit CI, not CI of this note commit. This run: 2 requirements audited, 0 newly confirmed production defects, 0 source fixes, 0 new fail-first tests, 0 locally executed PHP/MariaDB tests. No merge, deployment or migration.

## Next exact action
Read File 00 PR #55 HEAD and all File 00 owner hook registration paths, compare File 02 callback argument/result contracts. Build a real WP/MariaDB two-worker fixture with owner side-effect counters and crash-after-owner-before-applied injection; create a fail-first regression only for a reproduced defect. Keep PR #22 draft and stacked on #21; preserve ownership, guardian consent, single free tier and privacy.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
