# File 02 Status — Version 1.3.0

## Current candidate

- Branch: `agent/file02-comprehensive-remediation-1.3.0`
- Version/schema: `1.3.0 / 1.3.0`; passkey table schema `1.0.1`
- Repository `main` re-verified during R319: `0f011b1876e217b7ee46f92903e5315538c1025e`
- Governing corpus: Definitive Master Plan v3.0; `SSH-F02-PLAN-2026-v1.0`; Consolidated All-Chats Directives v2.1; Continuous-Value/Top-20 Superset plan; later cross-file ownership refinement for File 02 passkey/WebAuthn ceremony
- Intended canonical repository name: `02-sabri-authentication-and-accounts` (rename not yet performed)
- Current transport repository: `02-sabri-authentication`
- Required account provider: `smc.authentication-account 1.1.0`
- Authentication-assurance producer: File 02 `smc_file02_authentication_assurance_v1` / contract `1.0.0`
- Historical incident baseline main: `8192c45b595b34e13e09934e3b2d554aa2d8553f`
- Exact current cross-file repository integration: **PENDING** for File 02 `1.3.0`; File 00 remains pinned to `1d7f215193d778b0977c8e50d738c42e1e5f66c2` / runtime `1.2.44`.
- Historical paired evidence: run `31850253635` proved File 02 `1.2.6`, not this candidate.

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
- R334/R335 correct real MariaDB passkey migration: dbDelta-safe one-index-per-line DDL plus exact reconciliation of the MariaDB-preserved stale `credential_lookup_hash => credential_hash` unique-index binding.
- R336 binds architecture release identity to `RELEASE-LOCK.json` and records exact cross-file integration closure without advancing external deployment gates.
- R337 replaces query-text migration bypass with an explicit router suspension scope; hardens email issuance/verification CAS state transitions; serializes Google subject/user mutations with database locks; proves exact WordPress/File 02 session binding; closes passkey backup/counter/RSA gaps; and makes privacy export/erasure bounded, canonical-and-legacy aware and postcondition-driven.

## Seven separate completion gates

| Gate | Status | Evidence boundary |
|---|---|---|
| Specified | Complete | Governing File 02 + central/Continuous-Value + cross-file ownership traced |
| Source coding | **Review candidate** | R337 comprehensive remediation implemented; exact-head CI pending |
| Packaged | Pending exact-head Release Integrity | Deterministic package proof must come from this exact head |
| Automated-QA | Pending exact-head R337 run | Local parser/static/package checks do not replace PHP 7.4/8.3 and real MariaDB CI |
| Staging-Accepted | No | Real Hostinger/WebAuthn/provider/browser acceptance evidence absent |
| Live-Deployed | No | No production authorization/evidence for this candidate |
| Operational | No | Monitoring/support/restore evidence absent |

## Cross-file repository integration evidence

The current File 02 `1.3.0` / File 00 `1.2.44` integration gate is **open** until the exact candidate head passes WordPress 7.0 / MariaDB 11.4. It must prove fresh activation, all nine canonical account types, the legacy passkey upgrade, logical-identity collision preservation, the repaired active-router legacy copy and final paired boundaries.

Exact run `31850253635` remains valid historical evidence for File 02 `1.2.6 / DB 1.2.1 / passkey 1.0.1`; it cannot be promoted to current-head evidence.

This does not substitute for Hostinger staging or live deployment evidence.

## External owner and environment gates

- Hostinger fresh install and supported upgrade acceptance against the exact packaged candidate.
- Real production-domain WebAuthn tests with platform authenticators, synced passkeys and cross-platform security keys; real Google and SMTP providers.
- File 01/File 20/File 03/File 24/theme/LiteSpeed integrations beyond the exact File 00 gate proven above.
- Real-role IDOR/CSRF/replay/race/privacy tests and privilege-loss/session-revocation tests.
- Urdu RTL, English LTR, keyboard, screen reader, 200–400% zoom, mobile and cross-browser acceptance.
- Performance/load/provider-outage tests, backup/restore and rollback rehearsal.
- Founder staging acceptance and controlled production authorization.

No source/package/staging/live/operational status may be inferred from another gate. Exact deployed code, live database version, live migration state and live verification remain unverified unless separate live evidence is captured.
