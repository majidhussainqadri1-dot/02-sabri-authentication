# F02 v12 Run35 — MariaDB rerun resolved (2026-11-06 PKT)

## Authority and frozen refs
Master Plan v12 p.84, F02-X24-011: canonical-owner-mediated cooling-off, configurable 1h–7d, default 24h. F02-X24-012: collision resolution wizard, no silent merge, one-time fingerprint-bound resolution. This is a partial ID-wise matrix, not whole-file acceptance. Repository majidhussainqadri1-dot/02-sabri-authentication; Draft PR #22 branch audit/f02-run14-claim-failure-replay-20261105, prior checkpoint 36c53bbc1991da8bef0d19fd3cd20ee58b0652ad; main/base 224c39bcb8c28f77504c7348dbad41226753c7e8. No source/schema changes in this run.

## Verified CI result — corrected prior provisional finding
Exact tested SHA **0448196d7a1d207154d5a7380769196e14c4eeb2**: all six workflow runs now have conclusion SUCCESS: review integrity 42142912840, release integrity 42142912826, File 00 WordPress/MariaDB integration 42142912769 (rerun attempt, job **127667190500**), collision proof 42142912699, recovery 42142912762, canonical storage 42142912709. The integration rerun completed all reported steps successfully: container initialization, immutable File 02/File 00 checkouts, WordPress install/activation, canonical fixture, legacy passkey and logical-identity migrations, route/taxonomy boundaries. The initial MariaDB healthcheck failure was transient on evidence available; its root cause is not established. Do not patch infrastructure without reproduction.

## Source evidence and limits
Read tests/security/collision-handoff-mock-regression.php at prior checkpoint SHA 36c53bbc1991da8bef0d19fd3cd20ee58b0652ad, blob a771195b79aeca053270d88721ce824e527e94ab. It asserts URL secrecy, 600-second TTL, fingerprint isolation, expiry, deleted-transient replay, and orphan rollback when set_transient fails. This is a MOCK test, not proof of real transient failure injection. The successful File 00 integration workflow does not by itself prove these collision scenarios, two-worker concurrent claims, rollback delete failure, or browser E2E. File 00 PR #55 and File 01 remain conditional. F02-X24-011 B+C; F02-X24-012 B+C, with missing real integration/Founder acceptance.

## Counts and action
2 requirements examined, 0 new proven application defects, 0 production fixes, 0 test edits, 1 evidence-only commit. CI at tested SHA: 6 success / 0 failure (rerun terminal outcome); no claim about CI at this evidence commit. Next: introduce controlled real WP/MariaDB failure-injection for set_transient/delete rollback, concurrent owner claim and crash reconciliation, with fail-first before source patch. Preserve Draft PR; no merge/deploy. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔

## Time
Goal 40 minutes. Start reference 2026-11-06 08:15:10 Asia/Karachi; verified terminal end timestamp not recorded; 40-minute completion NOT established. No fabricated elapsed time.
