# Review-only branch — do not merge or deploy

This branch records the 12 August 2026 twenty-round review, source-lineage block, and the checksum identity of the locally corrected 1.2.3 incident-hardening patch. It intentionally does **not** replace `main` runtime source because the governing File 02 corpus records a later approved 1.3.8 modern-authentication lineage whose exact source bytes are not currently recovered. Merging older 1.2.x runtime changes before reconciling that later source would risk a product-scope downgrade and violate the evidence-first/no-patch-stacking rule.

Unblock only after exact 1.3.8 (or later superseding) source recovery, manifest/checksum verification, hardening reconciliation, exact-head QA, staging acceptance and separate live authorization.
