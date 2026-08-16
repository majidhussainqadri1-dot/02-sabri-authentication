# R329 — Complete Review Frozen Before Correction

Review scope: runtime/release identity, WordPress readme, README/STATUS/CHANGELOG/traceability, release lock/manifest/SBOM, architecture/contracts/migration/staging/incident/rollback truth, deterministic builder, permanent release/documentation/review CI, current File 00 integration pin, historical release regressions and current repository-main boundary.

No R329 product/release correction was started before this ledger was frozen.

## Frozen defect ledger

1. **Release identity is stale after material R321–R328 runtime hardening.** The current source still identifies itself as `1.2.2` even though bootstrap/Safe-Mode semantics, asynchronous recovery/resend lifecycle, Google OIDC containment, passkey containment, session/risk evidence, admin mutation behavior, privacy erasure continuation and legacy migration semantics all changed. The next source identity must be `1.2.3`; DB remains `1.2.1` and passkey schema remains `1.0.1` because no new physical schema shape was introduced in R321–R328.
2. **Release-facing documentation and evidence still name the R311–R320 branch/line.** `RELEASE-LOCK.json`, README, STATUS, release manifest, traceability, changelog, WordPress readme, SBOM, architecture and the historical root report's current-truth notice do not describe R321–R329 source truth.
3. **Permanent release/documentation/File00 workflows still use the setup-php commit form that already failed GitHub Actions resolution during R320.** The review gate was corrected to the resolvable `shivammathur/setup-php@2.37.2` tag, but baseline release, canonical-docs and real-integration workflows still use `@f3e473...`; release CI can therefore fail before testing product code.
4. **Permanent CI does not include the final R33x regression line.** Baseline release, canonical-docs and review-integrity loops currently stop at `r32*`; R329/R330 regressions could be omitted from final exact-head proof.
5. **Real MariaDB/File00 integration does not exercise the new R328 legacy logical-identity migration rule.** It rehearses the old passkey-column upgrade only. It does not prove that a legacy evidence row whose numeric auto-ID collides with an unrelated canonical row is still migrated by stable logical identity, nor that migration success is withheld when a logical identity is missing.
6. **Architecture and historical/current release regressions remain semantically stale.** Architecture still describes legacy evidence copying only as `INSERT IGNORE` without the R328 logical-identity reconciliation invariant; R319/R320 and three-plan release guards hard-code the preceding release/review line and would either reject the truthful 1.2.3 correction or continue blessing stale evidence.
7. **Release source-policy cleanup detection is incomplete.** The release integrity gate rejects `*correction*.yml` and the old payload but does not reject the current temporary `round-ledger-apply.yml` / `tools/apply-round-ledger.py` machinery. These must not survive the final R330 candidate.

## Fresh dependency truth captured during review

- File 00 `main`: `c4ab298b3ba2b870d507d32b36b1b4afd2771621` (freshly re-read during R329).
- File 02 repository `main` remains a separate repository reality and is not inferred from this review branch.

## Correction requirements

- Advance source candidate to runtime `1.2.3`, DB `1.2.1`, passkey schema `1.0.1`; synchronize all release-facing evidence and tests.
- Record R321–R329 hardening without changing staging/live claims.
- Use the known-resolvable setup-php release tag in permanent workflows and include `r33*` regressions.
- Add real MariaDB legacy logical-identity collision/reconciliation rehearsal to the File00 integration gate.
- Update release-source policy so the final candidate cannot contain temporary round-correction machinery; actual temporary machinery removal is finalized in R330 after the tenth review is frozen.
- Add permanent R329 release-truth regression coverage.
