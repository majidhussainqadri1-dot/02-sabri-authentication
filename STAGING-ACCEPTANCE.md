# File 02 Staging Acceptance — Version 1.0.0

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
- [ ] All seven File 02 tables/indexes and all managed pages are correct.
- [ ] Cron/outbox/cleanup hooks run, retry and dead-letter correctly.

## Dependencies and providers

- [ ] File 00 registration, email-completion, membership and step-up contracts pass positive/negative tests.
- [ ] File 01 manifest and File 20 route/layout placement accepted without duplicate navigation.
- [ ] HTTPS, permalinks, active theme and LiteSpeed do not break auth routes/headers.
- [ ] Real SMTP/email delivery tested for success, delay, failure and retry.
- [ ] Google OAuth tested for login/link/unlink, collision, denied consent, callback replay and outage.
- [ ] Provider timeouts/circuit breaker/Safe Mode drills pass.

## Representative journeys

- [ ] Founder, administrator, adult member, eligible minor, guardian, suspended account and security operator.
- [ ] Registration with National ID and Passport.
- [ ] Email verification: valid, expired, replayed, resent and concurrent.
- [ ] Password login: valid, invalid, brute force, unknown account and completion-only account.
- [ ] New-device/network risk challenge and invalid/exhausted step-up.
- [ ] Password recovery/reset and all-session revocation.
- [ ] Session list, current marker, individual revoke, revoke others and sign out everywhere.
- [ ] Google link/unlink and exact-email collision behavior.
- [ ] Provider/dependency failure preserves public reading and never falsely succeeds.

## Security and privacy

- [ ] CSRF, IDOR, enumeration, open redirect, replay, race, cache leakage, XSS/SQLi and malformed input tests.
- [ ] No raw secret/token/password/full IP in logs, events, exports or diagnostics.
- [ ] Privacy export/erasure/anonymization and retention cleanup pass.
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
