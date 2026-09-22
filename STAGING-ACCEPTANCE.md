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
