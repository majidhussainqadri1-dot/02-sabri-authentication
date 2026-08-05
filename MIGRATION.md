# File 02 Migration Guide — 1.1.0

## Migration model

All File 02 changes are additive, idempotent and non-destructive. `SAUTH_Activator::repair()`:

1. creates/reconciles seven canonical `sauth_*` tables through WordPress `dbDelta`;
2. invokes `SAUTH_Activator::migrate_legacy_tables()` to copy pre-1.1 `sa_*` rows through bounded `INSERT IGNORE` operations;
3. preserves legacy tables as rollback evidence;
4. routes retained compatibility SQL to canonical storage through `SAUTH_Storage_Router`;
5. reconciles managed pages and canonical option mirrors;
6. registers `/account/sessions/` and preserves the old page only as a redirect;
7. migrates the Google secret to encrypted canonical storage;
8. ensures the anti-enumeration dummy password hash; and
9. records runtime/schema version `1.1.0`.

File 02 never migrates or mutates File 00 roles, membership approvals, account-class decisions, guardian truth, identity evidence or verification decisions. File 00 separately upgrades its versioned authentication-account provider to 1.1.0.

## Supported paths

1. Fresh installation.
2. Upgrade from every repository-supported File 02 release to 1.1.0.
3. Upgrade from legacy `sa_*` tables/options/pages to canonical `sauth_*` storage and names.
4. Deactivate/reactivate without data loss.
5. Re-run of the same migration after interruption.
6. Rollback to the prior code package while preserved legacy tables remain available.

## Pre-migration gates

- Record exact source head, package SHA-256, manifest and SBOM.
- Record the paired File 00 exact head and verify `smc.authentication-account 1.1.0`.
- Verify HTTPS, PHP/WordPress requirements and database privileges.
- Back up database, WordPress files and encryption-key configuration; prove isolated restore.
- Enable Safe Mode before upgrading a populated environment.
- Capture System Check, existing `sa_*`/`sauth_*` table counts, routes and options.

## Execution

1. Install the exact deterministic package with registration/provider mutations gated.
2. Activate or run guarded File 02 repair.
3. Confirm `sauth_version` and `sauth_db_version` are `1.1.0`.
4. Confirm every canonical `sauth_*` table and index exists.
5. Compare legacy and canonical row counts; duplicate keys may reduce copied row counts only where the canonical row already exists.
6. Confirm retained compatibility queries resolve to canonical tables.
7. Confirm `/account/sessions/` resolves and `/account-sessions/` redirects without an open redirect or loop.
8. Confirm scheduled outbox/cleanup hooks.
9. Run password and Google-first registration, email verification, login/risk, recovery and session journeys before reopening providers.

## Reconciliation

- No unexplained deletion is allowed.
- Canonical tables become the active 1.1.0 source immediately after repair.
- Legacy tables remain read-only rollback evidence by policy; new runtime queries are routed to canonical storage.
- Page and option compatibility mirrors must equal canonical values.
- File 00 city, account type, Ethical Conduct consent and profile-photo completion state must match the registration receipt.

## Failure policy

- Do not drop, truncate or destructively downgrade either canonical or legacy tables.
- Enable Safe Mode and preserve public reading.
- Capture redacted System Check and exact failure point.
- Restore the previous code package if necessary.
- Resume the idempotent migration only after defect correction and retest.

## Cutover and closure

Legacy table purge is not part of ordinary migration or uninstall. It requires a separate Founder-approved change request, backup/restore proof, dependency scan, staging rehearsal and rollback plan. The GitHub repository rename is also an owner-level administration action and does not alter the stable package slug.
