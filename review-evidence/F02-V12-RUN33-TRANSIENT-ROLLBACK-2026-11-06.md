# File 02 v12 — Run 33 — transient failure rollback (2026-11-06 PKT)

## Authority and traceability
Original Library `Sabri-Website-Master-Plan-v12.docx` p.84: F02-X24-011 canonical-owner-mediated recovery cooling-off 1h–7d, default 24h (B+C); F02-X24-012 no silent merge, one-time fingerprint-bound collision case (A: verified orphan failure path; B: mock test, pending CI; C: real WP/MariaDB, browser, Founder). No v13.

## Frozen provenance
Repo `majidhussainqadri1-dot/02-sabri-authentication`, Draft PR #22 branch `audit/f02-run14-claim-failure-replay-20261105`, base main `224c39bcb8c28f77504c7348dbad41226753c7e8` (verified through PR metadata and latest default-branch commit search). Prior checkpoint `ce89a22f00344f5fab4a78f7c6ecbd5af177798f`; previous six workflow runs all success (42135397337, 42135397347, 42135397346, 42135397391, 42135397345, 42135397343). Existing source blob `1ce466b564efa5184f2c3530caaddb2a40e19a47`, test blob `e54fa1d8492fe7f6684694380806f60d60dacbad`.

## New defect / fail-first / minimal fix
Source `create_collision_case()` inserted a pending recovery_changes row before `set_transient()`; on transient failure returned error without deleting the pending row. A durable orphan without usable browser handoff results (source-level A). Test-first commit `8e27e589fd3221896e3f1c3784c5ffabb8416100` extends mock to simulate set_transient=false, require the error and assert no orphan row. Fix commit `6caba670dcdd2b2d063077357d14c9dab4d013d4` deletes only matching pending case on handoff failure; no successful path, token transport, schema, auth policy or owner hook altered. Database delete failure remains an explicit residual risk needing real integration/cleanup validation.

## QA truth
Prior exact checkpoint `ce89a22f00344f5fab4a78f7c6ecbd5af177798f`: 6 workflow PASS / 0 FAIL. New test-only SHA `8e27e589fd3221896e3f1c3784c5ffabb8416100`: 6 workflows QUEUED at first inspection, RED not yet observed. Fix SHA `6caba670dcdd2b2d063077357d14c9dab4d013d4`: no runs returned at first inspection. Local PHP CLI 8.4.24 exists, but git remote checkout failed due to DNS `Could not resolve host: github.com`; thus no local execution, lint or real WP/MariaDB, concurrency, browser E2E, ZIP/manifest parity. No green claim for new commits. No merge/deployment.

## Next exact action
Collect CI RED on test-only SHA and GREEN on fix SHA, inspect job logs; run PHP 7.4/8.3 and mock test, then real WordPress with transient failure injection and database rollback/cleanup failure; File 00 canonical owner hook integration and two-worker claim/crash recovery remain pending. File 02 not 100% accepted.

## Timing
40-minute target; automation start reference 2026-11-06 06:16:59 PKT, local command at 06:17:49 PKT. Final timestamp/duration not fully captured; 40 minutes NOT complete/verified. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔

## CI follow-up (same run)
Test-only SHA `8e27e589fd3221896e3f1c3784c5ffabb8416100`: collision workflow run `42142853697` completed FAILURE (expected RED); review integrity, recovery, canonical storage completed SUCCESS; other workflows pending at check. Fix SHA `6caba670dcdd2b2d063077357d14c9dab4d013d4`: collision workflow run `42142863965` completed SUCCESS (GREEN), recovery run `42142863995` SUCCESS; four other workflows still IN_PROGRESS. This is workflow-level evidence, not full integration acceptance. The fail-first RED and subsequent GREEN now have distinct immutable SHAs.
