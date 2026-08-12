# File 02 — Source Lineage and Release Block — 12 August 2026

## Governing finding

The current GitHub `main` reviewed for this incident is `0f011b1876e217b7ee46f92903e5315538c1025e` / File 02 `1.2.1`. The current incident-hardening workspace advances that old line to `1.2.3` without claiming later product-scope completion.

The latest approved File 02 planning/review corpus records a later modern-authentication implementation lineage, including runtime candidate `1.3.8`, DB `1.3.0`, passkey schema `1.1.0`, and the F02-X24-001..024 architecture. That exact source bundle is not present in the current workspace, and the currently accessible GitHub refs do not expose those 1.3.x source bytes. A branch name is not source evidence: the visible `codex/file02-modern-auth-24-enhancements-1.3.0` ref currently resolves to the old `c895ec17...` tree whose plugin header is `1.2.0`.

## Consequence

This `1.2.3` line is **incident-hardening evidence only**. It must not be represented as the latest approved File 02 implementation, must not be used to downgrade a later deployment, and must not be packaged or deployed as a production-complete replacement for the approved X24 source line.

The exact 1.3.8 source must be recovered (or a later superseding exact source proven), checksum/manifest parity established, and these incident-hardening corrections replayed/reconciled onto that exact source before a staging candidate can be authorized.

## Historical checksum references

The prior reviewed evidence records the following 1.3.8 artifact identities. They are **historical identity references, not proof that those bytes are currently recovered**:

- Installable ZIP SHA-256: `9e93cc0282ec407a7efc5bb37559a6d6e87f9e20c9e9453e33b338e49f494e2f`
- Full source review bundle SHA-256: `f284fbe8296f7d3085c5a10f4710c0962b0aff8ba1997667779605ff9d0bff57`

## Release law

No merge/deployment decision may infer current live correctness from this repository review. Staging acceptance, deployed package parity, DB/migration state, and live verification remain separate gates.
