# File 02 — R337 Correction and Retest Evidence

## Governing discipline

R337 followed the Founder-mandated review-first discipline:

1. complete the full review without coding corrections;
2. freeze the complete R337 defect ledger;
3. only then correct every verified R337 defect;
4. add/align permanent regressions;
5. run exact-head cumulative QA;
6. keep repository, staging and live claims separate.

The frozen ledger is `review-evidence/R337-REVIEW-FROZEN.md`.

## Exact review identity

- Repository: `majidhussainqadri1-dot/02-sabri-authentication`
- Review branch: `review/file02-r337-fresh-audit-2026-08-16`
- Frozen pre-correction HEAD: `972f5fd2cc59fe69bf465b844ac36c740533f7dd`
- Frozen-ledger commit: `24fde2c30bfa3a6d01671427605931a2d6924746`
- Runtime identity retained: File 02 `1.2.6`
- DB identity retained: `1.2.1`
- Passkey schema retained: `1.0.1`
- Exact multi-file source correction commit: `e04cfdf51a6d876f70c0296acfb9692fef5a54df`
- First fully green post-correction exact-head source/evidence candidate: `734c09ca58e973fd4acf1968b335d8a451b40d8d`
- Successful Review Branch Integrity run for that exact candidate: `31930439265`

This evidence document is added after that green run, so the commit containing this document must itself receive a fresh exact-head Review Branch Integrity run before being treated as the final immutable R337 branch head.

## Frozen R337 defects and corrections

### R337-D01 — High — File 00 dependency gate admitted an incompatible provider runtime

**Defect:** File 02 required File 00 `1.2.43+`, although the canonical nine-type account taxonomy/provider alignment used by File 02 is corrected in the reviewed File 00 `1.2.44` candidate.

**Correction:** `SA_Membership_Adapter::MIN_VERSION` and activation/readme evidence now require File 00 `1.2.44+`. File 02 does not introduce a lossy account-type remap.

### R337-D02 — Medium — WordPress minimum contradicted the mandatory File 00 dependency

**Defect:** File 02 advertised WordPress `6.0+`, while mandatory File 00 `1.2.44` requires WordPress `6.4+`.

**Correction:** plugin/readme and release evidence now require WordPress `6.4+`.

### R337-D03 — High — Professional-adult registration prevalidation used stale provider-only account types

**Defect:** the local adult-only list still used `clinic_staff` and `institution_representative` while omitting canonical `researcher`, `pharmacy`, `clinic`, and `publisher`.

**Correction:** local professional/institutional adult prevalidation now uses the canonical current set: `doctor`, `teacher`, `researcher`, `pharmacy`, `clinic`, `publisher`.

### R337-D04 — High — Passkey success could continue without credential security-state persistence

**Defect:** after signature validation, File 02 updated `sign_count`, backup state and usage timestamps without checking the mutation result or verifying the persisted postcondition. A failed write could still continue into passkey assurance/session success.

**Correction:** the passkey path now verifies the update result, DB error state, active credential status, expected signature counter, expected backup state and exact `last_used_at` persistence before any pending assurance/session success. Failure terminates as `credential_state_persist_failed`.

### R337-D05 — High — Authentication success could outlive failed File 02 session-registry persistence

**Defect:** `SAUTH_Session_Manager::register_cookie()` destroyed a newly issued WordPress session token if the File 02 projection failed, but password/Google/passkey callers could continue emitting success and redirecting as if the session existed.

**Correction:** projection failure now checks the DB error/postcondition, destroys the token, clears the auth cookie/current user and terminates the synchronous request with a fail-closed 503 path. AJAX receives an explicit error response; normal requests stop through `wp_die()`.

### R337-D06 — Medium — WordPress administrative email confirmation was intercepted

**Defect:** `redirect_core_login_surface()` preserved core `logout`, `postpass` and `confirmaction` but redirected `confirm_admin_email` into File 02 login.

**Correction:** `confirm_admin_email` is explicitly preserved as a WordPress-owned administrative ceremony.

### R337-D07 — Medium — Release evidence used stale branch/review identity

**Defect:** release/status evidence still pointed to the prior File 02 corrective branch/review line.

**Correction:** `RELEASE-LOCK.json`, `STATUS.md`, `RELEASE-MANIFEST.md`, `README.md`, `readme.txt`, `PLAN-TRACEABILITY.md` and permanent release-truth regressions now describe the R337 review branch, the File 00 `1.2.44+`/WordPress `6.4+` dependency boundary, and the correct separation of current source evidence from historical cross-file evidence.

## Permanent regression alignment

R337 added `tests/r337-fresh-audit-regression.php` and retained the historical functional regressions. Several historical release-truth tests contained deliberately exact old branch strings; after R337 legitimately advanced the review line, those stale string assertions blocked the cumulative suite. They were updated without weakening functional/security checks:

- `tests/r299-cross-flow-regression.php`
- `tests/r302-migration-postcondition-regression.php`
- `tests/r319-release-contract-truth-regression.php`
- `tests/r329-release-truth-regression.php`
- `tests/r330-final-adversarial-regression.php`
- `tests/r334-passkey-dbdelta-migration-regression.php`
- `tests/r335-passkey-index-reconciliation-regression.php`
- `tests/r336-post-integration-release-closure-regression.php`

The R334–R336 migrations and File 00 integration proofs remain historical invariants; they now explicitly distinguish the exact pre-R337 paired evidence from the current R337 source.

## Diagnostic failed runs during correction

Intermediate failed runs were treated as diagnostic evidence, not as completion:

- `31929325072` — exposed an R337 static-test interpolation/stale expectation problem.
- `31929431221` — exposed stale R319 release-truth expectation.
- `31929917923` — reached R299 stale status-branch evidence after earlier suites passed.
- `31930023152` — reached R329 stale release-branch evidence after the R291–R328 line passed.
- `31930161004` — reached R330 forward-compatibility release-line guard.
- `31930219734` — reached R334 stale branch evidence.
- `31930289617` — reached R335 stale branch evidence.
- `31930361254` — reached R336 stale post-integration wording.

Each failure was corrected at the proven expectation/source boundary before the next exact-head run.

## Green exact-head retest before this evidence-only commit

Review Branch Integrity run `31930439265` passed on exact immutable HEAD `734c09ca58e973fd4acf1968b335d8a451b40d8d`.

That run proves, for that exact head:

- exact SHA checkout and `git diff --check`;
- lint of every PHP file on PHP `7.4` and PHP `8.3`;
- cumulative no-network security/authentication/registration/WebAuthn/migration/privacy/session/route/release regressions through R337;
- JavaScript syntax and CSS structural checks; and
- rejection of forbidden temporary correction machinery/review-only artifacts from the release boundary.

It does **not** prove Hostinger staging, deployment or operations.

## Cross-file File 00 evidence boundary

Historical paired repository integration run `31850253635` remains valid only for its exact pre-R337 File 02 input `f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9` and File 00 input `1d7f215193d778b0977c8e50d738c42e1e5f66c2` / runtime `1.2.44`.

R337 changed File 02 after that run. Therefore exact post-R337 paired File 00 `1.2.44` integration remains a separate required gate. The historical run is not silently promoted to current-head integration proof.

## Completion boundary after R337 corrections

- Specified: complete within the governing source corpus.
- Coded/source: R337 corrected candidate.
- Automated source QA: green for exact pre-evidence HEAD `734c09ca58e973fd4acf1968b335d8a451b40d8d`; this evidence-only commit requires its own fresh exact-head run.
- Packaged: not newly claimed by R337.
- Post-R337 paired File 00 integration: pending.
- Staging-Accepted: not proven.
- Live-Deployed: not proven.
- Operational: not proven.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
