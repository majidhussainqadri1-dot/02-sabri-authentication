# File 02 — Run 27: fail-first collision URL proof regression

Date: 2026-11-06 Asia/Karachi. Governing document: Sabri-Website-Master-Plan-v12.docx, p.84. No v13.

## Immutable source and PR evidence
- Previous PR #22 HEAD: cec9516e28750c8081091478f16ee3868f85f0bb (open/draft/unmerged at start).
- PR base/main SHA reported by GitHub: 224c39bcb8c28f77504c7348dbad41226753c7e8. This is PR base, not an independently refreshed default-branch HEAD.
- Orchestrator blob SHA: df974001dc0f9bd31bc63a95cd95ca6116d9ea59.
- Source test commit: 54fd38d04b873fdb3284b577b0423deace214354.
- File 00 consumer SHA: NOT freshly frozen this run. Canonical owner hook registration remains unverified.
- Candidate source release/schema previously reported: 1.4.0/1.4.0; deployed values unknown.

## v12 requirement traceability
- F02-X24-011 Recovery Cooling-Off: B (mock claim behavior), C (real MariaDB concurrency, owner idempotency, crash reconciliation, staging/live/founder). No new fix.
- F02-X24-012 Collision Resolution: A (source-level URL bearer transport: create_collision_case() returns add_query_arg with proof, render_collision_resolution() reads $_GET['proof']); B (new fail-first static regression committed but execution against checked-out repository pending); C (browser/history/referrer/logging, owner integration, WordPress/MariaDB and founder acceptance pending).

## Work actually done
- Read previous Run26 checkpoint and current PR #22 metadata.
- Read original v12 p.84 F02-X24-011 and F02-X24-012.
- Re-read immutable orchestrator and verified URL bearer path at lines 245–249 and GET consumption at 382–392.
- Verified five CI workflows on previous SHA cec9516e28750c8081091478f16ee3868f85f0bb: success 5, failure 0 (run IDs 42082337214, 42082337357, 42082337203, 42082337525, 42082336915).
- Added fail-first test tests/security/collision-url-proof-regression.php on existing draft branch. This intentionally detects the existing unsafe transport. Not wired into CI yet; no passing test claimed.
- New production fixes: 0. New defects: 0 (one previously known finding reproduced). Remaining known security finding: 1. Test files added: 1.
- No main merge, staging deployment, live deployment, DB migration or schema changes.

## Next exact action
Run new PHP regression on the exact branch checkout to capture expected red result. Inspect every caller/consumer of create_collision_case() and WordPress routing. Implement server-side one-time handoff with no URL bearer while preserving fingerprint and canonical owner semantics; then verify green tests and CI. Do not remove proof parameter alone without maintaining the flow. Verify File 00 PR #55 hooks and two-worker MariaDB crash/replay separately.

## Environment / duration
Target 40 minutes. Start timestamp not captured at first tool call; UTC clock at local runtime 2026-11-05T19:12:45Z. End/elapsed full-session timestamps not established. Therefore 40 minutes NOT verified. PHP 8.4.24 CLI available; local repository checkout and actual test execution not performed. GitHub connector work and commits only. No invented elapsed minutes.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
