# F02 v12 — Run 29 — Collision bearer URL remediation (2026-11-06 PKT)

## Governing requirements
Master Plan v12 p.84: F02-X24-011 recovery cooling-off (B+C), F02-X24-012 one-time fingerprint-bound collision resolution/no silent merge (A source transport fixed provisionally; B+C integration/acceptance outstanding).

## Immutable baseline and commits
Previous PR #22 HEAD cfa7ae99636673de4edfdc82ae85d5cf57de2ae7; PR base main 224c39bcb8c28f77504c7348dbad41226753c7e8. Previous source blob df974001dc0f9bd31bc63a95cd95ca6116d9ea59.
Source fix 13239d076f821928468310a05efb0600ae4d263c. Workflow regression update 80d57aee26f1a6e71f12dcfde364e394a7ed90af.
Candidate runtime 1.4.0 / DB 1.4.0 / passkey schema 1.1.0; deployed versions not verified.

## Evidence
At cfa7ae99636673de4edfdc82ae85d5cf57de2ae7, GitHub Actions 42101314992 completed SUCCESS, with PHP lint and expected RED exit from collision-url-proof-regression.php; 5 other workflow runs completed SUCCESS (42101314591, 42101314832, 42101314833, 42101314784, 42101314721). RED was deliberately expected, NOT a security pass.
Root cause: create_collision_case appended raw bearer proof to navigable URL and render_collision_resolution read $_GET['proof'].
Fix: only opaque case ID remains in URL; token stored in 10-minute WordPress transient keyed by case + client fingerprint via HMAC wp_salt('auth'); render obtains proof server-side; POST deletes handoff. Canonical owner filter and atomic pending->processing gate unchanged.
The previous fail-first workflow is converted to GREEN expectation, with structural handoff guards. At note authoring, CI on 80d57aee26f1a6e71f12dcfde364e394a7ed90af had no returned runs; do not claim green.

## Unverified and next
Full PHP lint / tests on new commit, WordPress+MariaDB two-worker concurrency, transient expiry and cross-fingerprint tests, browser history/referrer tests, File 00 canonical owner registration and crash reconciliation, consumer contract review, release ZIP checksums, deployed artifact parity, staging/live and Founder acceptance are PENDING. No deployment or main merge. Need check that returning 'proof' field to PHP caller cannot be leaked by other consumers; if so remove through contract-safe migration. No 100% acceptance claim.

## CI follow-up / correction
At 80d57aee26f1a6e71f12dcfde364e394a7ed90af workflow 42110476230 FAILED at the GREEN source guard; job 127536375462 log says "Collision creation passes bearer proof into a URL parameter map". Root cause of test failure: original regex matched the retained internal return array 'proof'=>$token, not only add_query_arg URL construction. The URL was already case-only. Corrected guard in c5da58b5ada07166edc97fa44da97d5cbc333fb6 to scope to add_query_arg(...array(...proof=>...); preserves internal return contract pending consumer audit. This is a test false-positive correction, not evidence of full runtime acceptance. At writing, corrected SHA CI result was not yet returned. Previously running 5 other workflows at 80d57a... are not counted as passes until completed. Verify actual CI and logs next.
