# Changelog

All notable changes to Sabri Authentication and Accounts are recorded here.

## 1.2.1 — Live-Proven Storage Router Bootstrap Correction

### Corrected

- Added the missing main-bootstrap `require_once` for `includes/class-sauth-storage-router.php` before `sauth_start_plugin()` can invoke `SAUTH_Storage_Router::init()`.
- Preserved DB schema `1.2.0`, File 00 account-contract minimum `1.1.0`, passkey schema/assurance `1.0.0` and all 1.2.0 authentication behavior.
- Added permanent source and packaged-bootstrap guards so a runtime class may not be invoked without its source being loaded.
- Added real WordPress/MariaDB integration against the exact File 00 1.2.43 correction candidate, including activation plus subsequent independent WordPress reloads.
- Advanced deterministic package, release lock, SBOM and current release identity to `1.2.1` without a schema bump.

### Proven incident boundary

The defect was reproduced by a cross-repository WordPress run after corrected File 00 satisfied `smc.authentication-account 1.1.0`: File 02 1.2.0 activated, then the next request fatally failed because `SAUTH_Storage_Router` had not been loaded. This patch corrects that exact owning source defect; staging/live resolution remains a separate gate.

## 1.2.0 — Four-Plan Passkey and Advanced Trust Source Candidate

### Added

- File 02-owned WebAuthn/passkey registration, usernameless authentication and credential revocation for the later CV-005 strong-login requirement.
- Required resident credentials and user verification with exact canonical RP ID/origin/challenge binding.
- The server-side `attestationObject` CBOR decoder, authenticator-data parser and COSE EC2/RSA public-key extractor establish registration key trust.
- ES256 P-256 and RS256 verification using PHP OpenSSL; a browser-supplied public key is never trusted.
- Atomic one-time challenge claim to close concurrent replay, five-minute challenge expiry and privacy-minimized client-fingerprint binding.
- Stable SHA-256 credential-ID lookup across WordPress salt rotation plus encrypted credential-ID presentation copy.
- Random opaque WebAuthn user handles with privacy erasure.
- Non-zero signature-counter regression containment and synchronized-passkey zero-counter compatibility.
- Fresh five-minute passkey assurance projection for File 00 Advanced Trust: owner `file02`, contract `1.0.0`, level 3, session/fingerprint-bound.
- Passkey manager page, File 20/File 01 route/contract manifest entries, passkey health checks, guarded repair and cleanup lifecycle.
- Passkey privacy export/erasure and privacy-minimized registered/authenticated/revoked event facts.
- No-network WebAuthn tests for origin/challenge/UV, CBOR/COSE parsing, server-derived keys and signatures.

### Corrected during fresh security review

- Rejected an initial design that could have trusted browser-extracted public-key material; registration now trusts only the server-parsed authenticator public key.
- Closed transient-only challenge replay race with an atomic unique option claim.
- Replaced salt-dependent credential lookup with stable SHA-256 over random WebAuthn credential IDs.
- Replaced salt-derived user handles with random opaque handles.
- Removed false `hardware_backed` inference under `attestation=none`; hardware provenance remains false unless independently proven.
- Prevented password-only passkey management when File 00 two-factor protection is enabled; current passkey or File 00 step-up is required.
- Added passkey events to the bounded authentication event allowlist and passkey schema to System Check/repair.

### Ownership

- File 02 owns password/Google/passkey authentication ceremony.
- File 00 remains membership, identity, role, guardian, verification, authorization and MFA-policy owner and independently decides whether File 02 assurance is sufficient.
- File 24 remains security/risk assurance governance, not a duplicate credential backend.

## 1.1.0 — Three-Plan Source Candidate

### Added

- Secure Google-first account registration with state, nonce, PKCE S256, fingerprint binding, exact issuer/audience/azp/time checks, verified email and one-time registration context.
- Required city and declared account-type fields.
- Separate versioned Ethical Conduct consent and profile-photograph completion acknowledgement.
- Adult-only professional/institutional account declarations.
- File 00 `smc.authentication-account 1.1.0` contract for parent-plan fields.
- Canonical nested `/account/sessions/` route and permanent compatibility redirect.
- Canonical `SAUTH_` constants/classes/actions/options with bounded legacy aliases.
- Three-plan architecture/source guards and deterministic retained artifacts.

### Corrected

- Missing city, account type, profile-photo requirement and Ethical Conduct consent.
- Password-only initial registration limitation.
- Non-canonical account-session route.
- Stale earlier status/release records.
- File 00 completion state that omitted later parent-plan fields.

## 1.0.0 — Full File 02 Source Candidate

- Added File 00 registration contract, signed email verification and password authentication.
- Added new-device/network/recent-failure risk evaluation and File 00-owned step-up.
- Added loop-safe account-completion resolver and same-origin routing.
- Added Google login/link/unlink with state, nonce, PKCE and collision protection.
- Added opaque session registry and scoped revocation.
- Added privacy-minimized event outbox, provider circuit breakers, Safe Mode and System Check.
- Added additive schemas, privacy lifecycle, non-destructive uninstall and deterministic package builder.

## 0.3.0

- Added File 00-owned step-up assurance and professional reauthentication bridges.
- Removed direct access to private File 00 MFA storage.

## 0.2.0

- Made File 00 a hard dependency and removed parallel role/profile ownership.
- Hardened Google linking, rate limiting and private-page headers.

## 0.1.0

- Initial authentication and account foundation.
