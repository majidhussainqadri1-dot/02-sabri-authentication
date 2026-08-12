# File 02 Status — Version 1.2.1

## Current candidate

- Branch: `fix/live-bootstrap-storage-router-1.2.1`
- Version/schema: `1.2.1 / 1.2.0`; passkey table schema `1.0.0`
- Governing corpus: Definitive Master Plan v3.0; `SSH-F02-PLAN-2026-v1.0`; Consolidated All-Chats Directives v2.1; Continuous-Value/Top-20 Superset plan; later File 00 Advanced Trust boundary for File 02 passkey/WebAuthn ceremony
- Canonical repository name: `02-sabri-authentication-and-accounts`
- Current transport repository: `02-sabri-authentication`
- Required account provider: `smc.authentication-account 1.1.0`
- Advanced Trust producer: File 02 `smc_file02_authentication_assurance_v1` / contract `1.0.0`
- Incident baseline main: `8192c45b595b34e13e09934e3b2d554aa2d8553f`
- Live-proven repository defect corrected in this candidate: `SAUTH_Storage_Router::init()` was called after activation without loading `includes/class-sauth-storage-router.php`.

## Source coding completed

- Full password and Google-first registration orchestration plus all mandatory identity/guardian/completion fields.
- Password login, signed email verification, recovery/reset, Google OAuth/link/unlink, provider circuits, Safe Mode and fail-closed membership checks.
- Device/session registry, generalized device/network display, individual revoke, revoke others and sign out everywhere (CV-006 source scope).
- New-device/network/recent-failure challenge with File 00-owned MFA/step-up policy.
- WebAuthn/passkey registration and usernameless authentication (CV-005) with HTTPS/RP ID/origin binding, required user verification and discoverable credentials.
- Server-side attestation-object CBOR parsing and COSE ES256/RS256 public-key extraction; client-supplied public keys are not trusted.
- Atomic one-time challenge replay claim, credential collision protection, signature verification, signature-counter regression containment and revoked/compromised states.
- Fresh passkey assurance projected to File 00 as `owner=file02`, `contract_version=1.0.0`, `level=3`, session/fingerprint-bound and five-minute limited.
- Conservative provenance: `attestation=none` never fabricates `hardware_backed=true`; File 00 may therefore keep stronger hardware-backed-only actions closed until separately proven.
- Passkey management page with fresh reauthentication; when File 00 stronger authentication policy is required, password-only passkey changes fail closed.
- Privacy export/erasure for passkeys, opaque random user handles, no biometric/private-key retention, privacy-minimized passkey events and bounded revoked-credential cleanup.
- Loop-safe completion resolver, canonical `/account/sessions/` route, canonical `SAUTH_` identifiers and bounded legacy aliases.
- File 01/File 20 manifests, migration/rollback/backup/incident documentation and deterministic packaging pipeline.
- File 02 1.2.1 now loads `class-sauth-storage-router.php` before `sauth_start_plugin()` can invoke `SAUTH_Storage_Router::init()`.

## Fresh defects corrected during the 1.2.x completion

1. Missing WebAuthn/passkey ceremony required by the later central/Advanced Trust scope.
2. Initial client-side public-key extraction design was rejected; registration now derives the COSE public key only from server-parsed authenticatorData.
3. Initial transient-only challenge consumption had a concurrency replay race; an atomic `add_option` claim now makes completion single-use.
4. Initial credential lookup used a WordPress-salt HMAC and would break on salt rotation; lookup now uses stable SHA-256 over opaque credential IDs.
5. Initial deterministic user handle depended on WordPress salts; user handles are now random opaque File 02 values with privacy erasure.
6. Initial hardware-backed inference could overclaim provenance under `attestation=none`; hardware-backed is now conservatively false without attested proof.
7. Passkey domain events were initially absent from the event allowlist; registered/authenticated/revoked facts are now first-class privacy-minimized events.
8. Passkey activation/deactivation lifecycle and no-network WebAuthn regression suite were added.
9. Real WordPress cross-repository testing exposed a bootstrap fatal in 1.2.0: `SAUTH_Storage_Router` existed in the package but its source file was not required. Version 1.2.1 corrects that exact defect and adds permanent bootstrap/package guards.

## Seven separate completion gates

| Gate | Status | Evidence boundary |
|---|---|---|
| Specified | Complete | Governing File 02 + central/Continuous-Value + Advanced Trust ownership traced |
| Source coding | **Complete candidate** | Reviewable 1.2.1 branch source including bootstrap correction and Passkey/WebAuthn |
| Packaged | Pending current exact-head CI | Deterministic builder must produce byte-identical 1.2.1 package |
| Automated-QA | Pending current exact-head CI | PHP 7.4/8.3, JS, architecture, bootstrap, WebAuthn, policy, packaging and File 00 integration |
| Staging-Accepted | No | Real Hostinger/WebAuthn/provider/browser evidence absent |
| Live-Deployed | No | No production authorization for 1.2.1 |
| Operational | No | Monitoring/support/restore evidence absent |

## External owner and environment gates

- Hostinger fresh install and supported upgrade tests, including `dbDelta` creation of the passkey table and private manager page.
- Real production-domain WebAuthn tests with platform authenticators, synced passkeys and cross-platform security keys; real Google and SMTP providers.
- Accepted File 00 Advanced Trust consumer behavior plus File 01/File 20/File 03/File 24/theme/LiteSpeed integrations.
- Real-role IDOR/CSRF/replay/race/privacy tests and privilege-loss/session-revocation tests.
- Urdu RTL, English LTR, keyboard, screen reader, 200–400% zoom, mobile and cross-browser acceptance.
- Performance/load/provider-outage tests, backup/restore and rollback rehearsal.
- Founder staging acceptance and controlled production authorization.

No source/package/staging/live/operational status may be inferred from another gate. Source coding is complete only at the candidate level until exact-head QA closes; staging and production claims remain explicitly prohibited without their own evidence.
