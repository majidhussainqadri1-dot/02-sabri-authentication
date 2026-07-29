# Corrective QA

`architecture-check.py` enforces the non-negotiable File 02 boundaries:

- File 00 remains the only membership, role, profile, and verification authority.
- File 02 cannot create users, roles, capabilities, or a parallel password login.
- Google linking is explicit, nonce-protected, email-matched, concurrency-locked, approved-account-only, and followed by Membership Core 2FA.
- Private account pages emit indexing, cache, frame, content-type, permissions, and referrer protections.
- Legacy File 02 metadata and the legacy WordPress biography are covered by privacy export and erasure.
- Rate limiting uses the dedicated atomic counter table, with a fixed-window fallback.
- Managed compatibility pages are updated idempotently and do not overwrite unrelated pages.

`security-unit.php` runs without WordPress or network access and checks:

- AES-256-GCM encryption, authenticated decryption, and tamper rejection.
- Local redirect validation and cryptographic token length.
- Google issuer, audience, authorized-party, nonce, email-verification, issue-time, and expiry rules.
- Explicit Google-link metadata completeness and legacy-link rejection.

These checks are structural and deterministic. They do not replace WordPress integration, live Google OAuth staging, browser, email-delivery, privacy-process, concurrency, or penetration testing.
