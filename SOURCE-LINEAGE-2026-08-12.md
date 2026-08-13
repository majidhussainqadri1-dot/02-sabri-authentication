# File 02 — Source Lineage and Release Block

## Governing finding

The current GitHub `main` reviewed for this incident remains `0f011b1876e217b7ee46f92903e5315538c1025e` / File 02 `1.2.1`. Five review-only incident-hardening cycles now reach a local corrected `1.2.7 / DB 1.2.0` source line without claiming later product-scope completion.

The latest approved File 02 planning/review corpus records a later modern-authentication implementation lineage: runtime candidate `1.3.8`, DB `1.3.0`, passkey schema `1.1.0`, and F02-X24-001..024 architecture. Exact 1.3.8 source bytes are not present in the current review workspace or accessible GitHub refs. A branch name is not source evidence.

Current File 00 repository `main` has been reverified at `c4ab298b3ba2b870d507d32b36b1b4afd2771621`. R77 corrected older File 02 callers that sent unsupported File 02-local action names to the File 00 CF-01 membership assertion; the current supported cross-contract action used for membership prerequisite/subject binding is `clinical_identity_link`.

R89 then found a separate canonical-owner discrepancy: File 00 core membership types and the current File 00 v1.1 authentication-account provider / older File 02 declaration vocabulary are inconsistent. This review does not invent a lossy semantic mapping between `clinic_staff` / `institution_representative` and File 00 core types such as `patient`, `pharmacy`, `clinic` or `publisher`. That discrepancy remains an explicit release blocker owned by the canonical File 00 provider boundary.

## Consequence

The local `1.2.7` line is **incident-hardening review evidence only**. It must not be represented as the latest approved File 02 implementation, must not be used to downgrade a later deployment, and must not be packaged/deployed as a production-complete replacement for the approved X24 source line.

The exact 1.3.8 source must be recovered (or a later superseding exact source proven), checksum/manifest parity established, the File 00 taxonomy/provider discrepancy resolved through an approved versioned contract/migration, and all R1-R100 hardening corrections replayed/reconciled onto that exact later File 02 source before a staging candidate can be authorized.

## Historical and current review checksum references

- Installable 1.3.8 ZIP SHA-256: `9e93cc0282ec407a7efc5bb37559a6d6e87f9e20c9e9453e33b338e49f494e2f`
- Full 1.3.8 source-review bundle SHA-256: `f284fbe8296f7d3085c5a10f4710c0962b0aff8ba1997667779605ff9d0bff57`
- R81–R100 corrective runtime/tests patch SHA-256: `1eb6b35f07c031acc60e4e5a916a3c4d8f8b0d18ff8552397be4223be947e807`
- R81–R100 corrected PHP/JS tree SHA-256: `c5c25e5f0a18059d91605687fa7bc203dec1dfb08882767dcde654e89fd6dd3b`
- Fifth-cycle DO-NOT-INSTALL review bundle SHA-256: `6ceba6d137d318bef09431c48bffaa59de32881d6b1373c9fbd0d3514f002db0`

## Release law

No merge/deployment decision may infer current live correctness from this repository review. Staging acceptance, deployed package parity, DB/migration state and live verification remain separate gates. The reported live outage remains OPEN.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
