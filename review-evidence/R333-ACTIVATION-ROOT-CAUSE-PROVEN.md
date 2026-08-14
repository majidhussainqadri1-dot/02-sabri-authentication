# R333 — Exact File 00 Activation-Readiness Root Cause Proven Before Correction

Diagnostic integration run: `31849029651`, exact File 02 head `3ce06c07d73ad352f1b587aeb0d16ab79d1459ad`, exact File 00 head `1d7f215193d778b0977c8e50d738c42e1e5f66c2`.

The non-secret readiness projection immediately after fresh File 00 activation proved:

- `SMC_VERSION = 1.2.44` — correct.
- `SMC_DB_VERSION = 1.4.5` — correct target.
- stored `smc_db_version = ""` — **the failing predicate**.
- `smc_page_url` and `smc_user_status` — available.
- `SMC_Security`, `SMC_Completion`, `SMC_CF01_Contract` — loaded.
- Safe Mode — false.
- `SMC_CF01_CONTRACT_VERSION = 1.1.0` — correct.
- `membership_assertion` — callable.
- File 00 activation state — `queued`, with message: `Activation accepted; protected bootstrap is deferred to the next administrator request.`

This matches exact File 00 source behavior: its activation hook intentionally performs no schema migration and queues protected bootstrap; the migration executes later on `admin_init` only for a user with `manage_options`. The WP-CLI integration fixture activated File 00 and immediately attempted File 02 activation without executing that documented administrator bootstrap request.

## Root cause classification

**Integration-fixture sequencing defect.** No File 00 or File 02 runtime/product defect is established by this failure. File 02 correctly failed closed because File 00's stored DB version had not yet reached its declared target.

## Permitted correction

The integration gate must emulate the real supported lifecycle: after File 00 activation, establish the administrator identity, execute the deferred `admin_init` bootstrap, prove stored `smc_db_version === SMC_DB_VERSION` and bootstrap state `ready`, and only then attempt File 02 activation. No plugin runtime/version change is required for this correction.
