# File 02 Rollback Guide — 1.3.0

## Rollback triggers

Containment or rollback is required for fatal activation errors, widespread sign-in or registration failure, account enumeration, unauthorized session acceptance, private-data exposure, Google callback/link collision, File 00 contract incompatibility, canonical-storage migration corruption, uncontrolled retries or severe performance regression.

## Immediate containment

1. Enable File 02 Safe Mode and preserve public reading.
2. Disable Google registration/login/linking and other high-risk mutations.
3. Record the exact File 02/File 00 heads, packages, SHA-256 values, UTC time, System Check, affected journey and trace IDs.
4. Revoke exposed challenges/sessions where required; do not perform broad deletion.
5. Preserve canonical `sauth_*` and legacy `sa_*` tables before intervention.

## Code rollback

- Reinstall the previously accepted immutable File 02 package and its compatible File 00 package.
- Do not drop additive 1.1.0 tables, columns, options, consents or File 00 private state.
- Verify the previous code tolerates the additive schema before switching.
- Clear opcode and application caches only after code replacement.
- Re-run public reading, login, logout, recovery and provider-disabled smoke tests.

## Storage rollback

Version 1.1.0 copies legacy `sa_*` rows into canonical `sauth_*` tables and routes active compatibility queries to canonical storage. It does not delete legacy tables. A code rollback may therefore use the preserved legacy tables only under the previously accepted code path. Never reverse-copy newer canonical rows without an approved migration because doing so can lose revocation, challenge, outbox or session evidence.

New accounts and membership truth remain File 00-owned. They must not be deleted merely to restore older File 02 code. City, declared account type, Ethical Conduct consent and profile-photograph completion state created through the v1.1 File 00 contract remain subject to File 00 privacy and lifecycle rules.

## Provider rollback

Restore the previous approved Google configuration or disable Google. Never copy secrets into logs, tickets or source control. Rotate a secret only under the incident procedure. A Google-first account remains a File 00 account and must retain a safe password-recovery route if the provider is disabled.

## Verification

After rollback confirm:

- no fatal/PHP/database errors;
- File 00 membership and account-contract assertions remain valid;
- current sessions obey revocation state;
- password reset and email verification do not falsely succeed;
- Google registration/login/linking is either correct or visibly disabled;
- `/account/sessions/` and any legacy redirect do not loop;
- public reading remains available;
- System Check has no unresolved blocker;
- database, files and encryption keys match the accepted backup.

A corrected package requires exact-head CI, deterministic clean-extract parity, fresh review/fix rounds, Hostinger staging regression and Founder acceptance before redeployment.
