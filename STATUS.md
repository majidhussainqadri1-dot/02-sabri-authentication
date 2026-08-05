# File 02 Status

## Current state

**Version 0.4.0 full-plan harmonization is in progress on `codex/file02-full-plan-harmonization-0.4.0`.**

This branch reconciles the implementation with `SSH-F02-PLAN-2026-v1.0`. It remains a source-development candidate only and is not authorized for merge, staging installation or production deployment.

## Completed source work

- Created a versioned, fail-closed File 00 account-orchestration consumer boundary.
- Preserved File 00 as the sole identity, membership, guardian, role, verification and MFA-policy owner.
- Added a full File 02 registration surface for name, email, phone, password, age/sex, address, country, identity-reference, guardian-reference and consent handoff.
- Added registration rate limits, idempotency and generic duplicate/provider-failure handling without parallel user or role creation.
- Added signed one-time email verification with 30-minute expiry, HMAC-only local token storage, resend throttle, explicit user confirmation, canonical-email binding, provider handoff, idempotency and concurrent replay protection.
- Added native File 02 email/username and password authentication using WordPress password APIs, unknown-account dummy hashing, generic errors, brute-force controls and File 00 membership/completion rechecks before session creation.
- Added a privacy-minimized versioned authentication-event outbox with retry and dead-letter states.
- Added canonical password-reset completion with password validation, event/audit evidence and all-session revocation.
- Added authenticated session summary, generalized device/network presentation, revoke-other-sessions and sign-out-everywhere controls.
- Added canonical `/login`, `/register`, `/verify-email`, `/forgot-password`, `/reset-password` and `/account-sessions` managed-page specifications.
- Added privacy export/erasure coverage for the local email-verification challenge without exposing token hashes.
- Applied the platform green primary identity, logical responsive CSS, focus-visible styling and reduced-motion handling.
- Added `PLAN-TRACEABILITY.md`, architecture guards and no-network policy tests for registration, email verification and password authentication.

## Fresh review-and-fix evidence for this batch

The post-implementation adversarial review found and corrected:

- a verification-delivery mismatch risk between submitted email and the canonical File 00/WordPress account email;
- a concurrent verification replay path that could emit duplicate completion evidence;
- disabled native form validation on the registration surface;
- stale architecture-guard expectations from the former File 00-delegated login/registration design;
- incomplete privacy export/erasure coverage for the new verification challenge;
- inconsistent authentication-route ownership and the obsolete orange primary visual token.

## Exact-head automated evidence

Exact head: `e54e69554c4c26da1a19aeaf8ff9da7dcdc1807c`.

GitHub Actions run `31033582209` completed successfully:

- `0.4.0 source, architecture and public-safety integrity`: success;
- `PHP 7.4 lint and runtime suites`: success;
- `PHP 8.3 lint and runtime suites`: success;
- existing security, CF-01 assurance, professional reauthentication and plan-harmonization suites: success;
- registration, age/guardian, password-policy and verification-token policy suite: success.

## Still required

- Accepted and staging-tested File 00 account-orchestration provider implementation.
- Safe opaque per-session registry for individual session revocation.
- New-device/location/risk challenge policy and File 00/File 24 step-up integration for password sign-in.
- Formally loop-safe account-completion resolver and state-transition tests.
- File 19/provider delivery integration and provider circuit breakers.
- File 01 route registry and File 20 placement contracts.
- System Check, outbox inspection, metrics, alerts and guarded repair.
- Complete migration/rollback, privacy/retention, authorization/IDOR, accessibility/RTL, browser/device, load and restore evidence.
- Two fresh review/fix rounds after the final implementation change.
- Deterministic installable package, SBOM, manifest, checksums and source/package parity.
- Hostinger staging with real File 00, Google OAuth and email-provider integration.
- Founder acceptance and controlled production deployment.

## Authorization

- Specified: **Complete in governing plan**
- Coded: **Substantial partial 0.4.0 candidate; remaining Must scope listed above**
- Packaged: **No**
- Automated QA: **Green on exact head `e54e69554c4c26da1a19aeaf8ff9da7dcdc1807c` within the defined repository scope**
- Staging accepted: **No**
- Live deployed: **No**
- Operational: **No**
- Merge authorized: **No**
