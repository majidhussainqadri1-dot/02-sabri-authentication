# Changelog

All notable changes to Sabri Authentication and Accounts are recorded here.

## 1.3.3 — Live Passkey Assurance Cycle Correction

### Corrected

- Fixed the live-proven circular File 00 ↔ File 02 authentication-assurance dependency that deleted a valid session-bound passkey receipt during File 00 capability/membership evaluation.
- Made `SAUTH_Passkey_Runtime::current_assurance()` a pure authentication-evidence projection over the current subject/session plus a valid session-bound receipt; it no longer invokes File 00 membership authorization.
- Preserved authorization at consumer boundaries: Google linking still requires `SA_Membership_Adapter::can_use_google()` before fresh passkey assurance, and File 00 retains independent membership/capability/revalidation authority.
- Added permanent R340 regression coverage and extended cumulative CI through the R34x line.
- Preserved DB `1.3.0`, passkey schema `1.0.1` and passkey assurance contract `1.0.0` unchanged.

### Identity

- Runtime: `1.3.3`.
- File 02 DB schema: `1.3.0` unchanged.
- Passkey schema: `1.0.1` unchanged.
- Passkey assurance contract: `1.0.0` unchanged.
- Repository/CI/package success is not a live-resolution claim; deployment, live passkey → Google-link → callback retest and parity confirmation are still required.

## 1.3.2 — File 00 Canonical Membership Route Contract Correction

### Corrected

- Removed the File 02 adapter's invented `sabri_profile`, `sabri_security_center` and `sabri_verification_status` membership page-map keys and their parallel `/sabri-*` fallback paths.
- Bound File 02 membership/profile completion to File 00 `application` → `/membership-application/`, membership security to `security` → `/membership-security/`, and membership verification/status to `status` → `/membership-status/`.
- Preserved File 24 ownership of the platform-wide `/sabri-security-center/` namespace and avoided manufacturing duplicate membership/profile pages outside File 00.
- Added permanent R339 no-network regression coverage that rejects the invented keys/slugs and proves runtime resolution through the exact File 00 keys.
- Extended the exact WordPress 7.0 / MariaDB 11.4 File 00 integration workflow to prove the material File 00 page map and the File 02 canonical route resolution.
- Preserved all R338 passkey-index reconciliation logic unchanged.

### Identity

- Runtime: `1.3.2`.
- File 02 DB schema: `1.3.0` unchanged.
- Passkey schema: `1.0.1` unchanged.
- Passkey assurance contract: `1.0.0` unchanged.
- Repository QA, staging, deployment and live route verification remain separate gates; this source correction is not a live-resolution claim.

## 1.3.0 — Comprehensive Concurrency, Evidence and Privacy Remediation

### Corrected

- Replaced query-text self-identification in legacy migration with an explicit, nested-safe storage-router suspension and advanced the File 02 DB marker so supported installations rerun the repaired copy.
- Converted email-verification issuance and consumption to compare-and-set transitions with delivery/publication/readback containment.
- Serialized Google registration, login, linking and unlinking on shared database subject/user locks; added exact subject, link, session and rollback postconditions.
- Required password, Google and passkey success to own the exact WordPress token, File 02 session projection and durable device/risk evidence before emitting success.
- Made session registration return an exact result, failed closed when lazy legacy projection cannot be proved, and routed user-facing “revoke others” through the exact-token postcondition helper.
- Enforced immutable WebAuthn backup eligibility, non-zero counter regression including reset-to-zero, assertion-time algorithm/key-shape validation, RSA keys of at least 2048 bits with exponent 65537, exact challenge receipts and assurance/session invalidation.
- Released every passkey enrollment lock before responding and removed any unproven credential row when the enrollment readback fails.
- Bounded privacy export/erasure to 50-row batches, covered canonical and preserved legacy stores, exported device/risk state and recursively removed identity-bearing event fields.
- Preserved File 00 as identity/membership/eligibility authority and validated professional reauthentication provider contract, purpose, scope and trace provenance.

### Identity

- Runtime: `1.3.0`.
- File 02 DB schema: `1.3.0`.
- Passkey schema: `1.0.1` unchanged.
- Passkey assurance contract: `1.0.0` unchanged.
- Exact-head QA/integration, staging, live deployment and operational status remain separate gates.

## 1.2.6 — Legacy Passkey Index Reconciliation Candidate

### Corrected

- MariaDB 11.4 proof established that renaming `credential_lookup_hash` to legacy `credential_hash` preserves the unique key name `credential_lookup_hash` while rebinding that key to the legacy column.
- Before dbDelta, File 02 now detects only that exact misbound unique-index state, fails closed on unexpected bindings, preserves legacy uniqueness under a legacy key name when necessary, and frees the canonical key name for the canonical column/index.
- The correction is idempotent and data-preserving; the intended physical schema is unchanged.

### Identity

- Runtime: `1.2.6`.
- File 02 DB schema: `1.2.1` unchanged.
- Passkey schema: `1.0.1` unchanged.
- Passkey assurance contract: `1.0.0` unchanged.
- Staging-Accepted, Live-Deployed and Operational remain unclaimed.

## 1.2.5 — Passkey dbDelta Migration Compatibility Candidate

### Corrected

- Real WordPress 7.0 / MariaDB 11.4 upgrade rehearsal proved that the passkey CREATE TABLE statement placed all index definitions on one line, causing `dbDelta()` to misparse later `UNIQUE KEY` / `KEY` tokens into an invalid primary-key ALTER.
- Each passkey index definition is now emitted on its own SQL line, preserving the exact intended schema while making existing-table reconciliation dbDelta-compatible.
- Permanent R334 regression coverage rejects the former combined-key line and preserves the one-index-per-line invariant.

### Identity

- Runtime: `1.2.5`.
- File 02 DB schema remains `1.2.1`.
- Passkey schema remains `1.0.1`; passkey assurance contract remains `1.0.0`.
- Staging-Accepted, Live-Deployed and Operational remain unclaimed.

## 1.2.4 — Canonical Account Taxonomy Parity Candidate

### Corrected

- File 02 public account choices now use the File 00 canonical taxonomy directly: `member`, `patient`, `student`, `doctor`, `teacher`, `researcher`, `pharmacy`, `clinic`, `publisher`.
- Provider-only `clinic_staff` and `institution_representative` aliases are no longer exposed as File 02 account choices; no lossy remap is performed.
- Permanent R331/R332 regressions preserve taxonomy parity, release identity and non-live completion boundaries.

### Identity

- Runtime: `1.2.4`.
- File 02 DB schema identity remains `1.2.1`.
- Passkey schema identity remains `1.0.1`; passkey assurance contract remains `1.0.0`.

## 1.2.3 — R321–R330 Corrective Hardening Candidate

### Corrected

- Centralized Safe Mode entry/revocation semantics and idempotent bootstrap/migration containment.
- Evidence-honest password reset, bounded recovery/resend retries and single-encoding redirect continuity.
- Google OIDC state-cookie persistence and linkage containment through File 02 session/Safe Mode authority.
- Passkey quarantine/assurance-invalidation containment, session-risk unknown states and non-consuming provider-health projections.
- Safe Mode provider-setting mutation block, verified settings rollback, high-volume privacy-erasure continuation and stable logical-identity legacy migration.
- Permanent release/documentation/integration gates synchronized to the R321–R330 line; staging/live/operational status remains unclaimed.

### Identity

- Runtime: `1.2.3`.
- File 02 DB schema identity remains `1.2.1`.
- Passkey schema identity remains `1.0.1`; passkey assurance contract remains `1.0.0`.

## 1.2.2 — R311–R320 Final Corrective Hardening Candidate

### Corrected

- Fail-closed risk-storage and provider-HTTPS boundaries; email/recovery provider-circuit behavior; Google half-open probe ownership.
- Material DB/page/passkey migration postconditions, including canonical passkey-column reconciliation and security-critical index/uniqueness proof.
- Hardened passkey runtime, Safe Mode ceremony completion, credential quarantine and epoch-aware assurance consumption.
- Session-revocation, privacy export/erasure/anonymization and operational-system-check postconditions.
- Canonical route/redirect encoding, evidence-honest provider UI, touch targets and release/dependency documentation truth.
- Permanent PHP 7.4/8.3 cumulative regressions plus current File 00/real MariaDB fresh-install and legacy passkey upgrade integration.
- Removal of temporary correction workflows and historical corrective payload files from the final candidate.

### Identity

- Runtime: `1.2.2`.
- File 02 DB schema identity: `1.2.1`.
- Passkey schema identity: `1.0.1`; passkey assurance contract remains `1.0.0`.
- Staging/live/operational completion remains unclaimed.

## 1.2.1 — WordPress-Integration-Proven Storage Router Bootstrap Correction

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
- Historical 1.2.0 review initially coupled passkey management to File 00 step-up; the later ownership correction retires that coupling. Current File 02 management uses fresh File 02 passkey assurance or current-password reauthentication.
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
