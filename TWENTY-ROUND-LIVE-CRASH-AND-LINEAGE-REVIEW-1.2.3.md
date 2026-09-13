# File 02 — Twenty-Round Live-Crash and Source-Lineage Review — 1.2.3

**Date:** 12 August 2026 (PKT)  
**Repository baseline:** `0f011b1876e217b7ee46f92903e5315538c1025e` / File 02 `1.2.1`  
**Earlier corrective baseline:** local ten-round incident-hardening candidate `1.2.2`  
**Current reviewed incident-hardening candidate:** `1.2.3` / DB `1.2.0`  
**Live status:** unresolved; exact deployed package/log/DB/migration parity remains unverified.  
**Release status:** deployment blocked by source-lineage divergence; no 1.2.3 installable production package is authorized.

## Governing review method

Each round began only after the previous round's defects were corrected and the cumulative regression contract passed. The review covered bootstrap/load order, schema and migration truth, activation/upgrade paths, Safe Mode, password/Google/email/passkey/risk flows, concurrency/replay, privacy, event outbox, session projection, documentation/release identity, plan-to-code traceability and source lineage.

## Twenty sequential rounds

| Round | Result | Defect(s) found | Correction before next round |
|---|---|---|---|
| R1 | Defect | Canonical-route bootstrap could stamp current runtime/DB markers before verified repair, allowing a stale schema to look current. | Removed premature marker writes; markers advance only after verified `SA_Activator::repair()` postconditions. |
| R2 | Defect | Passkey finish-registration, finish-authentication and revoke could complete a challenge issued before Global Safe Mode. | Added Safe Mode fail-closed barriers and invalidation of pre-existing passkey challenges. |
| R3 | Defect | Passkey schema/page repair could run `dbDelta` and page mutation during ordinary public/AJAX traffic. | Automatic passkey repair restricted to controlled admin/cron/WP-CLI contexts; public/AJAX returns a deferred-repair error. |
| R4 | Defect | Core `maybe_upgrade()` could run DDL on public/AJAX traffic and lacked cross-request serialization/Throwable containment. | Added controlled-context gate, atomic upgrade lock with TTL, and fail-closed Throwable handling. |
| R5 | Defect | Core schema success checked table presence but not required columns/unique replay/idempotency indexes. | Added complete required-column and unique-index postcondition verification before version markers advance. |
| R6 | Defect | Passkey schema success checked table/route presence but not required columns/unique credential indexes. | Added full passkey column and unique-index verification before accepting the passkey schema marker. |
| R7 | Defect | Plugin startup was not idempotent against legacy duplicate startup calls; unexpected runtime exception could take down WordPress. | Added one-start guard, Throwable containment, Global Safe Mode entry and redacted bootstrap failure reference. |
| R8 | Defect | Activation repair/passkey install lacked a safe exception boundary. | Added activation Throwable containment and redacted activation-failure evidence; core activation failure remains fail-closed. |
| R9 | Defect | Google account UI read legacy `_sa_google_*` metadata directly instead of canonical-first bounded fallback. | Routed UI reads through `SA_Google_OAuth::meta_value()` canonical-first compatibility accessor. |
| R10 | Defect | Passkey-only installation failure forced unrelated password/Google Global Safe Mode, contradicting degraded-provider policy; readiness could proceed without verified passkey health. | Added passkey-local degraded state; core auth stays available if healthy; passkey readiness now requires verified schema/route/crypto/origin health. |
| R11 | Defect | Password login did not honor Global Safe Mode after core repair failure. | Password sign-in now fails closed while Global Safe Mode is active. |
| R12 | Defect | Successful WordPress password reset could later fatal while writing File 02 session/outbox/audit evidence, after the password was already changed. | WordPress sessions are revoked independently; secondary File 02 evidence is Throwable-contained and recorded via redacted reference. |
| R13 | Defect | A login-risk challenge issued before Safe Mode could still complete and establish a session. | Risk challenge completion now invalidates the challenge and fails closed in Safe Mode. |
| R14 | Defect | Email verification issue/verify paths could mutate authentication state during Global Safe Mode. | Both issuance and completion now return fail-closed Safe Mode errors. |
| R15 | Defect | Event outbox persisted raw downstream consumer exception text in `last_error`. | Dead-letter/retry diagnostics now persist only a correlation digest; regression test warning noise was also removed. |
| R16 | Defect | Google second-factor challenge completion lacked an atomic completion claim, allowing concurrent valid requests to race into link/login mutation. | Added nonce-bound atomic per-challenge option lock with stale recovery and guarded release. |
| R17 | Defect | Google unlink bypassed Global Safe Mode; allowlisted Google link/unlink domain events were never emitted; unlink success copy overclaimed File 00 two-factor availability. | Added Safe Mode block, privacy-minimized `GoogleAccountLinked.v1` / `GoogleAccountUnlinked.v1` emissions, and policy-neutral success copy. |
| R18 | Defect | Session-registry database write failures were silently ignored, causing stale security projection while UI/System Check could imply health. | Added redacted registry-degraded evidence, System Check warning, and kept WordPress core session invalidation security-authoritative even when projection writes fail. |
| R19 | Defect / release blocker | Current GitHub 1.2.x source is older than the latest approved reviewed File 02 1.3.8 / DB 1.3.0 / passkey-schema 1.1.0 X24 lineage; exact 1.3.8 source bytes are not currently recovered, and stale branch names are not source evidence. | Added `SOURCE-LINEAGE-LOCK.json` and source-lineage record; explicitly blocks packaging/deployment/downgrade and requires exact later-source recovery + reconciliation before staging candidacy. |
| R20 | Defect | Historical ten-round regression assertions contradicted the newer provider-local passkey-degradation policy; source-review copy retained a stale 1.2.1 generated package manifest; release/SBOM identity was stale after the new corrections. | Updated regression semantics, removed stale package-manifest evidence, advanced incident-hardening identity to 1.2.3, aligned readme/changelog/SBOM/lineage records, then reran clean closure QA. |

## Defect-round accounting

**Defects were found in every round: R1–R20 (20/20).**  
No next round began until the prior round's corrective changes passed the cumulative review regression.

## Closure QA after R20 corrections

- Every PHP file in the reviewed workspace: syntax lint PASS.
- `assets/js/authentication.js`: `node --check` PASS.
- Evolved ten-round live-crash regression: PASS.
- New twenty-round cumulative regression: PASS through R20 with no warnings.
- JSON validation: `SBOM.spdx.json` and `SOURCE-LINEAGE-LOCK.json` PASS.
- Stale generated `PACKAGE-MANIFEST.json`: removed from current source-review evidence.
- Raw exception persistence check: corrected for outbox/bootstrap/activation/recovery/session-registry diagnostics covered by the regression contract.

## Critical truth boundary

This review proves corrections in the **reviewed 1.2.3 incident-hardening source line only**. It does not prove that 1.2.3 is the complete latest File 02 implementation because the governing File 02 corpus records a later approved 1.3.8 modern-authentication lineage whose exact source bytes are not currently available for reconciliation.

Therefore:

1. Do not merge/deploy 1.2.3 as a production-complete replacement.
2. Recover exact 1.3.8 (or a later superseding exact source) and verify its hashes/manifest.
3. Reconcile/reapply the 20-round hardening changes onto that exact later source.
4. Run exact-head/package/real WordPress+MariaDB/upgrade/rollback/plan-traceability QA.
5. Then perform Hostinger staging acceptance and only later a separately authorized live deployment/re-test.

The reported website outage after File 02 1.2.1 remains **open** until exact deployed artifact, runtime fatal log, DB/schema/migration state and live re-test are available.
