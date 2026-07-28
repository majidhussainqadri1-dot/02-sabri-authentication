# Status

## Current state

**File 02 v0.2.0 corrective source prepared — automated QA and independent review required**

## Corrected

- File 00 is mandatory and exclusive membership/role/profile/verification authority.
- File 01 runtime dependency removed.
- Direct File 02 registration, user creation, password login, and role mutation removed.
- Legacy login, registration, and profile routes delegate to File 00.
- Google accounts require nonce-protected, explicit same-email linking to an approved Membership Core account.
- Google link and login require Membership Core TOTP or recovery code.
- Legacy Google associations cannot authenticate until explicitly re-linked.
- Google link metadata is consistent; linking is concurrency-locked; unlink is protected by 2FA and revokes other sessions.
- Atomic database-backed fixed-window rate limiting, fixed-window fallback, and per-user 2FA limits added.
- File 02 privacy export and erasure cover Google data, legacy `_sa_*` data, and the legacy WordPress biography.
- All File 02 and private File 00 account pages use noindex/noarchive/no-store/private, no-referrer, frame, content-type, and permissions controls.
- Idempotent page ownership validation and fail-closed dependency behavior added, including privacy headers when the dependency is unavailable.
- Corrective source inventory, checksums, lock, architecture test, no-network security unit tests, full-repository secret scanning, and pinned CI prepared.

## Still required

- GitHub corrective workflow success.
- Independent code review.
- WordPress staging activation and database-upgrade test.
- File 00/File 20 integration test.
- Google Cloud OAuth consent and callback test.
- Browser tests for link, login, 2FA, recovery code, unlink, logout, password reset, headers, and privacy tools.
- Security and privacy acceptance.
- Production approval.

## Authorization

- Corrective development candidate: **Pending CI**
- Staging candidate: **No**
- Production release: **No**
- Live installation authorized: **No**
- Merge authorized: **No**
