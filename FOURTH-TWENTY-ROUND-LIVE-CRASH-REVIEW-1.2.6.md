# File 02 — Fourth Fresh Twenty-Round Repository Review — R61–R80 — Review-Only 1.2.6

**Platform:** Sabri Social Homeopathy Platform  
**File:** 02 — Authentication and Accounts  
**Review discipline:** full round audit → freeze defect ledger → correct all defects from that completed round → cumulative regression → next round  
**Repository baseline:** GitHub `main` `0f011b1876e217b7ee46f92903e5315538c1025e` / runtime `1.2.1`  
**Prior local corrected review baseline:** `1.2.5 / DB 1.2.0`  
**Current local corrected review identity:** `1.2.6 / DB 1.2.0`  
**Later approved lineage still blocking deployment:** runtime `1.3.8` / DB `1.3.0` / passkey schema `1.1.0`  
**Live incident status:** OPEN — no live-resolution claim.

## Governing method

Every R61–R80 round was completed as an uninterrupted review before any code correction began. Findings from that completed round were frozen as one defect ledger, all defects from that ledger were then corrected together, syntax/regression was rerun, and only after that corrected state passed was the next round opened. No finding was patched mid-review.

## Round ledger

| Round | Result | Defect and post-review correction |
|---|---|---|
| R61 | DEFECT | Rate-limit counter/storage read failure could fail open; DB-error/null reads now fail closed and create privacy-safe degradation evidence. |
| R62 | DEFECT | Login-risk storage failures could look like no risk; they now produce score-100 deny `risk_storage_unavailable`. |
| R63 | DEFECT | Google-registration `nbf` wrote an undefined time variable; it now constrains the active `$time_ok` predicate. |
| R64 | DEFECT | Email-verification challenge reads conflated DB failure with missing rows; explicit storage-read errors were added. |
| R65 | DEFECT | Google OAuth link projection could become partial; all touched keys now snapshot/readback/rollback. |
| R66 | DEFECT | Google-first registration link projection had the same partial-write exposure; exact rollback/reconciliation was added. |
| R67 | DEFECT | Passkey schema version marker was not read back; exact marker confirmation is now an install postcondition. |
| R68 | DEFECT | File 00 email-verification response was not subject-bound; provider subject mismatch now fails unknown. |
| R69 | DEFECT | Promoted assurance receipt could survive failed index persistence; orphan receipt is deleted. |
| R70 | DEFECT | Outbox degradation could clear after failed row-state persistence; row failures now aggregate and keep degradation active. |
| R71 | DEFECT | Privacy topology DB failure could look like absence and canonical-only export could omit preserved legacy rows; topology now fails closed and all existing generations are processed. |
| R72 | DEFECT | Passkey credential storage failures could look empty/not-found; helpers now return explicit errors and fail closed. |
| R73 | DEFECT | Session registry/list/risk/revocation read failures could produce false empty/low-risk/success semantics; these failures are now observable and security-authoritative revocation is required for success claims. |
| R74 | DEFECT | Maintenance degradation clearing/schedule result/topology diagnostics lacked persistence truth; readback and unreadable-topology reporting were added. |
| R75 | DEFECT | Legacy table discovery DB failure could skip migration input and still stamp success; discovery failure now blocks migration success. |
| R76 | DEFECT | Google stale lock read-then-delete could erase a newer lock owner; exact compare-and-delete semantics were added. |
| R77 | DEFECT | File 02 sent unsupported File 02-local action names across exact current File 00 CF-01; sign-in/professional subject binding now uses supported `clinical_identity_link`. |
| R78 | DEFECT | Pending/new-session passkey assurance writes were unverified; readback is required and passkey sign-in fails closed if durable session binding cannot be confirmed. |
| R79 | DEFECT | Password reset hard-coded `all_sessions_revoked => true` after ignoring revocation result; event/audit now report the confirmed WordPress revocation result. |
| R80 | DEFECT | R61–R79 lacked a dedicated cumulative regression contract and current identity/docs remained 1.2.5; R61–R80 regression plus review-only 1.2.6 identity/SBOM/lineage alignment were completed. |

## Exact File 00 interoperability evidence used in R77

Current File 00 GitHub `main` was refreshed at `c4ab298b3ba2b870d507d32b36b1b4afd2771621`. Its `SMC_CF01_Contract` 1.1.0 supports `clinical_identity_link`, `clinical_read`, `clinical_write`, `prescription_sign`, `clinical_export`, `break_glass`, `guardian_sensitive`, and `key_recovery`; unsupported actions return `unknown/unsupported_action`. The previous File 02 cross-contract action names were therefore a repository interoperability defect.

## Local R80 closure evidence

- R61–R80 source patch SHA-256: `5482740a58943a5fc390efaf0a5f599289736e660c2b1d9d5e10e8b90338880f`
- Corrected content tree SHA-256: `0a70d11fb940efe64f69026434e682b6055ceef6b92f6e859c17aa250a885d54`
- Corrected PHP/JS tree SHA-256: `abf86bd7a2e682c85fe0e1a2f1cd5b83a9f7d3449859db9a1a1557c7d7905c46`
- DO-NOT-INSTALL review bundle SHA-256: `21b61e09a5545694bbb90a2d9a78c86702f4ae7419d4ca58c79d9d6de2f93618`
- PHP syntax: 42 files PASS
- JavaScript syntax: 1 file PASS
- Prior ten-round, R1–R20, R21–R40, R41–R60, and new R61–R80 regressions: PASS
- Conflict markers: none
- Unsupported File 00 membership-action calls in the corrected local source: zero

## Release / deployment law

The approved later File 02 `1.3.8 / DB 1.3.0 / passkey schema 1.1.0` exact source bytes are still not recovered. This corrected `1.2.6` source remains local review evidence and is intentionally **not pushed as runtime source to this GitHub branch**, must not be merged/deployed as a production-complete replacement, and must first be reconciled onto the exact recovered 1.3.8-or-later source.

The reported live outage remains OPEN. **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
