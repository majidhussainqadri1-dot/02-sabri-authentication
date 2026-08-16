# File 02 — Source Manifest Recovery Correction — 2026-08-16

## Why this correction exists

The earlier `SOURCE-RECOVERY-AUDIT-2026-08-16.md` accurately recorded what was visible in its first search pass, but a subsequent File Library search surfaced two exact-named File 02 `1.3.8` manifest files that were not exposed in that first pass. Evidence must be corrected when stronger later evidence appears; the earlier audit is retained as chronology rather than silently rewritten.

## Newly recovered evidence

The File Library now exposes:

1. `02-sabri-authentication-1.3.8-MANIFEST.json`
2. `NINTH-EIGHTY-ROUND-SOURCE-MANIFEST.json`

Their content is structurally consistent with the v2.9 Ninth Eighty-Round review record. The installable manifest enumerates the `1.3.8` installable paths with byte counts and per-file SHA-256 values. The source-review manifest does the same for the source-review tree, including the three workflows, evidence documents, runtime source, tests and build tooling.

The v2.9 review record separately freezes the historical whole-artifact identities:

- `02-sabri-authentication-1.3.8-SOURCE-CANDIDATE.zip` — `9e93cc0282ec407a7efc5bb37559a6d6e87f9e20c9e9453e33b338e49f494e2f`
- `02-sabri-authentication-1.3.8-MANIFEST.json` — `5300a3483116fedb7cd7248253ecf98f3d0a0b7ab6621b8a2d70c7b682c72bb8`
- `02-sabri-authentication-1.3.8-NINTH-EIGHTY-ROUND-SOURCE-REVIEW-BUNDLE.zip` — `f284fbe8296f7d3085c5a10f4710c0962b0aff8ba1997667779605ff9d0bff57`
- `NINTH-EIGHTY-ROUND-SOURCE-MANIFEST.json` — `5a15260da739353d911568c4ebe2a75d0989a4aff515c3dbd9e17e4c20702108`

## What the manifest recovery proves

The recovered manifests materially improve provenance. They provide a concrete expected inventory for the approved `1.3.8` source/product line, including exact byte sizes and per-file SHA-256 identities. Among other things, the installable manifest proves that the expected tree contains the X24 owner implementation files such as:

- `includes/class-sauth-modern-auth.php`
- `includes/class-sauth-security-orchestrator.php`
- `includes/class-sauth-shared-signals.php`
- `includes/class-sauth-password-safety.php`
- `includes/class-sauth-dpop.php`
- `includes/class-sauth-fido-trust.php`
- the large `includes/class-sauth-passkeys.php` implementation
- the `1.3.8` plugin bootstrap, route, privacy, operations, provider and session surfaces.

This means future candidate bytes can now be compared file-by-file rather than merely by branch/version naming.

## What is still NOT recovered

The exact bytes of these two historical ZIPs are still not exposed in the evidence available to this correction:

- `02-sabri-authentication-1.3.8-SOURCE-CANDIDATE.zip`
- `02-sabri-authentication-1.3.8-NINTH-EIGHTY-ROUND-SOURCE-REVIEW-BUNDLE.zip`

Nor is a complete set of exact `1.3.8` source-file bytes available as a recoverable tree in the current GitHub refs. Therefore:

- exact `1.3.8` source recovery remains **incomplete**;
- source-review ZIP SHA parity cannot yet be freshly recomputed;
- installable ZIP SHA parity cannot yet be freshly recomputed;
- the historical whole-manifest SHA values have not been freshly recomputed from raw File Library bytes through the presentation/search layer;
- R337/R338 hardening reconciliation onto guessed/reconstructed source remains prohibited;
- packaging, staging and deployment remain blocked.

## Additional archaeology performed

A targeted search was run for a `1.3.7 -> 1.3.8` patch/diff, the exact ninth source-review ZIP, the exact installable ZIP and representative `1.3.8` source-file hashes. That search returned the recovered manifests and governing v2.9 evidence, but did not expose a ninth-cycle patch or either ZIP/source tree as exact recoverable bytes.

Current GitHub branch/release/tag findings remain unchanged: no current `1.3.8` branch, release or tag proves those bytes; named `1.3.0`/`1.3.4` branches resolve to the old `c895ec17...` tree.

## Corrected recovery status

- Historical `1.3.8` identity: **VERIFIED FROM GOVERNING REVIEW EVIDENCE**
- Exact installable manifest file: **RECOVERED IN FILE LIBRARY PRESENTATION**
- Exact source-review manifest file: **RECOVERED IN FILE LIBRARY PRESENTATION**
- Path / expected byte-size / per-file SHA inventory: **RECOVERED**
- Exact installable ZIP bytes: **NOT RECOVERED**
- Exact source-review ZIP bytes: **NOT RECOVERED**
- Complete exact `1.3.8` source bytes/tree: **NOT RECOVERED**
- R337/R338 reconciliation authorized: **NO**
- Packaging authorized: **NO**
- Staging authorized: **NO**
- Deployment authorized: **NO**

`SOURCE-LINEAGE-LOCK.json` has been upgraded to v3 to encode this partial-recovery state machine-readably.

## Next recovery path

The next search should use the recovered manifests as a hash oracle: compare the `1.3.7` and `1.3.8` manifests, identify every changed path, and search File Library/GitHub evidence for those exact changed bytes or a ninth-cycle delta patch. If a complete set can be independently recovered, every file must match the `1.3.8` manifest before the tree can be called recovered. Otherwise the correct path remains to obtain the original ninth source-review/installable ZIP.

## Live boundary

Repository/source-recovery evidence is not live deployment evidence. Deployed File 02 version/package, live DB/schema, migration state and deployment parity remain unverified.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
