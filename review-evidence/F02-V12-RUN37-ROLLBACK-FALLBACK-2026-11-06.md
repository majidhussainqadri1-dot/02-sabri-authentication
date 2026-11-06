# F02 v12 — Run37: compensating rollback fallback (2026-11-06)

Authority: Master Plan v12 p.84, F02-X24-011 and F02-X24-012, as quoted in Run36. The original DOCX was not independently retrieved in this run; this is a continuation against the prior recorded clause, not a fresh document verification.

Previous evidence HEAD: b98fa6e4b6e6212b6f3528f2591964bc1d6a548f. PR #22 draft/open; base SHA 224c39bcb8c28f77504c7348dbad41226753c7e8. Prior test SHA 63ac375b02d0461dc71bfe8abe7e50e7478cdb16: 5 successful workflow runs, 1 failed collision workflow (run 42161681438, step "Exercise transient handoff with isolated WP mocks"). This is fail-first RED, not accepted implementation.

Source audit: includes/class-sauth-security-orchestrator.php blob 4dd641dab7b7ce00733c1c60afcf946c5b8e49f8. On failed set_transient, compensating DELETE was previously unchecked. Mock test blob 3c10f498750deec9fb9ae38dead0a01343baae49 exposed an orphan pending row if DELETE returned false.

Changes: test commit 5b7c720e45479a64c706124429ab2b7666c34769 adds a conditional UPDATE implementation to the fake DB; source commit 91b21d160ff471110f87c17ce5445f745fc70834 attempts status='cancelled' if compensating DELETE did not delete exactly one row. No bearer proof is returned on failed transient handoff. Existing collision resolution rejects non-pending rows. This addresses the mocked DELETE-failure branch, conditional on UPDATE succeeding.

Residual gap: if DELETE and UPDATE both fail, a pending orphan remains. Its exploitability has not been established. Real WP/MariaDB fault injection, transactional/reconciliation design, browser/consumer and canonical-owner concurrent claims remain outstanding. F02-X24-011 B+C; F02-X24-012 A (double DB failure uncontained), B+C. No migration or schema change. Do not assert full source-level acceptance.

QA: prior fail-first collision workflow failed at SHA 63ac375b; other 5 workflows succeeded. At source SHA 91b21d1 no workflow run was visible on first query. PHP lint, local mock execution, real WP/MariaDB, package checksums, and E2E not yet verified this run. CI pass/fail at new SHA unknown. No main merge, no staging/live deployment.

Next exact action: inspect new SHA CI jobs; run isolated mock PHP 7.4/8.x and WordPress+MariaDB fault injection, then handle the DELETE+UPDATE double failure with verified fail-closed/reconciliation contract rather than speculative patch stacking.

40-minute target; runtime start/end timestamps not independently recorded; 40 minutes NOT verified. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
