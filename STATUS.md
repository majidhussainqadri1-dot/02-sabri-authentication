# File 02 Status

## Current state

**Version 0.4.0 full-plan harmonization is in progress on `codex/file02-full-plan-harmonization-0.4.0`.**

This branch starts reconciliation with `SSH-F02-PLAN-2026-v1.0`. It is a source-development candidate only.

## Completed in the first harmonization batch

- Created a versioned, fail-closed File 00 account-orchestration consumer boundary.
- Preserved File 00 as the sole identity, membership, guardian, role, verification and MFA-policy owner.
- Added a privacy-minimized versioned authentication-event outbox with retry and dead-letter states.
- Added canonical password-reset completion with password validation, event/audit evidence and all-session revocation.
- Added authenticated session summary, generalized device/network presentation, revoke-other-sessions and sign-out-everywhere controls.
- Added canonical `/login`, `/register`, `/forgot-password`, `/reset-password` and `/account-sessions` managed-page specifications.
- Aligned plugin and database candidate versions to `0.4.0`.
- Added `PLAN-TRACEABILITY.md` with every F02 functional and non-functional requirement status.

## Still required

- Full File 02 registration form and accepted File 00 registration transaction provider.
- Signed email-verification issuance/consumption/resend lifecycle.
- Native File 02 email/password authentication and new-device/risk challenge.
- Safe opaque per-session registry for individual session revocation.
- Account-completion resolver and loop prevention.
- Full event coverage and provider-outage/circuit-breaker UX.
- File 01 route registry and File 20 placement contracts.
- System Check, outbox inspection, metrics, alerts and guarded repair.
- Complete migration/rollback, privacy/retention, authorization/IDOR, accessibility/RTL, browser/device, load and restore evidence.
- Two fresh review/fix rounds after the final implementation change.
- Deterministic installable package, SBOM, manifest, checksums and source/package parity.
- Hostinger staging with real File 00, Google OAuth and email-provider integration.
- Founder acceptance and controlled production deployment.

## Authorization

- Specified: **Complete in governing plan**
- Coded: **Partial 0.4.0 candidate**
- Packaged: **No**
- Automated QA: **Pending for current head**
- Staging accepted: **No**
- Live deployed: **No**
- Operational: **No**
- Merge authorized: **No**
