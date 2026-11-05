# F02 v12 — Run 31 — collision handoff executable mock QA (2026-11-06 PKT)

## Governing source
Master Plan v12 p.84, F02-X24-011: configurable 1h–7d, default 24h, canonical-owner mediated pending recovery change. F02-X24-012: no silent merge; one-time fingerprint-bound collision resolution case. Source-level B + external C remain for both; previous URL bearer A is provisionally fixed, but not accepted in real browser/DB.

## Immutable freeze
Repo majidhussainqadri1-dot/02-sabri-authentication. Main HEAD verified via GitHub commit fetch: 224c39bcb8c28f77504c7348dbad41226753c7e8. Previous draft checkpoint: 810dc550bd144f6048a2c02b86dfaf8d17991b55. Branch: audit/f02-run14-claim-failure-replay-20261105 (branch existence verified). New test commit 1ed47b7b548c1b0a1fdd6aeafd8656a8ff2588c9; CI gate commit 4a5c49147e00ff4af5507ae3fc1cb3625706d191. Orchestrator blob 1ce466b564efa5184f2c3530caaddb2a40e19a47; prior URL regression blob 01edbea66064f64741d0276a3a155ac85b62c5b9; workflow previous blob c22e303c3c289085d440ae4c81e059bc80d51eb3. Candidate DB/runtime 1.4.0, passkey schema 1.1.0 from previous source note only, NOT live.

## Concrete work
Added tests/security/collision-handoff-mock-regression.php: an executable isolated PHP mock of WP transients, fingerprint, case insert, shortcode rendering. Checks URL has no proof, 600s TTL, same-fingerprint proof retrieval, different-fingerprint denial, expiry denial, and deleted-handoff replay denial. Updated .github/workflows/f02-collision-proof-failfirst.yml to PHP-lint orchestrator + tests and execute this new test alongside existing URL regression. No production source changed; no new proven production defect. This is **mock-level**, not real WordPress, MariaDB, browser or two-worker race.

## Execution truth
Local PHP 8.4.24 lint of authored test: PASS (before GitHub upload). New GitHub CI workflow runs for 4a5c49147e00ff4af5507ae3fc1cb3625706d191: empty at two queries; hence 0 confirmed pass, 0 confirmed fail at this SHA, not a green claim. Prior checkpoint 810dc550bd144f6048a2c02b86dfaf8d17991b55: six completed-success GitHub workflows (IDs 42120219492, 42120219377, 42120219417, 42120219393, 42120219411, 42120219401), six pass, zero fail. Individual assertion counts not verified. No real WP+MariaDB, E2E, live, ZIP/checksum or Founder acceptance. No main merge/deploy.

## Remaining gaps and next action
1. Inspect new CI logs at exact test SHA and correct any test harness failure before calling green.
2. Check full create_collision_case return-value consumers, proof exposure, transient expiration, POST deletion and failure/replay paths in real WordPress.
3. Verify File 00 PR #55 owner-hook registration and crash reconciliation with exact consumer SHA.
4. Run real MariaDB two-worker atomic claim and browser isolation tests. Keep File 02 acceptance conditional.

## Duration
40-minute target. First runtime timestamp available 2026-11-06 04:13:11 PKT; end-to-end 40 minutes not verified. No fabricated elapsed time. No production fixes, one new mock regression file and one CI workflow change. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
