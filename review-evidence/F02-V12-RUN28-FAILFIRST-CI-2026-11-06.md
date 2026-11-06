# File 02 — Run 28: collision URL proof fail-first CI wiring

Date: 2026-11-06 Asia/Karachi. Governing source: Sabri-Website-Master-Plan-v12.docx p.84, F02-X24-011 and F02-X24-012. No v13.

## Previous checkpoint and immutable evidence
- Prior draft checkpoint HEAD: a01dcf77f1fa3e11f043b796597c5a2c39298b67 (Run27).
- Existing fail-first test blob: 0e9f99a1de08cbfe5d4658fba24bf5ec9f525a15.
- Orchestrator source blob: df974001dc0f9bd31bc63a95cd95ca6116d9ea59.
- New CI workflow source commit: 89b160115b3aad21c98e4ee55c87c18f7c202a8b.
- Draft branch verified by fetching Run27 checkpoint: audit/f02-run14-claim-failure-replay-20261105.
- Main HEAD not freshly frozen; historical PR base 224c39bcb8c28f77504c7348dbad41226753c7e8 is NOT a refreshed main HEAD.
- File 00 consumer SHA not freshly verified. Production source/schema historically 1.4.0; actual deployment unknown.

## Requirements traceability
- F02-X24-011: B/C. Recovery mock behavior exists, but canonical owner idempotency, real MariaDB concurrent workers and crash reconciliation not accepted.
- F02-X24-012: A/B/C. Proven source URL bearer design: create_collision_case adds proof query parameter and render_collision_resolution reads GET proof. Browser exposure and live exploitation unverified. Fail-first static test exists; now CI expected-red runner is committed, but execution of new workflow remains unverified. Owner consumer and WordPress/MariaDB integration remain pending.

## Actions and QA
- Read Run27 checkpoint and original v12 clause via Library search.
- Read exact orchestrator and regression test at prior SHA.
- Verified five GitHub Actions workflows on Run27 HEAD a01dcf77f1fa3e11f043b796597c5a2c39298b67: 5 success, 0 failure (IDs 42091760523, 42091760329, 42091760325, 42091760791, 42091760371). Those workflows do NOT execute the newly added fail-first test.
- Added .github/workflows/f02-collision-proof-failfirst.yml on the existing draft branch, committing a PHP lint and expected-RED check for both unsafe source signals. CI execution not yet returned; DO NOT claim it ran or passed.
- Local git ls-remote failed DNS resolution for github.com. PHP 8.4.24 CLI present but exact repository checkout unavailable; no local source execution, real WP/MariaDB, browser E2E or ZIP checksum.
- New confirmed defects 0; previously proven URL bearer design finding 1; production fixes 0; new test runner/workflow 1; remaining source security finding 1. No schema/migration/deploy changes.

## Next exact action
Obtain actual workflow run and log for commit 89b160115b3aad21c98e4ee55c87c18f7c202a8b or later checkpoint; verify expected RED. Freeze every create_collision_case consumer and routing contract, then implement a minimally scoped secure server-side one-time handoff and update fail-first test from expected RED to required GREEN, preserving fingerprint and canonical owner verification. Confirm real MariaDB concurrency and crash reconciliation before acceptance. No merge/deploy.

## Duration
Target 40 minutes. Recorded UTC timestamps: 2026-11-05T20:12:19Z at local git attempt; 2026-11-05T20:12:51Z before checkpoint; final timestamp not captured. At least 32 seconds of observed work, not 40 minutes. Execution window and local network DNS prevented full target. Do not claim 40-minute completion.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
