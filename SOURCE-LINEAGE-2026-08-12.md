# File 02 — Source Lineage and Release Block

## Governing finding

The current GitHub `main` reviewed for this incident remains `0f011b1876e217b7ee46f92903e5315538c1025e` / File 02 `1.2.1`. Four review-only incident-hardening cycles now reach a local corrected `1.2.6 / DB 1.2.0` source line without claiming later product-scope completion.

The latest approved File 02 planning/review corpus records a later modern-authentication implementation lineage: runtime candidate `1.3.8`, DB `1.3.0`, passkey schema `1.1.0`, and F02-X24-001..024 architecture. Exact 1.3.8 source bytes are not present in the current review workspace or accessible GitHub refs. A branch name is not source evidence.

During R77 the exact current File 00 repository `main` was refreshed at `c4ab298b3ba2b870d507d32b36b1b4afd2771621`. Its CF-01 membership-assurance contract supports `clinical_identity_link` for membership eligibility/subject binding and does not support the File 02-local semantic action names that had been sent by several File 02 sign-in/professional paths. Those callers were corrected locally in the R61-R80 review source; runtime source remains intentionally absent from this GitHub review branch until later-lineage reconciliation.

## Consequence

The local `1.2.6` line is **incident-hardening review evidence only**. It must not be represented as the latest approved File 02 implementation, must not be used to downgrade a later deployment, and must not be packaged/deployed as a production-complete replacement for the approved X24 source line.

The exact 1.3.8 source must be recovered (or a later superseding exact source proven), checksum/manifest parity established, and all R1-R80 hardening corrections replayed/reconciled onto that exact source before a staging candidate can be authorized.

## Historical and current review checksum references

- Installable 1.3.8 ZIP SHA-256: `9e93cc0282ec407a7efc5bb37559a6d6e87f9e20c9e9453e33b338e49f494e2f`
- Full 1.3.8 source-review bundle SHA-256: `f284fbe8296f7d3085c5a10f4710c0962b0aff8ba1997667779605ff9d0bff57`
- R61–R80 local source patch SHA-256: `5482740a58943a5fc390efaf0a5f599289736e660c2b1d9d5e10e8b90338880f`
- R61–R80 corrected content tree SHA-256: `0a70d11fb940efe64f69026434e682b6055ceef6b92f6e859c17aa250a885d54`
- R61–R80 corrected PHP/JS tree SHA-256: `abf86bd7a2e682c85fe0e1a2f1cd5b83a9f7d3449859db9a1a1557c7d7905c46`
- Review-only DO-NOT-INSTALL bundle SHA-256: `21b61e09a5545694bbb90a2d9a78c86702f4ae7419d4ca58c79d9d6de2f93618`

## Release law

No merge/deployment decision may infer current live correctness from this repository review. Staging acceptance, deployed package parity, DB/migration state and live verification remain separate gates. The reported live outage remains OPEN.
