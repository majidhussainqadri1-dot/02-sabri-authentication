# F02 v12 — Run 14: owner failure / replay containment (2026-11-05)

## Governing primary requirements
Source: Sabri-Website-Master-Plan-v12.docx, File 02 p.84:
- F02-X24-011: recovery cooling-off, default 24h, configurable 1h–7d, canonical-owner mediated pending change.
- F02-X24-012: one-time fingerprint-bound collision resolution, no silent merge.

## Frozen GitHub source and lineage
Repository majidhussainqadri1-dot/02-sabri-authentication; main historical SHA 224c39bcb8c28f77504c7348dbad41226753c7e8 (GitHub search; current ref not independently resolved).
Parent PR #21 last evidence SHA edf4cef1f27e8a50526d980bfb64f5adfbe5a538, source file blob 5b69387fcb58d1199bca7e545819e32026cc011d.
This new draft PR #22 is based on that SHA; new test commit 35840df0ee1e1f3eaa09e264f1892316daafe5db; workflow commit db8777710805b58ddff5ae445836a3cefdd45e31. Do not merge #22 independently of #21.
Candidate DB schema version previously recorded 1.4.0; File 00 consumer SHA, deployment, DB migration, staging/live are not reverified.

## Requirement traceability
| Requirement | Classification | Evidence | Remaining |
|---|---|---|---|
| F02-X24-011 | B/C | atomic pending->processing claim already in PR #21; new mock test checks owner failure and replay containment | Real WP/MariaDB concurrent workers, callback idempotency/crash reconciliation, staging/live/founder |
| F02-X24-012 | B/C | same ordering and deterministic failed owner/replay mock; fingerprint check in source | Real DB and canonical owner contract, staging/live/founder |

A: no new proven production defect in this run; previous two ordering defects have source patches but remain acceptance-pending.
New files: tests/recovery-claim-owner-failure.php; updated .github/workflows/file02-recovery-claim-gate.yml. Test simulates an owner returning false after one successful atomic claim and verifies that the record remains processing and a second invocation does not reach the owner. It is mock-only; cannot establish exactly-once effects or DB crash safety.

## QA and execution truth
Previous parent PR #21 SHA caba0f4afc714099acdb092190aff01f35e61c53 had 5 successful CI workflows / 0 failures per prior recorded evidence. On new SHA db8777710805b58ddff5ae445836a3cefdd45e31, GitHub workflow query returned 0 runs at checkpoint; thus **0 new CI passes, 0 new CI failures, test execution unverified**. Local clone failed due DNS resolution of github.com; PHP lint, MariaDB, WordPress integration, ZIP/checksums and live not run here.
No production source change, no merge, no deploy.

## Next exact action
Read PR #22 CI job logs once triggered; fix any harness failures; then implement real WP+MariaDB concurrent worker coverage for both requirements and inspect File 00 callback side effects. Verify deployed artifact/DB/migration separately before any live claim.
Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
