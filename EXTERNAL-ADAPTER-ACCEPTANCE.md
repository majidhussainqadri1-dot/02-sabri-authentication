# File 02 v12 — External adapters, operator gates and deployment acceptance

## Security ownership
File00 owns identity, membership, guardian and eligibility. File02 owns password, Google OIDC, passkeys and authentication assurance ceremonies. File24 consumes bounded risk/assurance projections and does not replace native enforcement. File25 owns semantic visual tokens. File26 explicitly has no private authentication-document owner connector requirement at pinned SHA.

## Provider readiness
Never consider the existence of a WordPress callback proof that the provider is configured, authentic, trusted, deployed or staging-accepted. `SAUTH_Operations::external_release_gate_summary()` reports `missing` or `registered_unverified`, and **always** `acceptance:not_verified`. Those statuses never grant permission or start an external provider.

## Password prefix range
Local weak-password blocklist is always active. A custom approved callback can provide a validated prefix range. Built-in HIBP adapter is deliberately OFF unless `define('SAUTH_HIBP_PASSWORD_CHECK_ENABLED', true);` is supplied as an **explicit environment configuration** after legal/privacy/provider approval. It sends **only the first five uppercase SHA-1 hex characters** to `https://api.pwnedpasswords.com/range/{prefix}` via the WordPress safe HTTP client; it sends no raw password or full digest. HTTPS certificate verification, no redirects, bounded bytes and `Add-Padding: true` are mandatory. A configured provider's error, incomplete or malformed response **rejects a password change**; an unconfigured provider retains local-only enforcement. Do not turn on in production before live policy and outage rehearsals. Never log secrets, tokens or full hashes.

## DPoP and cryptographic trust
Proof structure/claims/issued-at/HTM/HTU/ATH and public JWK thumbprint are checked by File02. A registered external `sauth_dpop_verify_signature_v1` callback must perform **real JOSE cryptographic verification** before an atomic replay claim can be accepted. No callback means deny. File02 contains mock CI signing approvals only in tests, never in production. FIDO metadata/attestation trust has the same independent provider gate; attestation=none provides no hardware provenance.

## Source vs operations
Owner mutation uncertainty remains in `processing` until a supervised File00 outcome has been verified; never auto-replay. Missing owner should not consume pending claim. No one should treat 48 traceability IDs, 22 green CI checks or deterministic package as 48 independent production acceptance receipts.

## Four-stage acceptance
1. **Exact repository**: independent changed-file audit, PHP 7.4/8.3, WordPress 7.0.1/MariaDB disposable tests, package checksum, PR reviews.
2. **Staging**: approved File00/24/25/26 deployment parity; real Google/SMTP/FedCM/FIDO/DPoP or explicit disabled policy; keyboard/screen reader/RTL; p75/p95; full upgrade/backups/restore/rollback and failure injection.
3. **Pre-deploy Live Reality Freeze**: active plugin version; deployed byte-for-byte package/checksum; tables/indexes/rows; stored `sauth_version`, `sauth_db_version`, `sauth_passkey_schema_version`, Safe Mode and migration state; runtime config/error; File00 dependency and same exact fingerprint.
4. **Deploy and Live**: founder authorization, source/package parity, guarded migration, real-session/critical routes re-test, last-success and check logs, explicit post-deploy parity receipt. Without live retest status remains `not_verified`.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
