# File 02 — Source Lineage and Release Block

## Governing finding

The current GitHub `main` reviewed for this incident is `0f011b1876e217b7ee46f92903e5315538c1025e` / File 02 `1.2.1`. Three review-only incident-hardening cycles now reach a local corrected `1.2.5 / DB 1.2.0` source line without claiming later product-scope completion.

The latest approved File 02 planning/review corpus records a later modern-authentication implementation lineage: runtime candidate `1.3.8`, DB `1.3.0`, passkey schema `1.1.0`, and F02-X24-001..024 architecture. Exact 1.3.8 source bytes are not present in the current review workspace or accessible GitHub refs. A branch name is not source evidence.

## Consequence

The local `1.2.5` line is **incident-hardening review evidence only**. It must not be represented as the latest approved File 02 implementation, must not be used to downgrade a later deployment, and must not be packaged/deployed as a production-complete replacement for the approved X24 source line.

The exact 1.3.8 source must be recovered (or a later superseding exact source proven), checksum/manifest parity established, and all R1–R60 hardening corrections replayed/reconciled onto that exact source before a staging candidate can be authorized.

## Historical checksum references

- Installable 1.3.8 ZIP SHA-256: `9e93cc0282ec407a7efc5bb37559a6d6e87f9e20c9e9453e33b338e49f494e2f`
- Full 1.3.8 source-review bundle SHA-256: `f284fbe8296f7d3085c5a10f4710c0962b0aff8ba1997667779605ff9d0bff57`
- R41–R60 local patch SHA-256: `fb71757617bef96861e4f310121de487202ea51e678c17ef002e555783dfcc33`
- Corrected code/docs/tests tree SHA-256: `e3fe3e51418b8c071e86d1c74fb2a2502d828eef5a44007d12b5dbf831487670`

## Release law

No merge/deployment decision may infer current live correctness from this repository review. Staging acceptance, deployed package parity, DB/migration state and live verification remain separate gates.
