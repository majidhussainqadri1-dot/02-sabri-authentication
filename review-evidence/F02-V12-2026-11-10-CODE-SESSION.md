# File 02 Master Plan v12 — 10 November 2026 source remediation and acceptance ledger

> **Immutable starting source:** PR #23, `79016d52fbaa9690ddd43983fedb83dc477c9ab0`. **Isolated implementation:** stacked Draft PR #24, `audit/f02-v12-session-20261110-recovery-ui`, based on the PR #23 branch. Main `224c39bcb8c28f77504c7348dbad41226753c7e8` was not altered. **Code reviewed before this report:** `4840b63960898cb16537cf76cfadb57ab6107106`. This document is repository/source evidence only, not deployment acceptance.

## Authoritative scope and control

The user-provided `Sabri-Website-Master-Plan-v12.docx`, paragraph numbers 935–983, contains **48 unique File 02 requirement identities**: 12 FR, 10 NFR, 2 CEN, 24 X24. Do not mistake a row below for an independent end-to-end acceptance receipt. Source feature presence, CI pass, real external provider acceptance, staging, live and operations are different gates.

Source runtime 1.4.0 / DB 1.4.0 / passkey schema 1.1.0 remain **candidate declarations**, not deployed observations. File00 remains owner of identity, membership, age/guardian and authority; File02 owns authentication ceremonies. File24 and File26 interfaces are external.

## Root-cause-first source corrections in this session

| Key | Confirmed source-level problem | Bounded correction | Evidence / limitation |
|---|---|---|---|
| S01 | Missing canonical `sauth_apply_recovery_change_v1` handler could consume `pending` claim and strand it at `processing` although no owner ran | registered-owner preclaim gate; preserve pending until handler exists | dedicated PHP mock and disposable real WP/MariaDB test; File00 owner still missing/unaccepted |
| S02 | Collision POST consumed fingerprint-bound handoff transient before detecting absent canonical owner | preserve original short-lived server-side proof only for `sauth_collision_owner_unavailable`; no bearer in URLs | structural gate; real browser handoff acceptance pending |
| S03 | FedCM finish lacked fresh Safe Mode check after begin | Safe Mode fail closed before nonce claim | PHP mock negative path; real IdP pending |
| S04 | Absent FedCM verifier burned once-only nonce | check registered verifier before claim; preserve atomic claim before callback | mock RP nonce replay and verifier absence; true IdP identity verification pending |
| S05 | `report_not_me` could return success after failed session revocation or shared-signal recording | refuse false success, attempt account lockdown and return explicit typed errors | RED-first mock -> source fix -> exact-head CI |
| S06 | `smart_step_up` did not gate high risk from unavailable security-signal/device DB | maximum risk gate; score=100 fails privileged step-up | RED-first mock -> source fix -> exact-head CI; downstream File24 consumer pending |
| S07 | `emergency_lockdown` reported success when lock persistence or passkey assurance invalidation failed; AJAX could return success with `locked=false` | read-back persisted lockdown and compromise-watch, require session+assurance postconditions; send AJAX error on failure | RED-first regression and corrected source; no real account incident claimed |
| S08 | Canonical recovery owner could succeed then final `processing→applied` write fail, returning untyped `false` | typed `sauth_recovery_reconciliation_required`, preserve `processing`, prevent automatic replay | RED-first mock and corrected source; supervised File00 reconciliation still required |
| S09 | Security Timeline printed raw UTC DB text despite NFR-010 | `wp_date()` and site date/time format with explicit UTC conversion | source regression; real timezone/locale browser QA pending |
| S10 | File02 CSS used superseded primary green | v12 canonical #087A4E / dark #065C3B / soft #E7F5EE | source gate; File25/theme compatibility still pending |
| S11 | Login/signup lacked localization/RTL | 52 translatable UI locations, English base and 2 native PHP catalogs (Urdu/Arabic), browser JS translated message keys, deterministic ZIP includes catalogs | source+i18n+packaging CI; full plugin translation/RTL browser/screen-reader still open |

**Do not add together issue families, tests and acceptance rows.** Source corrections S01–S11 are **candidate branch changes**, not 11 live incidents or 11 live fixes. Repeated CI/mock failures are not additional production bugs. The first FedCM CI mock failure was due to absent WordPress `has_filter()` shim; the shim and negative tests were corrected.

## The 48 requirements — code existence vs accepted product

| Requirement | Source status (not release status) | Outstanding acceptance |
|---|---|---|
| `F02-FR-001` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-FR-002` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-FR-003` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-FR-004` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-FR-005` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-FR-006` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-FR-007` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-FR-008` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-FR-009` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-FR-010` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-FR-011` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-FR-012` | source implementation present | full real-provider/end-to-end acceptance open |
| `F02-NFR-001` | partial controls/source evidence | release-quality gate open |
| `F02-NFR-002` | partial controls/source evidence | release-quality gate open |
| `F02-NFR-003` | partial controls/source evidence | release-quality gate open |
| `F02-NFR-004` | partial controls/source evidence | release-quality gate open |
| `F02-NFR-005` | partial controls/source evidence | release-quality gate open |
| `F02-NFR-006` | partial controls/source evidence | release-quality gate open |
| `F02-NFR-007` | partial controls/source evidence | release-quality gate open |
| `F02-NFR-008` | partial controls/source evidence | release-quality gate open |
| `F02-NFR-009` | partial controls/source evidence | release-quality gate open |
| `F02-NFR-010` | partial controls/source evidence | release-quality gate open |
| `F02-CEN-01` | partial architecture | cross-file consumer acceptance open |
| `F02-CEN-02` | partial architecture | cross-file consumer acceptance open |
| `F02-X24-001` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-002` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-003` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-004` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-005` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-006` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-007` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-008` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-009` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-010` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-011` | partial/adaptor | File00 owner recovery approval; no independent real owner acceptance |
| `F02-X24-012` | partial/adaptor | File00 canonical collision approval adapter absent/unverified |
| `F02-X24-013` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-014` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-015` | partial/adaptor | real signed CAEP provider verifier |
| `F02-X24-016` | partial/adaptor | real signed RISC provider verifier |
| `F02-X24-017` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-018` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-019` | partial/adaptor | approved breach-prefix service and outage behavior |
| `F02-X24-020` | partial/adaptor | approved DPoP cryptographic verifier |
| `F02-X24-021` | partial/adaptor | approved FIDO metadata provider and revocation |
| `F02-X24-022` | source implementation present | behavioral/browser/cross-file acceptance open |
| `F02-X24-023` | partial/adaptor | real FedCM IdP and browser verification |
| `F02-X24-024` | source implementation present | behavioral/browser/cross-file acceptance open |

There are **12/12** FR with baseline source code and **24/24** X24 with at least a source scaffold/implementation; **8/24** X24 have identifiable external owner/provider adapters still open. All 10 NFR and both CEN require acceptance beyond existing source. Even source-implemented X24 features require browser/consumer/provider tests. Never infer a percentage of full completion from these numbers.

## Outstanding integration and source/acceptance work packages (13)

1. File00 canonical collision resolution/approval provider; exact actor, ownership, idempotency and no silent merge.
2. File00 canonical owner approval for the recovery change kinds and out-of-band identity evidence; no bypass or invented approval.
3. Production-grade reconciliation, retention and manual incident closure for uncertain owner side effects, transient+DB double-write failures and privacy legal holds; the source preclaim/finalization defects corrected here do not close operations.
4. Signed/authorized CAEP intake; real verifier/subject/type/idempotency and privilege/session containment.
5. Signed/authorized RISC intake; real verifier plus trusted-source mapping and revocation.
6. Real FedCM server-side IdP token verifier and browser flow with provider errors.
7. Real DPoP signature verifier and approved integration with sender-constrained OAuth tokens.
8. Real FIDO attestation metadata adapter with trust-list refresh/revocation and provenance rules.
9. File00/File24 Assurance Receipt v2 and smart step-up consumer contract acceptance.
10. File26 privacy-safe search projection connector or formal reviewed no-index decision.
11. File25/themed canonical design token parity and full mobile/RTL/a11y browser QA.
12. Exhaustive text catalogs beyond translated login/signup/passkey messages plus timezone and internationalization real-environment QA.
13. Approved breached-password prefix lookup adapter, safe provider-failure policy and end-to-end validation.

These packages are workstreams, **not 13 proven code bugs**. Some require an external contract/real authorized provider more than File02 source edits. Never manufacture crypto/identity approval callbacks solely to pass tests.

## Mandatory evidence gates before release

- Exact candidate SHA: PHP 7.4/8.3 lint, cumulative security regressions, WordPress 7.0.1/MariaDB 11.4 disposable integration and deterministic clean-extract package. Check against **the final commit after this report**, not a prior successful run.
- Confirm every one of the 48 behavioral/quality/contract acceptance receipts with evidence; 48 traceability rows are not 48 passing end-to-end tests.
- Real Google OAuth, WebAuthn conditional/hybrid, CAEP/RISC, FedCM, DPoP, FIDO metadata, SMTP outage and password breach provider tests; reject missing verifier fail-closed.
- Cross-file native ownership: File00, File20, File24, File25, File26 (and File03 profile completion); explicit File26 private/no-index policy.
- Real Urdu/Arabic/English keyboard, screen reader, zoom, performance p75/p95 and device/browser tests.
- Hostinger staging fresh/upgrade, migrations, downgrade safety, backup/restore and rollback rehearsals; Founder acceptance and explicit deployment permission.
- Live Reality Freeze *before* any live patch: active plugin package and checksum, DB/schema version/options/tables, migration state, config/dependencies, runtime error; verify source-to-deployed parity; after deployment live re-test and new parity. No live diagnosis derived solely from GitHub.

## Five mandatory reality fields

| Fact | Evidence |
|---|---|
| Repository HEAD | main `224c39bcb8c28f77504c7348dbad41226753c7e8`; session source reviewed through `4840b63960898cb16537cf76cfadb57ab6107106`, later documentation commit must be separately checked |
| Deployed Version | **Unknown**; no live plugin artifact/Hostinger access |
| DB Version | **Unknown live**; candidate schema declaration `1.4.0` only |
| Migration State | **Unknown live**; CI MariaDB is disposable |
| Live Verification Status | **Not performed**; zero live fixes verified |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**Disposition:** Keep PR #24 **Draft**, stacked on PR #23; do not independently merge/deploy, do not claim 100% complete. Production deployment and full File02 acceptance remain blocked by real owner/provider, acceptance and live-parity evidence.
