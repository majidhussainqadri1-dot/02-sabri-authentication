# File 02 v12 — Run 18 evidence-first checkpoint — 2026-11-05

## Governing v12 and scope
Original Sabri-Website-Master-Plan-v12.docx page 84: F02-X24-011 default 24h cooling-off, configurable 1h–7d, canonical owner mediation; F02-X24-012 one-time fingerprint-bound collision case, no silent merge. This run changes only File 02 recovery payload validation. No v13.

## Frozen provenance
- Prior evidence commit: aaa580a5ad2a6fe9af08feed31106776033b24c9.
- Prior source orchestrator blob: 5b69387fcb58d1199bca7e545819e32026cc011d.
- Prior exact CI at aaa580a5ad2a6fe9af08feed31106776033b24c9: five workflow runs SUCCESS, IDs 42000348539, 42000348535, 42000348541, 42000348792, 42000348650. These were on the PREVIOUS code, not the new fix.
- Repo default branch main; last commit-search result 224c39bcb8c28f77504c7348dbad41226753c7e8, NOT verified direct branch-ref HEAD.
- Existing PR #22, branch audit/f02-run14-claim-failure-replay-20261105. Stacked on PR #21; do not merge independently.
- New fail-first test commit: 7ebcb2864f093fdb1d19ac7ac8980501802e4477.
- New workflow commit: d6915bce4b9a49d74a151498687f6b8786c3a207.
- New source fix commit: 46cb37b2d0effe6a96e6e4cadd9d169caac1cee2.
- New orchestrator blob: df974001dc0f9bd31bc63a95cd95ca6116d9ea59.

## Confirmed source defect and root cause
In apply_recovery_change, decrypted payload JSON was decoded, but non-array decode results (including malformed JSON, null, false, number, string) were silently replaced by an empty array when invoking sauth_apply_recovery_change_v1. Before this callback, the code atomically transitioned pending to processing. This could consume a one-time recovery case and invoke the canonical owner with altered payload semantics. Source inspection confirms the defect; exploitation and live impact NOT established.

## Change and regression
- Added tests/recovery-claim-invalid-payload.php, deterministic mock of malformed/non-object payload with expected zero database claims and zero canonical owner calls.
- Added PHP lint and execution to .github/workflows/file02-recovery-claim-gate.yml.
- Minimal production change: reject non-array decrypted/decoded payload with WP_Error sauth_recovery_payload_invalid BEFORE pending->processing claim; pass validated array unchanged to owner.
- Test is fail-first by source reasoning, but no executable red result was obtained: GitHub Actions query for workflow commit returned no runs. Do not label red run proven.
- GitHub Actions query for source fix commit returned no runs at check time. New fix CI pass/fail = 0/0 OBSERVED, not a pass.
- Local git clone failed DNS resolution for github.com; PHP 8.4 is installed but complete repo source was not locally obtained, so local PHP lint/test not executed.
- Real WordPress/MariaDB, two-worker concurrency, crash reconciliation, consumer callback side effects, package ZIP/manifest SHA, staging and live parity NOT tested.

## ID-wise traceability
| ID | A | B | C | Next |
|---|---|---|---|---|
| F02-X24-011 | Confirmed malformed payload fail-open-to-owner; source fix committed, not executed | Existing atomic claim and mock replay gates previously green | MariaDB race, owner idempotency, crash reconciliation, staging/founder | Verify exact new SHA CI and DB integration |
| F02-X24-012 | No new collision-specific defect established | Existing fingerprint/one-time mock gates previously green | Real database and consumer/staging/founder acceptance | Test collision persistence and canonical owner |

## Accounting
Requirements inspected 2; new source defects found 1; source fixes committed 1; fully validated fixes 0; unresolved acceptance requirements at least 2. New exact-SHA CI success/failure observed 0/0. No main merge, no migration, no deployment.

## Next exact action
Check CI on new SHA; inspect failed steps and repair in same draft branch; run real WordPress/MariaDB concurrent workers and owner callback idempotency; freeze consumer SHAs and package checksums; then update evidence.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
