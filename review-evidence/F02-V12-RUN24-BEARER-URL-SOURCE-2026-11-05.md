# File 02 v12 — Run 24 source audit and security evidence (2026-11-05)

## Authority
Library original Sabri-Website-Master-Plan-v12.docx p84: F02-X24-011 canonical-owner-mediated 1h–7d configurable, default 24h recovery; F02-X24-012 no silent merge, one-time fingerprint-bound collision case. No v13.

## Frozen references
- File02 main last observed commit: 224c39bcb8c28f77504c7348dbad41226753c7e8 (commit search, default branch).
- Prior draft PR #22 checkpoint: c4099685053b0119289ebe30f6eecde64777f4fe; branch audit/f02-run14-claim-failure-replay-20261105 (branch search confirmed). This note is appended to that branch, not main.
- Source read at immutable c4099685053b0119289ebe30f6eecde64777f4fe: includes/class-sauth-security-orchestrator.php. Previous checkpoint blob 8011f0c39d4e3e3bd25b51927479a1683c44baef.
- File00 PR55 historical candidate b3e9253d06a2897f092981f3a1f77e16b4b78578; no new exhaustive consumer proof in this run.

## Source audit / root-cause boundary
- create_collision_case() generates random bearer proof, encrypts it at rest, then returns URL with `case` AND `proof` as GET query arguments. render_collision_resolution() reads `$_GET['proof']` and embeds it in a hidden POST field. This is a confirmed source-level exposure *path*, not proof that logs, third-party referrers, or browser history have leaked the token in any deployed environment. Threat model, URL redaction, cache/referrer headers, and route integration must be reviewed before remediation.
- resolve_collision_case() checks fingerprint and bearer before conditional pending→processing claim, then invokes canonical hook. No proven missing hook registration across entire File00 tree. No speculative owner hook added.
- apply_recovery_change() checks JSON validity before conditional claim and canonical callback. Post-owner/pre-applied crash reconciliation remains unproven.

## Traceability
| ID | A: proven broken | B: present / under-tested | C: external acceptance |
|---|---|---|---|
| F02-X24-011 | none newly proven | atomic claim and JSON guard; true DB concurrency, owner idempotency and crash reconciliation pending | WP+MariaDB, staging, Founder, live |
| F02-X24-012 | URL bearer propagation confirmed as security review finding; exploitability not established | fingerprint/one-time claim and canonical owner decision; browser referrer/history/log threat model and consumer hook proof pending | browser/security policy, staging, Founder, live |

## Exact-SHA CI
At pre-note commit c4099685053b0119289ebe30f6eecde64777f4fe: 5 completed/success, 0 failed: File00 Integration 42052878787; Canonical Storage 42052878268; Recovery Claim Regression 42052878051; Review Branch Integrity 42052878087; Release Integrity 42052878114. No new local PHP, MariaDB concurrency, E2E, ZIP/checksum, or live tests executed. No production source patch or fail-first regression this run: browser token transport contract and exact mitigation not yet approved; patch stacking would be speculative.

## Counters and next action
2 requirement IDs audited; 1 confirmed sensitive-token URL propagation source finding (not classified as confirmed exploit); 0 production defects fixed; 2 IDs remain unaccepted; 0 source commits; 1 evidence commit. Next: inspect actual routing/HTTP header and external-resource behavior, establish fail-first browser regression, choose minimally invasive same-origin proof transport mitigation, and test File00 callback registration from full PR55 tree; then real WP/MariaDB workers.

Deployment artifact, DB version, migration state and live verification unknown. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
