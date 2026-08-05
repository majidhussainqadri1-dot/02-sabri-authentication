# File 02 Corrective Review — v0.2.0

## Governing boundary

File 00 — Sabri Membership Core is the sole authority for membership registration, identity evidence, member profiles, account types, WordPress roles, institutional status, approval, password/TOTP sign-in, recovery codes, and verification records.

File 02 is limited to optional Google authentication integration, explicit Google link/unlink controls, account-recovery routing, access-page integration, privacy coverage for File 02 data, and authentication-specific rate limiting.

## Defect closure matrix

| Review defect | Corrective implementation | Status |
|---|---|---|
| File 02 bypassed Membership Core | Added mandatory dependency adapter and fail-closed runtime/activation behavior | Corrected |
| File 01 dependency was obsolete | Removed File 01 runtime/menu dependency; Unified Application Shell is optional admin parent | Corrected |
| File 02 created roles and users | Removed all role creation, user creation, and direct password login | Corrected |
| Incomplete registration was marked complete | Legacy registration now delegates to Membership Core; no File 02 completion flag is written | Corrected |
| Email ownership was not verified | Registration is exclusively delegated to Membership Core email-verification workflow | Corrected |
| Profile editing could replace WordPress roles | File 02 profile endpoint cannot mutate roles or account types and delegates to Membership Core | Corrected |
| Google email auto-linked local accounts | Google linking is explicit, nonce-protected, requires a signed-in approved Membership Core account, and requires exact email match | Corrected |
| Google sign-in bypassed Membership Core 2FA | Google identity verification is followed by Membership Core Authenticator or recovery-code verification | Corrected |
| Legacy Google links were ambiguous | Only explicit link version 2 is accepted for login; legacy links must be re-linked | Corrected |
| Concurrent Google linking could create duplicate ownership | Added an atomic option-based link lock and duplicate recheck immediately before metadata write | Corrected |
| Google unlink left other authenticated sessions active | Unlink now destroys other WordPress sessions while preserving the current protected session | Corrected |
| Google metadata was inconsistent | Link version, identifier, email, verified flag, stable link timestamp, last-login timestamp, and optional picture are written consistently | Corrected |
| Privacy erasure was incomplete | Export and erasure cover all Google metadata, legacy `_sa_*` metadata, and the legacy WordPress biography written by File 02 | Corrected |
| Private pages could be indexed/cached | Added noindex, nofollow, noarchive, X-Robots-Tag, no-store, private/no-cache, no-referrer, frame, content-type, and permissions headers, including dependency-failure mode | Corrected |
| Transient rate limiter was non-atomic | Added a dedicated atomic fixed-window database counter table, fixed-window fallback, per-challenge and per-user 2FA limits, and success resets | Corrected |
| Existing slugs could be adopted blindly | Managed-page updates use the stored page map, ownership metadata, exact-shortcode adoption, and idempotent collision handling | Corrected |
| Mutable GitHub Action tags | Corrective CI pins checkout and setup-php to immutable commit SHAs | Corrected |
| Secret scan was narrow | Corrective CI scans source and governance files, blocks credential/archive filenames, and checks broader credential patterns | Corrected |
| Baseline verifier could be self-rewritten | Corrective CI verifies the immutable original baseline commit remains an ancestor and validates the release lock/inventory separately | Strengthened |

## Deliberately unresolved outside File 02

The following require separate staging or File 00/File 20 work and are not falsely claimed as complete here:

- live Google Cloud consent-screen and callback validation;
- browser-level OAuth and cookie behavior;
- WordPress database migration against the real staging database;
- File 00 security and privacy audit;
- File 20 visual/application-shell acceptance;
- penetration testing and production authorization.

## Merge gate

Do not merge or deploy until:

1. corrective integrity and PHP lint jobs pass;
2. an independent code review is recorded;
3. staging tests cover dependency failure, link, unlink, linked login, TOTP, recovery code, logout, password reset, privacy export/erasure, noindex/no-cache headers, and legacy-link rejection;
4. no unresolved blocker remains.
