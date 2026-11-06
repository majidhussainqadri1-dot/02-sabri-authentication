# File 02 — Master Plan v12, manual 40-minute source remediation and 48-ID review ledger (2026-11-06)

## Authority / frozen source truths
- Governing sole plan: original `Sabri-Website-Master-Plan-v12.docx`, File 02 section, paragraphs 934–982; do not confuse repository `STATUS.md` historic text with deployed truth.
- Source repository: `majidhussainqadri1-dot/02-sabri-authentication`. Base `main` HEAD `224c39bcb8c28f77504c7348dbad41226753c7e8`; manual-work start Draft PR #23 HEAD `3905a5e2b1b9fdbdc9eb4e721b724dd57fd61096`; checked source/test HEAD before this ledger `47090db085f449b541f7bb1566fdce9c3a1cd583`.
- Draft branch: `audit/f02-v12-run45-source-evidence-20261106` (unmerged). File02 candidate software/DB/passkey versions `1.4.0 / 1.4.0 / 1.1.0`. Exact independent File00 PR55 source pinned `b3e9253d06a2897f092981f3a1f77e16b4b78578` in new CI; prior legacy integration still tests older File00.
- **Scope:** Source-only candidate code changes, isolated PHP and real disposable WordPress 7.0.1 / MariaDB 11.4 / PHP 8.3 test containers, plus PHP 7.4/8.3 mocks. No deploy, merge, live read/write, staging, migration on any actual site.
- **Source signals or self-reported `Implemented` statuses do not equal 48/48 full behavioral acceptance.** Every row below is a traceability pointer and explicit evidence limitation, not a success certificate.

## Confirmed source defects: 5 found, 5 corrected in this bounded manual session
| ID | Proven root cause | RED / failure first | Minimal corrected source |
| --- | --- | --- | --- |
| D01 | Generic recovery scheduler accepted `collision_resolution` which the generic executor correctly denies; could create non-executable pending rows | CI `42257452523`: `generic scheduler created a dedicated collision-resolution case` | `includes/class-sauth-security-orchestrator.php`, fix `548f30aad3be340e1e292485dce4615674ed1e25`; reject at scheduling before storage |
| D02 | 600s collision proof expired but stale pending collision DB row did not transition to `expired`; 30d retention never covered still-pending case | Real MariaDB RED `42259157514`: `cleanup lifecycle mismatch` | `SAUTH_Security_Orchestrator::cleanup()`, fix `0a9f771f6806d248648e6bdcc3ac84b8cba6a64b`; expire aged collision pending only, leave in-flight/other recovery untouched |
| D03 | Invalid FedCM RP nonce deleted a legitimate nonce transient before validation | PHP 7.4/8.3 RED `42259729873`: `invalid nonce destructively consumed valid request` | `includes/class-sauth-modern-auth.php`, fix `877655cf91b9019ef16f3ba02f6d50e35e76fa86`; validate first |
| D04 | Concurrent FedCM transient readers could invoke verified IdP callback twice; `get_transient+delete_transient` not atomic | PHP 7.4/8.3 RED `42259852463`: `FedCM nonce replay succeeded after a cached concurrent read` | `includes/class-sauth-modern-auth.php`, fix `78f98c723973ba17da02240f2547a4408df3eaa9`; bounded unique `add_option` replay claim and narrowly scoped scheduled expiry |
| D05 | Unreconciled aged `processing` and stale pending collision cases invisible to operator System Check | Real MariaDB RED `42260541557`: `stale recovery backlog invisible to System Check` | `includes/class-sauth-operations.php`, fix `23e3111e48969399c0aaba98a0566a7c4e290ff3`; read-only privacy-safe backlog indicator |

Test-harness issue **separate from production defects**: WP-CLI `eval-file` local/global scoping produced an invalid blank table name; corrected only isolated test fixture at `1af3279b41f3b2ba9a2ad5484563c35b9f6f5e62` before accepting D02 RED. It is not a sixth production bug.

## New tests and reproducible CI evidence
- `tests/security/collision-generic-schedule-denial-regression.php` + isolated mock, `.github/workflows/f02-collision-generic-isolation.yml`.
- `tests/security/fedcm-nonce-once-mock.php` + `.github/workflows/file02-fedcm-one-time.yml`, PHP 7.4 and 8.3, invalid nonce, cache-race replay, cleanup isolation.
- `tests/integration/recovery-once-mariadb-concurrency.php`: **two real WordPress PHP processes** on MariaDB; WIN/LOSE and one test-only owner callback receipt; no actual File00 mutating owner callback used.
- `tests/integration/collision-once-mariadb-concurrency.php`: two real WordPress PHP processes for dedicated collision; one WIN, one LOSE, one applied row/owner test receipt.
- `tests/integration/collision-expiry-mariadb.php`: expired/fresh/generic/processing isolation and real 30d purge.
- `tests/integration/recovery-reconciliation-health.php`: read-only unresolved backlog indicator on real DB and data suppression.
- `tests/integration/fedcm-claim-expiry-mariadb.php`: actual WP Cron hook and atomic non-autoloaded `wp_options` claim, expired claim cleanup; no foreign options touched.
- New `.github/workflows/file02-v12-file00-pr55-wordpress701.yml` pins exact current File00 PR55 and installs WordPress **7.0.1** with MariaDB **11.4**. Old integration workflow is retained; neither stands for live parity.
- At **exact code/test SHA `47090db085f449b541f7bb1566fdce9c3a1cd583` GitHub Actions concluded 11/11 SUCCESS, 0 FAIL**. Distinguish 11 workflow executions (including push/PR repetition) from 11 unique independent test designs. Real MariaDB collision job `42261766522`: one WIN/one LOSE, applied status, fixture cleaned. Real recovery job `42258432789`: winner/loser and only one test-only owner callback. All of this is scoped, not complete auth/browser production acceptance.

## 48 v12 requirement IDs — source map, NOT 48 approvals
Every row has status **B/C** (source/test proof partial; distinct integration, browser, File00-owner, staging/Founder gates remain), unless stated more specifically. Labels and requirements are summarized from governing v12; source paths are code-candidate pointers, not tested end-to-end on each row.

| ID | v12 topic | Repository pointer(s) | Remaining evidence / distinction |
|---|---|---|---|
| `F02-FR-001` | Registration | `includes/class-sa-registration.php` | Baseline source; exhaustive end-to-end, guardian and File00 owner pending |
| `F02-FR-002` | Email verification | `includes/class-sauth-email-verification.php` | Source + prior R341; real SMTP deliverability/outage pending |
| `F02-FR-003` | Password authentication | `includes/class-sa-registration.php` | Source and older CI; exhaustive rate/risk pending |
| `F02-FR-004` | Google OAuth | `includes/class-sa-google-oauth.php` | OIDC source; live provider callback/browser pending |
| `F02-FR-005` | Account linking | `includes/class-sa-google-oauth.php` | Fresh assurance exists; identity collision real provider test pending |
| `F02-FR-006` | Password recovery | `includes/class-sa-registration.php` | One-time source; real notifications and all-session E2E pending |
| `F02-FR-007` | Session management | `includes/class-sauth-session-manager.php` | Source; real cross-device browser test pending |
| `F02-FR-008` | Login risk | `includes/class-sauth-login-risk.php` | Source + modern risk; graded device/browser acceptance pending |
| `F02-FR-009` | Account completion | `includes/class-sauth-completion-resolver.php` | File00 real CI bounded; all roles/minors pending |
| `F02-FR-010` | Safe redirects | `includes/class-sa-security.php` | Source; adversarial full browser E2E pending |
| `F02-FR-011` | Audit outbox | `includes/class-sauth-event-outbox.php` | Source; dead-letter operational observability pending |
| `F02-FR-012` | Provider degraded UX | `includes/class-sauth-provider-health.php` | Source; real outage/recovery exercise pending |
| `F02-NFR-001` | Object/field authorization | `includes/class-sa-access-control.php` | IDOR/owner matrix not fully independently accepted |
| `F02-NFR-002` | Privacy and retention | `includes/class-sa-privacy.php\`, \`includes/class-sauth-security-orchestrator.php` | Aged collision expiry now real MariaDB green; whole lifecycle/legal hold pending |
| `F02-NFR-003` | Reliability, retry and reconciliation | `includes/class-sauth-event-outbox.php\`, \`includes/class-sauth-security-orchestrator.php` | One-time recovery/collision real DB claims green; uncertain owner side effects and durable repair ledger pending |
| `F02-NFR-004` | Performance | `includes/class-sauth-session-manager.php` | Measured p75/p95 performance/load budgets not proven |
| `F02-NFR-005` | Accessibility | `assets/js/authentication.js\`, \`templates/login.php` | Browser/keyboard/screen-reader/RTL acceptance not proven |
| `F02-NFR-006` | Observability | `includes/class-sauth-operations.php` | System Check source; live metrics/alert ownership not proven |
| `F02-NFR-007` | Migration/rollback | `includes/class-sa-activator.php\`, \`MIGRATION.md` | Fresh and legacy MariaDB CI present; real staging backup/restore/rollback pending |
| `F02-NFR-008` | Operability | `includes/class-sauth-operations.php` | New read-only privacy-safe recovery backlog health real DB green; operator remediation/runbook pending |
| `F02-NFR-009` | WP7.0.1 and PHP8.3 compatibility | `.github/workflows/file02-v12-file00-pr55-wordpress701.yml` | Fresh 7.0.1/8.3/MariaDB11.4/PR55 CI green; deployed parity external |
| `F02-NFR-010` | Localization | `assets/css/authentication.css\`, \`assets/js/authentication.js` | Urdu/Arabic RTL and timezone/date formatting browser acceptance not proven |
| `F02-CEN-01` | Owner, free/green/privacy and File26 projection | `CONTRACTS.md\`, \`includes/class-sa-membership-adapter.php` | Cross-file File26 search/native owner data acceptance unverified |
| `F02-CEN-02` | Owner writes, versioned reads/events/index | `CONTRACTS.md\`, \`includes/class-sauth-account-contract.php` | All object/field/consumer authorization negative paths not individually proven |
| `F02-X24-001` | Conditional passkey autofill | `assets/js/authentication.js` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-002` | 180-second upgrade window | `includes/class-sauth-modern-auth.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-003` | Capability adaptive login | `assets/js/authentication.js` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-004` | Hybrid passkey UX | `assets/js/authentication.js` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-005` | Credential reconciliation signals | `assets/js/authentication.js` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-006` | Credential-label sync | `assets/js/authentication.js` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-007` | Security timeline | `includes/class-sauth-security-orchestrator.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-008` | This Was Not Me | `includes/class-sauth-security-orchestrator.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-009` | Emergency lockdown | `includes/class-sauth-security-orchestrator.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-010` | Strong-method downgrade | `includes/class-sauth-security-orchestrator.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-011` | Recovery cooling-off | `includes/class-sauth-security-orchestrator.php` | New bounded real MariaDB acceptance green; actual File00 owner side-effects/failure pending |
| `F02-X24-012` | Collision resolution | `includes/class-sauth-security-orchestrator.php` | New bounded real MariaDB acceptance green; actual File00 owner side-effects/failure pending |
| `F02-X24-013` | Adaptive risk v2 | `includes/class-sauth-security-orchestrator.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-014` | Explainable risk reasons | `includes/class-sauth-security-orchestrator.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-015` | CAEP access evaluation | `includes/class-sauth-shared-signals.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-016` | RISC signal integration | `includes/class-sauth-shared-signals.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-017` | Assurance receipt v2 | `includes/class-sauth-security-orchestrator.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-018` | Per-action step-up | `includes/class-sauth-security-orchestrator.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-019` | Breached password prefix | `includes/class-sauth-password-safety.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-020` | DPoP / sender-constrained JWT | `includes/class-sauth-dpop.php` | External verifier/provider or browser proof pending |
| `F02-X24-021` | FIDO trust metadata | `includes/class-sauth-fido-trust.php` | External verifier/provider or browser proof pending |
| `F02-X24-022` | Related-origin passkeys | `includes/class-sauth-modern-auth.php` | Source mapped; full behavioral/browser/provider acceptance not individually established |
| `F02-X24-023` | FedCM federated login | `includes/class-sauth-modern-auth.php` | External verifier/provider or browser proof pending |
| `F02-X24-024` | Credential portability metadata | `includes/class-sauth-modern-auth.php` | External verifier/provider or browser proof pending |

### Highest-priority unresolved acceptance/risk register (not countable as proven source defects without RED evidence)
1. `F02-X24-011/012`: test-only owner filter in real MariaDB race DOES NOT prove actual File00 canonical owner side-effect idempotency, 2-connection rollback on crashes, or safe reconciliation of unknown owner outcomes. A durable externally reviewable incident/repair ledger on simultaneous transient + DELETE + UPDATE failure is still unproven. Operator System Check indicator is observability, NOT an automatic fix.
2. `F02-X24-023`: browser FedCM + actual IdP cryptographic token verifier and provider incident tests absent. New nonce tests prove only test-controlled callbacks, exact WordPress option state and fail-closed identity boundary.
3. `F02-X24-020/021/024`: DPoP signature trust adapter, FIDO metadata trust service, and portability/crypto exchange are adapter-ready, not certified live provider integrations.
4. `F02-NFR-001/004/005/006/007/010`: complete authorization matrix, p75/p95, mobile/desktop browsers, keyboard/RTL/accessibility, end-to-end operational alert ownership, real backup/restore and full upgrade/rollback acceptance pending.
5. `F02-CEN-01/02`: actual File26 search and all File00 owner/consumer projections not independently certified; all 48 source-to-test-to-Founder acceptance receipts still outstanding.
6. Exact deployed artifact checksum/runtime version, Live DB/schema/options, migration receipts and Live parity **not collected**. Original user's directive defers live until authorized and post-source coding. No claim of live fix.

## Time, deployment and audit boundary
The user's manual 40-minute session was started **2026-11-06 16:55:51 UTC / 21:55:51 Asia/Karachi**, requested target 40 minutes. **The total completed duration must be computed from actual final timestamp, not inferred here.** This document records completed work and an evidence snapshot, not a claim that 40 minutes have already elapsed.
The pre-existing hourly automation was independently confirmed **paused**. Do not resume it.
Repository HEAD: baseline main `224c39bcb8c28f77504c7348dbad41226753c7e8`; corrected draft was `47090db085f449b541f7bb1566fdce9c3a1cd583` before adding this note (this note produces later separate exact HEAD). Deployed Version: unknown. Live DB Version: unknown. Migration State: unknown. Live Verification Status: unverified/deferred.
**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
