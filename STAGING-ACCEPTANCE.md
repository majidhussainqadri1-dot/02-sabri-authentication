## V12.3 P11 package-folder transition guard — 14 November 2026

**DISPOSABLE TEST PASS; STAGING/LIVE ACCEPTANCE NOT PERFORMED.** Source plugin path in current File02 integration tests is `wp-content/plugins/sabri-authentication/sabri-authentication.php`. The deterministic 1.4.0 SOURCE-CANDIDATE ZIP deliberately installs under `wp-content/plugins/02-sabri-authentication/sabri-authentication.php` (see `tools/build-package.sh` and `RELEASE-LOCK.json.package_folder`). WordPress distinguishes these directories as separate plugin identities. This is a deployment-identity risk, **not proof of a current live bug**.

- [x] Exact candidate ZIP can install and activate on disposable WordPress 7.0.1/MariaDB 11.4 using the `02-sabri-authentication` folder **after** explicitly deactivating the `sabri-authentication` source installation. The CI test asserts only one File02 slug is active and the installed 1.4.0 DB/account provider remains ready.
- [ ] Obtain **actual live active File02 path** and deployed bytes from authorized Hostinger P00; never infer it from old test fixtures.
- [ ] On an isolated restored staging clone, identify all currently installed File02 plugin directories, network/site activation and residual content. Preserve site/network plugin activation and database row evidence.
- [ ] Approved operator must design a reversible folder transition or same-directory update **without two active File02 copies**, lost account hooks, orphaned cron registrations or version/schema marker false positives. ZIP extraction alone does not grant permission to change active plugin identity.
- [ ] Exercise legacy 1.3.x → 1.4.0 one-way DB migration and passkey 1.0.1 → 1.1.0 with actual installed folder identity; test real sessions, encrypted data, options, safe mode, canonical pages and restoration.
- [ ] Capture clean staging archive SHA-256, manifest, SBOM, restored backup/rollback command and post-activation plugin-path/digest parity.
- [ ] Require File00/24/Founder authorization before Production GO. If plugin identity is uncertain, halt and perform Deployment-Parity Audit.
- [ ] After deployment (only if authorized), re-freeze actual folder/hash/options/schema and execute real live sign-in/recovery/safety re-test. No claim of Resolved without final live parity.

The disposable ZIP install assertion **does not** test a site/network-activated Hostinger in-place upgrade, persistent browser sessions, CDN/cache behavior, real Google/WebAuthn providers or irreversible recovery data changes.

---

## Additional v12 candidate acceptance barriers — 10 November 2026 Draft PR #26

- [ ] On a clone and actual configured custom breached-password callback, unavailable or null response must prevent a password change; opt-in HIBP remains disabled unless approved, and no raw/full hash can leave the environment.
- [ ] Recovery `apply_after` UTC broken/overflow/future timestamps never invoke File00 owner; verify legal delays, concurrency, and audit.
- [ ] Canonical Collision owner action completed then DB final state write fails: no replay, explicit reconciliation, strict 600-second proof.
- [ ] DPoP JWT htu refuses signed query/fragment/userinfo and JOSE alg/key-type mismatch; approved real JWS verifier confirms signature before replay claim.
- [ ] Security Timeline values and historical rows audited for sensitive data; no unapproved PII/bearer retention under applicable privacy/hold policy.
- [ ] Timeline DB fault injection renders a visible unavailable alert, not false empty history.
- [ ] This Was Not Me when compromise-watch write fails never returns contained success; review emergency session containment and user-notification semantics.
- [ ] Source CI `exit(string)` negative-test false-green permanently excluded by verified nonzero-exit audit; all 20 source fixtures re-run on exact commit, PHP 7.4 and 8.3.
- [ ] Security Center + Collision Wizard Urdu/Arabic/RTL browser, touch and screen-reader workflows accepted; disposable WordPress PHP catalogs are insufficient alone.
- [ ] Hostinger Live Reality Freeze + exact deployed artifact/DB and migrated state verified before any Production claim.

# File 02 Staging Acceptance — Version 1.4.0 Modern Authentication 24

Staging must prove fresh-install and supported 1.3.x upgrade to runtime/DB/passkey `1.4.0 / 1.4.0 / 1.1.0`; all prior R343 behavior; the three new tables; all F02-X-24-001..024 source-to-runtime paths; conditional/hybrid passkeys on real browsers; browser credential signals; security timeline/not-me/lockdown; recovery cooling/collision flow; CAEP/RISC replay/authorization; Assurance v2; password privacy; DPoP/FIDO adapters where configured; exact related-origin manifest; FedCM only with real server verifier; privacy export/erasure; accessibility/RTL/performance; backup/restore/rollback. **Modern Authentication 24** is not production-accepted merely because repository CI passes.

> **Current authoritative candidate — File 02 1.4.0 / DB 1.4.0 / passkey schema 1.1.0.**  
> This repository candidate carries the Founder-approved **Modern Authentication 24** amendment (F02-X-24-001..024) forward onto the post-R343 source line. It preserves passkey assurance v1 `1.0.0` and adds Modern Auth `1.0.0`, Authentication Assurance Receipt v2 `2.0.0`, and Shared Signals `1.0.0`. Repository coding/CI/package/staging/live/operational gates remain separate. Any lower-version “current candidate” wording below is retained only as historical provenance unless explicitly restated here.

# File 02 Staging Acceptance — Version 1.3.0

This checklist proves real-environment acceptance; repository CI alone cannot complete it.

## Immutable inputs

- [ ] Exact File 02 source head, package SHA-256, manifest and SBOM recorded.
- [ ] Compatible File 00 exact head/package and account/assurance contracts recorded.
- [ ] Sanitized access-controlled Hostinger-equivalent staging clone.
- [ ] Database/files/keys backup restored successfully in isolation.
- [ ] Rollback owner, decision-maker and observation window named.

## Installation and migration

- [ ] Fresh install succeeds.
- [ ] Every supported upgrade path succeeds and is idempotent.
- [ ] Deactivate/reactivate preserves data and routes.
- [ ] Non-destructive uninstall behavior verified.
- [ ] All eight File 02 tables/indexes (seven authentication tables plus `sauth_passkeys`) and all managed pages are correct; canonical version/schema markers are published only after storage postconditions.
- [ ] With the storage router active, a preserved `sa_*` row copies into its `sauth_*` destination and the DB `1.3.0` migration marker is published only after the logical-identity readback succeeds.
- [ ] Cron/outbox/cleanup hooks run, retry and dead-letter correctly.

## Dependencies and providers

- [ ] File 00 registration, email-completion and membership/eligibility contracts pass positive/negative tests; retired File 00 factor codes are not treated as File 02 authentication.
- [ ] File 01 manifest and File 20 route/layout placement accepted without duplicate navigation.
- [ ] HTTPS, permalinks, active theme and LiteSpeed do not break auth routes/headers.
- [ ] Real SMTP/email delivery tested for success, delay, failure and retry.
- [ ] Google OAuth tested for login/link/unlink, collision, denied consent, callback replay and outage.
- [ ] Provider timeouts/circuit breaker/Safe Mode drills pass.

## Representative journeys

- [ ] Founder, administrator, adult member, eligible minor, guardian, suspended account and security operator.
- [ ] Registration with National ID and Passport.
- [ ] Email verification: valid, expired, replayed, resent and concurrent.
- [ ] Email issuance delivery failure, stale `issuing`, concurrent resend and verification claim/readback failures remain recoverable and never falsely succeed.
- [ ] Password login: valid, invalid, brute force, unknown account and completion-only account.
- [ ] New-device/network risk allow/challenge/deny behavior, including File 02 passkey step-up and unavailable-passkey fail-closed behavior.
- [ ] Password recovery/reset and all-session revocation.
- [ ] Session list, current marker, individual revoke, revoke others and sign out everywhere.
- [ ] Google login/link/unlink and exact-email collision behavior, risk evaluation, rollback postconditions and linkage-failure containment.
- [ ] Concurrent Google registration/link/login/unlink requests serialize on the same subject/user lock namespace without duplicate owners or orphaned accounts.
- [ ] Provider/dependency failure preserves public reading and never falsely succeeds.

## Security and privacy

- [ ] CSRF, IDOR, enumeration, open redirect, replay, race, cache leakage, XSS/SQLi and malformed input tests.
- [ ] No raw secret/token/password/full IP in logs, events, exports or diagnostics.
- [ ] Privacy export pagination, passkey export/erasure/assurance-epoch cleanup, anonymization and retention cleanup pass.
- [ ] More than 50 canonical and legacy rows erase over repeated batches; device/risk export and recursive outbox identity removal are verified.
- [ ] Backup restore and rollback preserve newly created File 00 accounts correctly.

## UX/accessibility/performance

- [ ] 320–1920px, Urdu RTL plus English LTR, keyboard, focus, screen reader, 200%/400% zoom and reduced motion.
- [ ] Chrome, Edge, Firefox, Safari and representative Android/iOS.
- [ ] Slow network, JavaScript failure and session expiry have clear recovery states.
- [ ] Route-specific latency/query/provider budgets measured and accepted.

## Final gates

- [ ] Two fresh post-final-code review/fix rounds completed with affected regression suites.
- [ ] Zero known blocker/critical defect; residual risk register explicitly approved.
- [ ] Founder functional, visual, business, privacy and safety acceptance recorded.
- [ ] Production deployment, monitoring thresholds and rollback window approved.
