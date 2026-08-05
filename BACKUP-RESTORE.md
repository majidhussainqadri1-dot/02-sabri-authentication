# File 02 Backup and Restore Runbook — 1.1.0

## Required backup set

A recoverable File 02 deployment requires one timestamped, access-controlled set containing:

1. the complete WordPress database, including canonical `sauth_*` tables, preserved legacy `sa_*` tables, File 00 membership/consent/private state and WordPress users/options/usermeta;
2. WordPress files, exact File 02 and File 00 packages, manifests, SHA-256 checksums and SPDX SBOMs;
3. configuration needed to recreate the environment, including PHP/WordPress versions, active plugins/theme, permalink/cache settings and cron behavior;
4. encryption-key and Google-secret recovery material in a separate secrets system, never in the ordinary archive;
5. restoration owner, approver, storage location, retention date and destruction date.

A database dump without the matching encryption-key configuration is not a proven backup.

## Backup verification

- Generate and record SHA-256 checksums for archives and database dumps.
- Verify the archive can be listed and extracted without traversal or corruption.
- Confirm the exact File 02/File 00 package identities and contract versions.
- Record row counts for all `sauth_*` and preserved `sa_*` tables.
- Confirm provider secrets, passwords, tokens and raw private evidence are absent from ordinary logs and reports.
- Test an isolated restore before staging acceptance and after material schema/key changes.

## Restore procedure

1. Isolate the destination and block public/provider traffic.
2. Restore files and database from the same recovery point.
3. Restore the encryption-key configuration through the secrets procedure.
4. Install the exact compatible File 00 and File 02 packages.
5. Keep Safe Mode enabled.
6. Run guarded File 02 repair and File 00 diagnostics; additive/idempotent migrations may reconcile missing indexes/pages/options but may not erase evidence.
7. Compare table counts, canonical/legacy migration markers, versions, routes and cron schedules.
8. Test password registration, Google-first registration with provider disabled first, email verification, password login/risk, recovery and session revocation.
9. Re-enable SMTP/Google only after negative and outage tests pass.
10. Record approver, timestamps, residual risks and observation window.

## Acceptance criteria

- File 00 identity, account class, guardian, consent, verification and suspension truth is intact.
- Canonical `sauth_*` tables contain the expected active state; preserved `sa_*` tables remain unchanged rollback evidence.
- City and Google picture candidates decrypt only with the intended File 00 purpose/context.
- No revoked session becomes valid and no challenge/token can be replayed.
- `/account/sessions/` works and the compatibility route does not loop.
- Event/outbox retry and cleanup behavior resumes safely.
- Public reading remains available during provider failure.
- System Check is pass or approved warning, never unresolved fail.

## Rehearsal evidence

For each rehearsal record backup ID, source/destination, exact heads/packages/checksums, restore duration, data/route/provider/security results, defects and corrections, reviewer, Founder decision and next rehearsal date. A written runbook without a successful isolated restore is not restore evidence.
