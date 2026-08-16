# R333 — Fresh Exact File 00 1.2.44 Integration Review Frozen Before Correction

Reviewed exact File 02 pre-correction head: `950f4bd3f63e08304e3afafa501196919223ab20`.

Fresh dependency evidence reviewed before any R333 correction:

- File 00 corrective candidate head: `1d7f215193d778b0977c8e50d738c42e1e5f66c2`.
- File 00 runtime: `1.2.44`; DB schema: `1.4.5`; public membership contract: `1.2.3`; `smc.authentication-account`: `1.1.0`.
- File 00 provider now validates account type through `array_keys( smc_account_types() )`, so the provider derives from File 00's canonical taxonomy rather than a duplicated alias vocabulary.
- File 00's complete repository verification before its corrected commit passed, including deterministic package verification and exact File 02 1.2.4 compatibility source/runtime QA. This remains repository evidence, not deployment evidence.

No R333 correction was started before this review ledger was frozen.

## Review scope

- File 02 real WordPress/MariaDB integration workflow and exact File 00 pin.
- Runtime/version assertions on both sides.
- File 00 canonical taxonomy vs File 02 public account choices.
- Fresh install, passkey-schema upgrade and legacy logical-identity migration rehearsal.
- Release lock/status/traceability truth and cross-file blocker lifecycle.
- Staging/live/operational completion boundaries.

## Frozen defect ledger

1. **The File 02 real-integration workflow still pins obsolete File 00 main `c4ab298b3ba2b870d507d32b36b1b4afd2771621` / runtime `1.2.43`.** It therefore cannot prove compatibility with the corrected File 00 1.2.44 provider.
2. **The integration workflow does not explicitly prove canonical account-taxonomy parity.** It proves provider availability and existing migration/runtime boundaries, but it does not assert that File 00 accepts every canonical account type exposed by File 02 or that the obsolete provider-only aliases are absent from the File 02 account-choice surface.
3. **The File 02 release lock correctly retains the cross-file blocker but is now stale relative to the newly available exact File 00 1.2.44 candidate.** The blocker may be removed only after the corrected workflow passes against exact File 00 `1d7f215193d778b0977c8e50d738c42e1e5f66c2`; it must not be removed merely because the File 00 source correction exists.
4. **Integration evidence naming remains stale at `file00-1.2.43`.** Current release-facing evidence should identify the 1.2.44 dependency after proof, while preserving historical 1.2.43 provenance where explicitly historical.

## Correction requirements

- Exact-pin File 00 `1d7f215193d778b0977c8e50d738c42e1e5f66c2` and runtime `1.2.44` in the real WordPress/MariaDB integration gate.
- Add two-sided canonical taxonomy assertions: all nine canonical values accepted/exposed; no `clinic_staff` / `institution_representative` public account-choice aliases.
- Re-run fresh install, passkey upgrade and legacy logical-identity collision migration on real MariaDB.
- Only after exact integration success, clear the File 00 taxonomy/provider cross-file blocker and record the exact paired SHAs in release evidence.
- Keep File 02 runtime `1.2.4`, DB `1.2.1`, passkey schema `1.0.1`; this correction is integration/release evidence unless review reveals a runtime defect.
- Keep Staging-Accepted, Live-Deployed and Operational false.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
