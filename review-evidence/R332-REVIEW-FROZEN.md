# R332 — Fresh Post-R331 Review Frozen Before Correction

Reviewed exact pre-correction head: `33e6487a130278f01aa3d545a8966adf47c56860`.

R331 corrected the File 02 account-choice surface to the File 00 canonical taxonomy and added a permanent regression proving that the public choices contain `member`, `patient`, `student`, `doctor`, `teacher`, `researcher`, `pharmacy`, `clinic`, and `publisher`, with no provider-only `clinic_staff` / `institution_representative` aliases in that choice block.

No R332 correction was started before this review ledger was frozen.

## Review scope

- Runtime/release identity after the material R331 account-contract compatibility correction.
- README, WordPress readme, status, architecture, contracts, migration, staging guide, traceability, release manifest/lock, SBOM, changelog and root review truth.
- Permanent release/documentation/review regressions and exact-head workflow version assertions.
- Cross-file File 00 integration pin and canonical-account-taxonomy ownership boundary.
- Temporary write-capable correction machinery.

## Frozen defect ledger

1. **Runtime/release identity is stale after R331.** Source still identifies itself as `1.2.3` even though the externally visible account-type vocabulary changed. A distinct runtime identity `1.2.4` is required; DB remains `1.2.1`, passkey schema remains `1.0.1`, and passkey assurance contract remains `1.0.0` because R331 changes no physical schema or passkey protocol.
2. **Release-facing evidence and permanent regressions still assert 1.2.3.** Current status/manifest/lock/SBOM/docs/tests/workflows must be synchronized to 1.2.4 while historical R329/R330 evidence and the historical 1.2.3 changelog record remain immutable provenance.
3. **Temporary write-capable taxonomy applicator remains present.** `.github/workflows/tmp-account-taxonomy-parity-apply.yml` must not survive the final source candidate.
4. **Cross-file integration is not yet closed.** The current real-integration workflow still pins File 00 1.2.43. File 00 source-level taxonomy-provider correction exists on its dedicated branch but requires a distinct 1.2.44 release identity and exact candidate SHA before File 02 can truthfully remove the cross-file release blocker.

## Correction requirements

- Advance File 02 source/release identity to `1.2.4` without changing DB/passkey schema identities.
- Synchronize current release evidence/tests/workflows while retaining historical evidence.
- Keep staging/live/operational claims false.
- Produce and exact-pin the File 00 1.2.44 taxonomy-compatible candidate, then update File 02 real integration and run a separate fresh post-integration review.
- Remove all temporary correction machinery before the final exact-head release candidate is declared source-green.
