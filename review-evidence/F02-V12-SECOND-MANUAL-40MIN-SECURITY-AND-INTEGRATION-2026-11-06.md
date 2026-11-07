# File 02 v12 — Second manual 40-minute source review: hardening, real MariaDB and release gates
**Dated:** 2026-11-06 (Asia/Karachi). **Scope:** Draft PR #23 only; do not merge or deploy automatically.

## Governing source, frozen identity and acceptance boundary
- **Authoritative plan:** `Sabri-Website-Master-Plan-v12.docx`, File 02 section, paragraphs 934–982: 12 FR + 10 NFR + 2 CEN + 24 X24 = **48 IDs**. The preceding exhaustive 48-ID ledger remains `review-evidence/F02-V12-MANUAL-40MIN-TRACEABILITY-AND-FIXES-2026-11-06.md`. This follow-up adds independently verified source corrections and **does not make all 48 accepted**.
- **Repository:** `majidhussainqadri1-dot/02-sabri-authentication`.
- **Frozen main:** `224c39bcb8c28f77504c7348dbad41226753c7e8`; **first-session PR #23 frozen base:** `7e88c8f1fa7ff3144a53747bc7cdb647b2f83ac0`; source/test HEAD before this document: `07eebc8e0476d84fa745c1fa0465efa370ab443f`.
- New session source/test work at the frozen head: **24 commits / 14 changed files** (source, tests, workflows, no deployed files).
- **Candidate software / DB / passkey schema:** `1.4.0 / 1.4.0 / 1.1.0`. These are repository declarations, NOT live observations.
- The pre-existing hourly recurring automation remains **paused**; manual task only. No `main`, release, package deployment, staging, live WordPress, or production DB modifications.

## RED → root-cause → minimally corrected source (six independently measured failure conditions; four conceptual defect families)
| Observation | Source flaw and impact | Red test / exact GitHub Action | Code remediation |
|---|---|---|---|
| D06 — signed JWT `iat` negative value accepted | `absint` converted negative seconds to a plausible positive current instant | DPoP PHP 7.4/8.3 run `42270499507`: `FAIL: negative iat allowed` | `includes/class-sauth-dpop.php`, commit `74e1ca3081721b39afbb67689ca6c5fa68fe4e94`; require real positive integer |
| D07 — private JWK component allowed past adapter boundary | Existing public-only check inspected `d` but not RSA `p,q,dp,dq,qi,oth,k` components | DPoP run `42270676677`: `FAIL: private JWK member allowed` | Same class, commits `7c11e04a54672ff0334d47a7aa4232bd6909c715` and `58547c5a187e1b6b9cb892868b0a01af58d01b5f` |
| D08 — premature/broad replay deletion | Cron method deleted any option with `sauth_dpop_` prefix even during validity | DPoP run `42270801962`: `FAIL: fresh replay claim deleted` | Same class `58547c5a187e1b6b9cb892868b0a01af58d01b5f`: exact SHA256 claim-key shape and 360s minimum age |
| D09 — signal `event_id` reused for other subject/type yet success reported | `SELECT event_id` returned success without verifying immutable event identity; duplicate insert races not reconciled | Real WordPress/MariaDB run `42271593610`: `reused event_id wrongly acknowledged with a different user` | `includes/class-sauth-shared-signals.php` commit `20d5440cd9fe687b3d62643763382de7d9e71f11`; exact `event_id/family/type/user_id` comparison and unique-insert race reread |
| D10 — same `event_id` with altered claims wrongly acknowledged | Source row comparison had not bound the sanitized claim contents to the existing event receipt | Real MariaDB run `42273099246`: `reused event_id wrongly acknowledged with altered claims` | Same class commit `4868a82ad1bdcbc69caf1a14b8dd12b14384bf65`; normalized scalar-claim comparison with sorted keys. D09/D10 share the same immutable-event conceptual root. |
| D11 — PII/bearer hidden in innocent claim fields | Arbitrary free-text values were persisted when claim key did not itself contain `password/token/email` | Real MariaDB run `42273716808`: `raw personal/credential data leaked in normalized CAEP claims` | Same class commit `12cccf6720184162495a3fa2b19e6f14d9d12fce`; reject unsafe identity/contact keys and free text, retain bounded code enums plus numeric/boolean risk facts |

**Status of D06–D11:** Corrected in source candidate and exercised in fresh exact-head CI. They are *not* proven live incidents and do not show absence of other defects.

### One new v12 feature gap addressed: operational Recovery Queue inspection
`F02-NFR-003/004/006/008`: previously operator `System Check` reported only a global stale/healthy condition. New bounded privacy-preserving aggregate inspection was first RED in real MariaDB CI `42272428314`: `read-only recovery queue inspection is missing`. Implemented `SAUTH_Operations::reconciliation_summary()` with at most 1000 read rows, strict fail-closed missing/large queue, zero identifiers/payload, read-only counts by stale/fresh collision, processing and other pending recovery states, on already capability-protected admin UI. Commit `1a4954f3da19335e03906135ae78ebf49f0e013a`. It never replays an uncertain File00 canonical-owner side effect. Bounded mock and real MariaDB tests added.

## Exact reproducible CI and code mapping
1. `tests/security/dpop-proof-and-replay-hardening.php` under new `.github/workflows/file02-dpop-proof-replay.yml`, PHP 7.4/8.3 negative/signed iat, private JWK members, atomic replay and expiry.
2. `tests/integration/dpop-replay-mariadb.php` under updated `.github/workflows/file02-v12-file00-pr55-wordpress701.yml`: real `wp_options` non-autoloaded replay claim, expiry hook, no private fields; **test-only signature adapter**.
3. `tests/integration/shared-signal-eventid-conflict-mariadb.php`: verified fixture exact duplicate returns same id; cross-user/type/claims event collision must deny without replacing existing row.
4. `tests/security/shared-signal-immutable-insert-race.php` under new `.github/workflows/file02-shared-signal-race.yml` (PHP 7.4/8.3): deterministic UNIQUE insert-loser reread, exact duplicate idempotency, divergent event conflict.
5. `tests/integration/shared-signal-claims-privacy-mariadb.php`: no raw email, bearer, phone, arbitrary contact/identity field; retain safe numeric `risk_score` and coded `reason`.
6. `tests/security/reconciliation-summary-bounded.php` under updated `.github/workflows/file02-recovery-claim-gate.yml`: strict query cap, fail-closed truncation/missing table/DB failure.
7. Extended `tests/integration/recovery-reconciliation-health.php`: exact aggregate changes before/after independent real MariaDB test fixtures; no private identifiers.
8. **Exact source/test SHA `07eebc8e0476d84fa745c1fa0465efa370ab443f`: GitHub 13/13 workflow executions SUCCESS, 0 failed, 0 pending.** Some were duplicate push/PR workflows, not 13 distinct feature acceptance tests.
9. Exact real integration run `42274099671`: WordPress 7.0.1/PHP 8.3/MariaDB 11.4 + pinned File00 PR55; source logs show DPoP, CAEP identity and privacy regressions PASS, along with earlier two-process recovery/collision claims, lifecycle expiry and FedCM claim tests.

## Still pending before an honest all-v12 100% acceptance
**External provider / integration dependencies:** F02-X24-020 DPoP real signature verifier (test injected verifier is NOT a crypto provider); F02-X24-021 approved FIDO attestation metadata; F02-X24-023 real FedCM IdP token validation and browser mediation; F02-X24-024 portability metadata/consumer compatibility. Do not silently treat adapter-ready source as external provider availability.

**Canonical owner contract and unverified risk:** File00 PR55 `b3e9253d06a2897f092981f3a1f77e16b4b78578` has registration/membership account contracts, but review of its `SMC_Authentication_Contract_V11`, `SMC_Authentication_Contract`, `SMC_Contracts` did not find a dedicated `sauth_resolve_collision_case_v1` final canonical-owner approval adapter. We MUST NOT invent an email-only merge or make File02 decide File00-owned identity. Generic Recovery owner failure, double-compensation failure and durable reconciliation remain additional acceptance tasks; unknown outcomes require supervised File00 reconciliation, no automated retries.

**Broader v12 48-ID work:** still need a per-requirement source+behavior+File00/24/26 consumer acceptance, real browser passkey/WebAuthn/Google FedCM, accessibility/RTL, p75/p95 metrics, PHP security/timing, third-party adapter contracts, operational Runbook, backup/restore, migration rollback, staging and final Founder approval. Privacy-safe suppression of arbitrary signal prose is a *tightened contract*; consumers/providers that previously depended on those free text keys require explicit compatibility review.

**No percentage can be responsibly inferred from 13 green workflows.** New test coverage ≠ complete 48-ID acceptance.

## Release discipline / Live Reality (five required fields)
| Field | Verified state |
|---|---|
| Repository HEAD | main `224c39bcb8c28f77504c7348dbad41226753c7e8`; second-session source candidate `07eebc8e0476d84fa745c1fa0465efa370ab443f`, before report commit |
| Deployed Version | Unknown; exact live artifact/version not fetched |
| DB Version | Live unknown (source declares `1.4.0`) |
| Migration State | Live unknown; disposable WordPress/MariaDB integrations passed only |
| Live Verification Status | Not performed; no live changes or live re-test |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**Session duration:** user requested a 40-minute manual work session, beginning approx 2026-11-06 23:20 Asia/Karachi; authoritative elapsed time must be measured from actual clock on completion. The report by itself does not certify 40 elapsed minutes.


## Second independent post-code review: File26 projection and exact-head package boundary
- Reviewed all modified PHP source classes a second time at exact PR HEAD `3daaedbc00beae2c568e7273bec0a4d3f4ff9d36`: `includes/class-sauth-dpop.php`, `includes/class-sauth-shared-signals.php`, `includes/class-sauth-operations.php`. Verified the new DPoP `iat` strict integer guard, public-only JWK members, SHA256 replay cleanup age guard; the sanitized CAEP/RISC immutable event tuple and claims compare; and bounded, aggregate-only admin queue read. This is a source review, not proof of external providers or all 48 features.
- At exact same candidate head, `File 02 Release Integrity` workflow `42274752987` succeeded across four separate jobs: source/release constitution, PHP 7.4 regressions, PHP 8.3 regressions, and deterministic package clean-extract parity. Archive generation in CI is NOT a live/staging deploy.
- **Cross-file File26 evidence freeze:** Repository `26-search-discovery-recommendations-knowledge-graph-classification`, main HEAD `1b064c6ea9f81195611ed001d5457e8ced65460a`, `includes/class-file26-owner-contracts.php` accepts owner registry entries for Files 03/05/06/07/08/10/11/12/15/18/21; **File02 is not in that current registry.** `docs/CONNECTOR-CONTRACT.md` requires owner-scoped `can_view`, versioned DTO and no identity document/raw secret payload. Governing `F02-CEN-01` therefore lacks evidence of an accepted File02→File26 safe searchable projection. Do not index authentication/session/identity data by inventing a connector or bypass the canonical owner; resolve with explicit File26 owner-domain policy, restricted object types, or a justified no-index decision and native negative tests. This is a documented integration gap, not evidence of an exploited live leak.
- At this second review, File00 PR55 `SMC_Authentication_Contract_V11::init()` still registers privacy export/erasure and manifest only; in inspected File00 authentication contracts no built-in `sauth_resolve_collision_case_v1` final-approval filter was found. Any external adapter must be separately audited. Do not call the collision wizard end-to-end completed.
