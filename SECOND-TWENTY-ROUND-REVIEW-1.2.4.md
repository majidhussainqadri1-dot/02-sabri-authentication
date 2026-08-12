# File 02 — Fresh Second Twenty-Round Review (R21-R40) — Review-Only 1.2.4

Date: 12 August 2026 PKT

Baseline: prior reviewed incident-hardening source `1.2.3 / DB 1.2.0`. Current local corrected review line: `1.2.4 / DB 1.2.0`. GitHub `main` remains `0f011b1876e217b7ee46f92903e5315538c1025e / 1.2.1` because the later approved `1.3.8 / DB 1.3.0 / passkey schema 1.1.0` source lineage is not currently recovered and an older runtime line must not be merged/deployed as a downgrade.

Each fresh round started only after the previous round's correction and cumulative regression had passed.

| Round | Result | Correction |
|---|---|---|
| R21 | defect | Rate-limit persistence failure now fails closed; removed racy transient fallback and records redacted degraded evidence. |
| R22 | defect | File 00 account-contract provider Throwable containment + redacted fail-closed result. |
| R23 | defect | Membership/Core provider calls and provider URLs contained/bounded. |
| R24 | defect | Authentication assurance blocked/purged during Safe Mode. |
| R25 | defect | File 00 assurance-provider runtime exceptions fail closed. |
| R26 | defect | Professional reauthentication blocked in Safe Mode. |
| R27 | defect | Google OAuth state persistence and secure cookie are verified before external redirect. |
| R28 | defect | Google second-factor challenge persistence is verified before redirect. |
| R29 | defect | Google link/login canonical+compat projection is read-back verified before success/session claim. |
| R30 | defect | Google unlink verifies that all provider metadata is actually gone before success. |
| R31 | defect | Passkey user-handle creation uses atomic add and compare-and-swap stale replacement. |
| R32 | defect | Passkey sign counter/state uses guarded persistence before login. |
| R33 | defect | Passkey login emits standard WordPress `wp_login`. |
| R34 | defect | Passkey assurance cannot project/elevate through Safe Mode. |
| R35 | defect | Revoked-session registry read error fails closed and records degraded evidence. |
| R36 | defect | General privacy export no longer silently truncates `done=true` at 100. |
| R37 | defect | Passkey privacy export no longer silently truncates at 100. |
| R38 | defect | General privacy erasure verifies DB/meta/session mutation and reports incomplete erasure. |
| R39 | defect | Passkey erasure verifies DB deletion and WebAuthn user-handle removal. |
| R40 | compound defect | Recovered stale outbox `dispatching` rows; verified published-state storage; System Check now validates core/passkey columns+unique indexes; repair reads back canonical+compat version markers; Google-first registration now verifies OAuth state/cookie/context and atomically consumes the registration proof before File 00 account mutation; stale regression/release identity advanced to review-only 1.2.4. |

Defect rounds: **R21-R40, all 20/20**.

Closure QA: all PHP syntax PASS; JavaScript `node --check` PASS; historical ten-round regression PASS; previous R1-R20 regression PASS; fresh R21-R40 regression PASS including R40a-R40f; JSON/SBOM/lineage validation PASS; runtime `LIMIT 100` truncation scan clear; covered diagnostic raw-error persistence scan clear.

Fresh source tree SHA-256: `f365bfb34be2af060567025838f3370b3e766dbf3ce6ab16ce8733ac6e720c00`.

Patch from reviewed 1.2.3 source to review-only 1.2.4 source SHA-256: `86dd918948681a541ca4c20526ac0c98694b01b17388b8ccc97635e2142f0397`.

This PR remains review-only. Runtime source is intentionally not applied to the branch/main until exact approved 1.3.8-or-later source is recovered and R1-R40 corrections are reconciled without dropping X24 scope.

The reported website outage after File 02 1.2.1 is still OPEN. Exact deployed code/package, fatal log, actual DB/schema, migration state and live parity/re-test are not yet proven.
