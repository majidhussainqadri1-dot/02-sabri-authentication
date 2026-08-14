# File 02 Status — Version 1.2.4

## Current candidate

- Branch: `fix/file02-account-taxonomy-parity-1.2.4`
- Version/schema: `1.2.4 / 1.2.1`; passkey table schema `1.0.1`
- Repository `main` re-verified during R319: `0f011b1876e217b7ee46f92903e5315538c1025e`
- Governing corpus: Definitive Master Plan v3.0; `SSH-F02-PLAN-2026-v1.0`; Consolidated All-Chats Directives v2.1; Continuous-Value/Top-20 Superset plan; later cross-file ownership refinement for File 02 passkey/WebAuthn ceremony
- Intended canonical repository name: `02-sabri-authentication-and-accounts` (rename not yet performed)
- Current transport repository: `02-sabri-authentication`
- Required account provider: `smc.authentication-account 1.1.0`
- Authentication-assurance producer: File 02 `smc_file02_authentication_assurance_v1` / contract `1.0.0`
- Historical incident baseline main: `8192c45b595b34e13e09934e3b2d554aa2d8553f`
- Historical defect corrected in 1.2.1: `SAUTH_Storage_Router::init()` was called after activation without loading `includes/class-sauth-storage-router.php`.

## Source candidate capabilities

- Full password and Google-first registration orchestration plus mandatory identity/guardian/completion fields passed to the File 00 owner contract.
- Password login, signed email verification, recovery/reset, Google OAuth/link/unlink, provider circuits, Safe Mode and fail-closed membership checks.
- Device/session registry, generalized device/network display, individual revoke, revoke others and sign out everywhere.
- New-device/network/recent-failure policy with elevated password risk requiring a separate File 02 passkey sign-in; retired File 00 factor codes are not treated as a File 02 ceremony.
- WebAuthn/passkey registration and usernameless authentication with HTTPS/RP ID/origin binding, required user verification and discoverable credentials.
- Server-side attestation-object CBOR parsing and COSE ES256/RS256 public-key extraction; client-supplied public keys are not trusted.
- Atomic one-time challenge replay claim, credential collision protection, signature verification, signature-counter regression containment and revoked/compromised states.
- Fresh passkey assurance projected as `owner=file02`, `contract_version=1.0.0`, `level=3`, session/fingerprint-bound and five-minute limited.
- Conservative provenance: `attestation=none` never fabricates `hardware_backed=true`.
- Passkey management uses a fresh File 02 passkey assurance when present, otherwise current-password reauthentication; retired File 00 authenticator/recovery codes are neither solicited nor accepted.
- Privacy export/erasure for passkeys, opaque random user handles, no biometric/private-key retention, privacy-minimized passkey events and bounded revoked-credential cleanup.
- Loop-safe completion resolver, canonical `/account/sessions/` route, canonical `SAUTH_` identifiers and bounded legacy aliases.
- File 01/File 20 manifests, migration/rollback/backup/incident documentation and deterministic packaging pipeline.
- File 02 1.2.4 retains the 1.2.1 storage-router bootstrap correction and adds R321–R330 fail-closed, migration/index, passkey, provider, privacy, session, UI and release-truth hardening.

## Review-cycle corrections carried into this branch

1. Missing WebAuthn/passkey ceremony required by the later approved scope.
2. Client-side public-key extraction rejected; registration derives the COSE public key only from server-parsed authenticatorData.
3. Transient-only challenge consumption race replaced with an atomic replay claim.
4. Credential lookup moved away from WordPress-salt HMAC to stable SHA-256 over opaque credential IDs.
5. Deterministic salt-bound user handles replaced with random opaque File 02 values with privacy erasure.
6. Hardware-backed inference is conservative under `attestation=none`.
7. Passkey domain events are privacy-minimized first-class events.
8. Passkey activation/deactivation lifecycle and no-network WebAuthn regression coverage were added.
9. The 1.2.0 storage-router bootstrap fatal was corrected in 1.2.1.
10. R291–R300 added dependency, Safe Mode, asynchronous privacy-job, passkey assurance, Google-flow and exact-head review hardening; R301–R310 completed the prior corrective line; R311–R318 completed further sequential review/fix/retest rounds, and R319 completed dependency/release-truth hardening; R320 completed the final adversarial review and corrective release-identity/index/CI cleanup.

## Seven separate completion gates

| Gate | Status | Evidence boundary |
|---|---|---|
| Specified | Complete | Governing File 02 + central/Continuous-Value + cross-file ownership traced |
| Source coding | **Review candidate** | Reviewable branch source reviewed through the completed R321–R330 ten-round cycle |
| Packaged | Not claimed from review branch | Release packaging is a separate gate |
| Automated-QA | Review exact-head gate | Review CI may prove lint/regression only for its exact head |
| Staging-Accepted | No | Real Hostinger/WebAuthn/provider/browser evidence absent |
| Live-Deployed | No | No production authorization/evidence for this candidate |
| Operational | No | Monitoring/support/restore evidence absent |

## External owner and environment gates

- Cross-file release blocker: File 00 canonical account taxonomy and its `smc.authentication-account 1.1.0` provider vocabulary still require owner-side harmonization; File 02 intentionally does not invent a lossy account-type remap.
- Hostinger fresh install and supported upgrade tests, including `dbDelta` creation of the passkey table and private manager page.
- Real production-domain WebAuthn tests with platform authenticators, synced passkeys and cross-platform security keys; real Google and SMTP providers.
- Accepted File 00 account/membership integration plus File 01/File 20/File 03/File 24/theme/LiteSpeed integrations.
- Real-role IDOR/CSRF/replay/race/privacy tests and privilege-loss/session-revocation tests.
- Urdu RTL, English LTR, keyboard, screen reader, 200–400% zoom, mobile and cross-browser acceptance.
- Performance/load/provider-outage tests, backup/restore and rollback rehearsal.
- Founder staging acceptance and controlled production authorization.

No source/package/staging/live/operational status may be inferred from another gate. Exact deployed code, database version, migration state and live verification remain unverified unless separate live evidence is captured.