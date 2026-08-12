# Review-only branch — do not merge or deploy

This branch records **forty total sequential review rounds**: the original R1-R20 cycle plus a fresh R21-R40 cycle completed on 12 August 2026. The latest locally corrected incident-hardening line is `1.2.4 / DB 1.2.0`, but its runtime source is intentionally **not** applied to this GitHub branch or `main`.

The governing File 02 corpus records a later approved `1.3.8 / DB 1.3.0 / passkey schema 1.1.0` modern-authentication/X24 lineage whose exact source bytes are not currently recovered. Merging/deploying an older 1.2.x runtime line before reconciling that later source could silently downgrade approved scope and would violate the evidence-first/no-patch-stacking rule.

Unblock only after exact 1.3.8 (or later superseding) source recovery, manifest/checksum verification, R1-R40 hardening reconciliation, exact-head source/package/WordPress+MariaDB/upgrade/rollback/traceability QA, Hostinger staging acceptance and separate live authorization/re-test.
