# File 02 — Run 26: collision bearer URL source-level threat and acceptance gap

Date: 2026-11-05 (Asia/Karachi). Governing plan: Sabri-Website-Master-Plan-v12.docx, p.84, F02-X24-011 / F02-X24-012. No v13.

## Immutable evidence
- Previous draft PR #22 HEAD: bf77ff044fdaaaa79b177245b3f5f6e8d5ca4e2a (open, draft, unmerged at read).
- Main ref: 224c39bcb8c28f77504c7348dbad41226753c7e8.
- File 02 security orchestrator blob: df974001dc0f9bd31bc63a95cd95ca6116d9ea59.
- Source candidate release/DB/passkey schema as shown in PR diff: 1.4.0 / 1.4.0 / 1.1.0; NOT deployed versions.
- On previous HEAD, 5 GitHub workflow runs completed successfully: 42072261830, 42072261862, 42072261959, 42072261754, 42072261955. These are workflow outcomes, not a count of individual test assertions.

## Traceability and classification
- F02-X24-011: B (mock-tested claim behavior; real concurrent WordPress/MariaDB and crash reconciliation unverified), C (staging/live/founder acceptance outstanding).
- F02-X24-012: A (confirmed source-level exposure-prone bearer transport: create_collision_case returns a URL built with add_query_arg including case and proof); B (fingerprint/one-time behavior has mock-based coverage, but no browser-level secret transport regression); C (real browser/network/referrer/logs, canonical owner integration, founder acceptance unverified).

## Root cause and evidence
The create_collision_case() return includes proof=$token and url=add_query_arg(array('case'=>$public_id,'proof'=>$token),home_url('/resolve-account/')). The render_collision_resolution() shortcode reads $_GET['proof'] and writes it to a hidden POST field. The resolve_collision_post() action reads $_POST['proof'] and removes it after use. Consequently a secret is placed in a navigable URL before the POST. Browser history and request logging are plausible exposure channels; no real-world leak has been observed or asserted. Fingerprint binding does not make secret-bearing URLs desirable. Removing the query parameter alone would break the current handoff, so no untested patch was stacked.

## Tests / actions performed
- GitHub source blob and PR metadata re-read at immutable SHA; main ref checked.
- Five previous-HEAD workflow results inspected: 5 success / 0 failure.
- Local git ls-remote attempted; DNS failed (Could not resolve host github.com). Local PHP runtime exists (8.4.24) but repository checkout, local lint, WordPress/MariaDB, browser E2E, ZIP and checksums were NOT executed.
- New source commits: 0. New tests: 0. Evidence commit: this document only.

## Next exact action
Inspect all callers of create_collision_case and route consumers at frozen SHA; design server-side short-lived, one-time, fingerprint-bound proof handoff that never places the bearer token in a URL. Establish fail-first tests for URL absence and end-to-end resolution, then minimal source change, run exact-SHA CI and real WordPress/MariaDB two-worker and crash-recovery tests. Verify File 00 PR #55 canonical owner callback registration separately. No merge/deploy.

## Environment
Deployed artifact/version, live DB version, migrations, runtime logs and source-to-live parity: unknown. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
