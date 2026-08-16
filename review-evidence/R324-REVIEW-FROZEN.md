# R324 — Complete Review Frozen Before Correction

Review scope: passkey/WebAuthn registration and authentication ceremonies, canonical credential schema, counter replay/quarantine, assurance binding/invalidation, challenge expiry/replay behavior, credential management, and the hardened runtime replacing mutable legacy endpoints.

No R324 correction was started before this ledger was frozen.

## Frozen defect ledger

1. **Counter-regression containment bypasses the centralized Safe Mode authority.** When credential quarantine or assurance invalidation cannot be proven, `SAUTH_Passkey_Runtime::finish_authentication()` writes `SAUTH_Operations::SAFE_MODE_OPTION` directly and destroys only WordPress session tokens. This bypasses the R321 Safe Mode revocation epoch and does not prove File 02's durable session projection was revoked.
2. **Assurance-invalidation failure has the same incomplete containment boundary.** `contain_invalidation_failure()` destroys only `WP_Session_Tokens` and returns false. It neither uses `SAUTH_Session_Manager::revoke_user_sessions()` nor establishes the centralized Safe Mode epoch, so a security-critical inability to rotate the passkey assurance epoch can leave File 02 session evidence inconsistent.

## Correction requirements

- Route both containment paths through a single runtime helper that invokes File 02 session revocation and `SAUTH_Operations::enter_safe_mode()`.
- Preserve fail-closed behavior even when durable session revocation cannot be confirmed.
- Add permanent R324 regression coverage proving no direct Safe Mode option write remains in passkey runtime and both containment paths use the common authority.
