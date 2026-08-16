# R336 — Post-Integration Release-Constitution Review Frozen Before Correction

Reviewed exact File 02 head: `f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9`.

No R336 correction was started before this review ledger was frozen.

## Exact evidence reviewed

- Exact File 00/File 02 WordPress 7.0 / MariaDB 11.4 integration run `31850253635` completed **SUCCESS** against File 02 `f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9` and exact File 00 `1d7f215193d778b0977c8e50d738c42e1e5f66c2` / runtime `1.2.44`.
- That run passed immutable input proof, File 00 queued activation, supported deferred administrator bootstrap to DB `1.4.5`, File 02 fresh activation at runtime `1.2.6` / DB `1.2.1` / passkey schema `1.0.1`, two-sided canonical account-taxonomy parity, the legacy passkey-column/index upgrade on real MariaDB, the legacy logical-identity collision migration, and final paired version/schema boundaries.
- Permanent File 02 Release Integrity run `31850253586` on the same exact File 02 head passed PHP 7.4 lint+cumulative regressions and PHP 8.3 lint+cumulative regressions.
- The release-constitution job in `31850253586` passed exact-head/baseline ancestry and the release identity/source-policy step, then failed only when `tests/architecture-check.py` required the historical `Version: 1.2.1`, `SAUTH_VERSION 1.2.1`, and DB `1.2.0` markers.

## Frozen defect ledger

1. **The permanent architecture guard is stale release infrastructure.** `tests/architecture-check.py` is still written as a File 02 `1.2.1` guard and hard-codes runtime `1.2.1` / DB `1.2.0`. It therefore rejects the truthful current runtime `1.2.6` / DB `1.2.1` after all product regressions and the exact real integration have passed.
2. **The architecture guard duplicates mutable release identity instead of consuming the repository release lock.** A future legitimate runtime increment can make it stale again. The guard should derive current runtime and DB identity from `RELEASE-LOCK.json`, while continuing to assert stable contract/architecture invariants explicitly.
3. **The File 00 taxonomy/provider cross-file blocker is now stale.** It was correctly retained through R335 pending exact integration. Run `31850253635` has now supplied the required exact paired WordPress/MariaDB proof, so repository/source release evidence may clear that specific blocker. This does not establish staging or live deployment.
4. **There is no permanent post-integration R336 closure regression.** The repository needs a regression proving the exact File 00 pin/runtime, current File 02 runtime/schema, full integration-workflow coverage, empty cross-file blocker ledger after successful proof, release-lock-driven architecture identity, and continued staging/live/operational non-claims.
5. **Deterministic package proof is still pending.** In run `31850253586`, the package job was skipped only because the stale architecture guard failed the prerequisite constitution job. Package completion may be claimed only after the corrected exact head passes the full permanent Release Integrity workflow.

## Correction requirements

- Make `tests/architecture-check.py` derive current runtime and DB identity from `RELEASE-LOCK.json`; remove the historical 1.2.1/1.2.0 duplication while preserving all stable architecture/security boundaries.
- Clear only the now-proven File 00 taxonomy/provider cross-file blocker and record exact integration run `31850253635`, File 00 SHA `1d7f215193d778b0977c8e50d738c42e1e5f66c2`, and File 02 paired SHA `f740ca65fc33031b98d7d75e5f27b7ccbeeefbf9` in current release evidence.
- Add a permanent R336 closure regression.
- Keep runtime `1.2.6`, DB `1.2.1`, passkey schema `1.0.1`, and passkey assurance contract `1.0.0`; R336 changes release/test evidence, not product runtime semantics.
- Keep Staging-Accepted, Live-Deployed and Operational false.
- Rerun exact-head release constitution, PHP 7.4/8.3 cumulative regressions, deterministic package and clean-extract parity before declaring repository/source release closure.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
