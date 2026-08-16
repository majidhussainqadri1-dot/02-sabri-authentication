# R320 — Final Adversarial Review — Frozen Defect Ledger

Review baseline: `fe66788cb6c12e966bb198b361347cb74ec490b0`.

Method boundary: the complete R320 review was finished before any R320 product correction. This file freezes the full R320 ledger. Product correction starts only after this freeze.

## Complete review scope

- final branch tree and R311-start-to-R319-final compare;
- plugin/version/schema/bootstrap/upgrade path;
- File 02 core tables, passkey schema, migration materiality and security-critical indexes;
- password/Google/passkey/risk/session/assurance/privacy/operations boundaries already corrected in R311–R319;
- release/package/integration CI and cumulative regression coverage;
- release lock, manifests, README/readme, changelog, SBOM, architecture, migration, staging checklist, traceability and historical root report truth;
- temporary correction workflows/payload artifacts;
- fresh File 00 dependency boundary and cross-file blocker.

## Frozen defects

1. **Release identity did not advance after material R311–R319 hardening.** Runtime still declared `1.2.1 / DB 1.2.0 / passkey schema 1.0.0`, so a deployment could not identify the materially different reviewed build by version, and normal `maybe_upgrade()` could not use a new File 02/DB marker as an explicit upgrade trigger.
2. **Material storage postconditions proved columns but not security-critical indexes/uniqueness.** Partial dbDelta/index failure could therefore satisfy `storage_ready()` despite missing unique/race/dispatch indexes. Passkey readiness had the same gap.
3. **Primary release CI remained frozen to the old 1.2.1/R301–R310 truth and omitted R311–R319 permanent regressions.** `baseline-integrity.yml` and `canonical-storage-and-docs.yml` would become stale/failing release gates for the corrected source.
4. **Real File 00 integration CI remained pinned to File 02 1.2.1/DB 1.2.0 and did not exercise legacy passkey-column upgrade reconciliation.** The migration introduced in R315 therefore lacked a real MariaDB upgrade-path gate.
5. **Release-facing documentation/SBOM was stale.** `readme.txt`, `CHANGELOG.md`, `ARCHITECTURE.md`, `STAGING-ACCEPTANCE.md`, `RELEASE-LOCK.json`, `RELEASE-MANIFEST.md`, `PLAN-TRACEABILITY.md`, `README.md`, `STATUS.md`, `MIGRATION.md`, `DATA-DICTIONARY.md` and `SBOM.spdx.json` did not consistently identify the final hardening line/version/schema. Root `REVIEW-REPORT.md` could also be mistaken for current evidence although it is a historical 1.1.0 report.
6. **Temporary write-capable corrective automation remained in the repository candidate.** R310 correction workflows, the R311–R320 correction workflow, and `.github/forty-round-payload/*` were review machinery, not permanent release infrastructure. Leaving them retained stale write-capable automation/review payload clutter in the candidate repository.
7. **The permanent review/release gates did not explicitly forbid reintroduction of temporary correction workflows/payloads or assert the new version/index-release truth.** Final closure therefore lacked a durable regression for the R320 release-cleanliness findings.
8. **The external File 00 account-taxonomy/provider-vocabulary harmonization blocker remains owner-side.** File 02 must continue to document it and must not introduce a lossy local remap. This is a release blocker, not a File 02 coding defect to patch locally.

R320 correction will address items 1–7 in File 02 and preserve item 8 as an explicit external gate. This evidence is repository/source review only, not staging/live evidence.
