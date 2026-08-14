# File 02 Migration Guide — 1.2.2

## Migration model

File 02 migration remains additive, idempotent and non-destructive. The 1.2.2 candidate advances File 02 DB identity to `1.2.1` and passkey schema identity to `1.0.1`; migration readiness now proves required columns and security-critical indexes, and legacy passkey credential columns are reconciled before successful markers are published.

`SAUTH_Activator::repair()` continues to reconcile the original authentication schema. `SAUTH_Passkeys::maybe_install()` creates/reconciles the passkey table and manager page through WordPress `dbDelta`; guarded repair forces this passkey reconciliation even if its stored schema marker is stale.

File 02 never migrates or mutates File 00 roles, membership approvals, account-class decisions, guardian truth, identity evidence, MFA policy or verification decisions.

## Supported paths

1. Fresh installation of 1.2.2.
2. Upgrade from every repository-supported File 02 release to 1.2.2.
3. Upgrade from legacy `sa_*` tables/options/pages to canonical `sauth_*` storage and names.
4. Upgrade from 1.1.0/1.2.0 to 1.2.2 with additive passkey table/page creation and no password/Google/session data loss.
5. Deactivate/reactivate without data loss; passkey cleanup cron is safely unscheduled/recreated.
6. Re-run the same migration/guarded repair after interruption.
7. Roll back code while preserving newer File 02 data; destructive passkey deletion is not part of ordinary rollback/uninstall.

## Pre-migration gates

- Record exact source head, package SHA-256, manifest and SBOM.
- Verify File 00 `smc.authentication-account 1.1.0`, current membership assurance, and the Advanced Trust consumer compatibility for File 02 passkey assurance; retired File 00 factor codes are not a File 02 ceremony.
- Verify HTTPS canonical origin, OpenSSL support, PHP/WordPress requirements and database privileges.
- Back up database, WordPress files and encryption-key configuration; prove isolated restore. A dedicated `SA_MASTER_KEY` (32+ characters) is mandatory before enabling or migrating an encrypted Google Client Secret.
- Enable Safe Mode before upgrading a populated environment.
- Capture System Check, existing `sa_*`/`sauth_*` table counts, passkey table existence, routes and options.

## Execution

1. Install the exact deterministic 1.2.2 package only after its release build is separately produced and approved; registration/provider/passkey mutations remain gated.
2. Activate or run guarded File 02 repair.
3. Confirm `sauth_version` is `1.2.2`, `sauth_db_version` is `1.2.1`, and `sauth_passkey_schema_version` is `1.0.1`.
4. Confirm the seven prior canonical `sauth_*` tables plus `sauth_passkeys` and their indexes exist.
5. Confirm the private `account-passkeys` manager page exists and remains `noindex/no-store` through File 02 private-page controls.
6. Compare legacy/canonical row counts; duplicate keys may reduce copied row counts only where canonical rows already exist.
7. Confirm `/account/sessions/` resolves and `/account-sessions/` redirects without open redirect/loop.
8. Confirm outbox/email/risk/session/provider/passkey cleanup schedules.
9. Run password, Google-first registration, email verification, recovery, session and passkey journeys before reopening high-risk actions.

## Passkey upgrade and compatibility rules

- Version 1.2.0 does not auto-enroll a passkey and does not convert passwords/TOTP/recovery codes into passkeys.
- Existing users remain valid under their prior approved authentication methods; passkey enrollment is an explicit authenticated action.
- Passkey registration generates a random opaque user handle and derives the public key only from server-parsed authenticator data.
- Existing WordPress salts can be rotated without changing the stable credential-ID lookup hash; encrypted exclusion/presentation copies fail closed if key material changes unexpectedly.
- Legacy File 02 passkey rows using `credential_hash` / `credential_cipher` are reconciled non-destructively into canonical `credential_lookup_hash` / `credential_id_ciphertext` columns before schema completion is accepted; incomplete copies fail the migration postcondition.
- File 00 receives only the versioned fresh passkey-assurance projection; no File 00 private MFA storage is imported into File 02.
- Passkey storage, canonical manager-page and cleanup-schedule postconditions are mandatory activation/guarded-repair postconditions. If they fail, File 02 fails closed, does not publish a successful version/schema marker, enters/retains containment as applicable, and must not claim password/Google authentication availability from that incomplete migration.

## Reconciliation

- No unexplained deletion is allowed.
- The original seven canonical tables remain active for their domains; `sauth_passkeys` becomes the sole File 02 passkey credential source when successfully created.
- Legacy tables remain read-only rollback evidence by policy; new baseline runtime queries are routed to canonical storage.
- Page/option compatibility mirrors must equal canonical values.
- File 00 city/account type/Ethical Conduct/profile-photo completion state must match registration receipts.
- Passkey assurance must identify `owner=file02`, contract `1.0.0`, be session/fingerprint-bound and remain fresh enough for File 00's independent policy checks.

## Failure policy

- Do not drop/truncate/destructively downgrade canonical, legacy or passkey tables.
- Enable Safe Mode and preserve safe public reading.
- Capture redacted System Check and exact failure point; never log credential IDs, signatures, private keys or raw session material.
- Restore the previous code package if necessary while preserving 1.2.0 data.
- Resume idempotent migration only after defect correction and retest.

## Cutover and closure

Legacy/passkey purge is not part of ordinary migration or uninstall. A destructive purge requires a separate Founder-approved change request, backup/restore proof, dependency scan, staging rehearsal, privacy/retention approval and rollback plan. The GitHub repository rename is also an owner-level administration action and does not alter the stable package slug.
