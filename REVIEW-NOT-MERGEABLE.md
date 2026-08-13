# Review-only branch — do not merge or deploy

This branch records the 12–13 August 2026 sequential review evidence through **R1–R100**, source-lineage block, and checksum identities for locally corrected incident-hardening candidates. It intentionally does **not** replace `main` runtime source because the governing File 02 corpus records a later approved `1.3.8 / DB 1.3.0 / passkey schema 1.1.0` modern-authentication lineage whose exact source bytes are not currently recovered.

The latest local review-only incident-hardening identity is `1.2.7 / DB 1.2.0`; it is not an installable production-complete release. R89 also records an unresolved canonical File 00 account-type/provider taxonomy discrepancy. Merging the older 1.2.x runtime before recovering the later File 02 source and resolving that File 00 contract boundary could silently downgrade approved scope or preserve incompatible identity semantics.

Any GitHub Actions checks that run on this review branch validate the **review-record branch content actually present on GitHub**, not the locally corrected 1.2.7 runtime source, because that runtime source is deliberately absent from the PR. They must not be cited as CI validation of local 1.2.7 or live correctness.

Unblock only after exact 1.3.8 (or later superseding) source recovery, checksum/manifest verification, canonical File 00 taxonomy/provider reconciliation, R1–R100 hardening reconciliation, exact-head QA, staging acceptance and separate live authorization/re-test.

The reported live outage remains OPEN. **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
