# File 02 Migration Guide — 1.0.0

## Migration model

All File 02 database changes are additive and idempotent. `SA_Activator::repair()` creates or reconciles File 02-owned tables with WordPress `dbDelta`, restores managed pages, migrates the Google secret to the current encrypted format, ensures the anti-enumeration dummy password hash and records runtime/schema version 1.0.0.

File 02 never migrates or mutates File 00 roles, membership applications, guardian truth, identity evidence or verification decisions.

## Supported paths

1. Fresh installation.
2. Upgrade from every repository-supported File 02 release to 1.0.0.
3. Deactivate/reactivate without data loss.
4. Re-run of the same migration after interruption.

## Pre-migration gates

- Record exact source version, branch/head and package SHA-256.
- Verify File 00 required contracts and compatible version.
- Verify HTTPS, PHP/WordPress requirements and database privileges.
- Back up database, WordPress files and encryption-key configuration; prove isolated restore.
- Place registration/provider linking in Safe Mode when upgrading a populated environment.
- Capture System Check and current table/page inventory.

## Execution

1. Install the exact deterministic package with gates closed.
2. Activate or run the guarded File 02 repair.
3. Confirm `sa_version` and `sa_db_version` are `1.0.0`.
4. Confirm all seven owned tables and all managed routes exist.
5. Confirm scheduled outbox/cleanup hooks.
6. Run representative login/recovery/session journeys before reopening registration/providers.

## Reconciliation

Compare pre/post counts for File 02-owned rows, route mappings and provider settings. No unexplained deletion is allowed. Expired challenge cleanup is lifecycle behavior, not migration loss.

## Failure policy

- Do not delete or destructively downgrade tables.
- Enable Safe Mode and preserve public reading.
- Capture redacted logs/System Check and exact failure point.
- Restore the previous code package if necessary.
- Resume the idempotent migration only after defect correction and retest.

## Cutover

No dual-write is used. File 00 is the canonical account provider throughout. File 02 routes are registered with File 01/File 20 manifests and old authentication links are redirected only after staging acceptance.
