# File 02 v12 — Run 25, exact-source collision transport audit (2026-11-05)

## Authority and frozen source
Original Library `Sabri-Website-Master-Plan-v12.docx`, page 84: F02-X24-011 recovery cooling-off (1h–7d, default 24h, canonical owner) and F02-X24-012 collision resolution (no silent merge; one-time fingerprint-bound case). No new scope or v13.
File02 PR #22 is open, draft, unmerged. Head before this evidence note: `28d2b514a8a6dad6a12e20f65f0e010c46e17640`, branch `audit/f02-run14-claim-failure-replay-20261105`, base/main observed via PR `224c39bcb8c28f77504c7348dbad41226753c7e8`. Source blob `includes/class-sauth-security-orchestrator.php` = `df974001dc0f9bd31bc63a95cd95ca6116d9ea59`. File00 PR55 historical candidate `b3e9253d06a2897f092981f3a1f77e16b4b78578` is NOT refreshed in this run.

## Concrete source findings
1. `create_collision_case()` stores encrypted proof and returns `add_query_arg(array('case'=>$public_id,'proof'=>$token),home_url('/resolve-account/'))`. `render_collision_resolution()` reads `$_GET['proof']` and emits a hidden POST field. This establishes a **bearer secret in a navigable URL**, not a proven live leak. Fingerprint binding and one-time claim mitigate unauthorized reuse but do not eliminate URL disclosure surfaces.
2. `resolve_collision_post()` checks nonce, reads POST case/proof, clears POST proof and redirects to a status-only URL on failure; this POST redirect itself does not propagate the proof. The initial GET URL remains the specific concern.
3. `render_collision_resolution()` shows no explicit referrer/cache controls in the inspected method. Global WordPress/server headers and embedded resources were NOT inspected, so absence of effective policy cannot be asserted.
4. Any mitigation that simply strips `proof` from the URL would break the existing GET-to-POST transport unless a safe alternative handoff is implemented and tested. Do not stack an untested fix or alter File00 ownership.

## ID-wise traceability
| ID | A proven | B source present but under-tested | C external |
|---|---|---|---|
| F02-X24-011 | no new A in bounded inspection | conditional claim, JSON guard; owner idempotency/crash reconciliation and real concurrency pending | WP/MariaDB, staging, Founder, live |
| F02-X24-012 | navigable URL carries bearer token (confirmed design-level security exposure path; live disclosure unverified) | fingerprint binding, pending→processing claim, canonical callback; browser referrer/history/log controls and consumers unverified | browser E2E, WP/MariaDB, staging, Founder, live |

## CI, immutable SHA and limitations
For **exact PR HEAD before this note** `28d2b514a8a6dad6a12e20f65f0e010c46e17640`, GitHub Actions API returned **5 completed/success, 0 failure**: File00 integration 42063045613; Release Integrity 42063045490; Review Branch Integrity 42063045617; recovery one-time claim regression 42063045484; Canonical Storage 42063045818. These are workflow conclusions, not a count of individual test assertions, not a test of this new note commit, and not live evidence.
No PHP lint, new fail-first test, production patch, actual WP/MariaDB workers, ZIP checksum or browser E2E was run during this checkpoint. This bounded source audit yields 1 confirmed URL transport security concern (carried forward), 0 new proven functional defects, 0 fixes, 2 requirements still unaccepted.

## Next exact action
Inspect all consumers of `create_collision_case()['url']`, routing and header policies at immutable source; establish a fail-first regression that fails on a navigable bearer URL and validates a functioning same-origin handoff; only then apply the minimal File02-owned fix and run PHP/CI/browser regression. Separately verify File00 canonical callback registration on PR55 exact tree, and run actual WordPress/MariaDB concurrent workers and crash-reconciliation scenarios.

Deployed artifact/version, live DB version, migration state, runtime logs and live parity unverified. **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
