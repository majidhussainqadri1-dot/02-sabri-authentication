# File 02 Rollback Guide — 1.0.0

## Rollback triggers

Rollback or containment is required for fatal activation errors, widespread sign-in failure, account enumeration, unauthorized session acceptance, private-data exposure, provider redirect/callback failure, database migration corruption, uncontrolled retry loops or severe performance regression.

## Immediate containment

1. Enable File 02 Safe Mode.
2. Disable Google/registration high-risk mutations while preserving public reading and safe recovery where correct.
3. Record exact package/head, UTC time, System Check, affected journey and trace IDs.
4. Revoke exposed challenges/sessions where required; do not perform broad deletion.

## Code rollback

- Reinstall the previously accepted immutable package.
- Do not drop additive 1.0.0 tables or remove columns.
- Verify the previous code tolerates additive schema before switching.
- Clear opcode/cache only after code replacement.
- Re-run login, logout, recovery and public-reading smoke tests.

## Data handling

File 02 schemas are additive. A code rollback normally leaves new tables dormant. Data destructive downgrade is prohibited. New post-cutover accounts remain File 00-owned and must not be deleted merely to restore older File 02 code.

## Provider rollback

Restore the previous approved Google configuration or disable the provider. Never copy secrets into logs, support tickets or source control. Rotate a secret only under the security incident procedure.

## Verification

After rollback confirm:

- no fatal/PHP errors;
- File 00 membership assertions remain valid;
- current sessions obey revocation state;
- password reset and email verification do not falsely succeed;
- public reading remains available;
- System Check has no unresolved blocker;
- backup and current database are consistent.

A corrected package requires full CI, clean-extract parity, fresh review/fix rounds and staging regression before redeployment.
