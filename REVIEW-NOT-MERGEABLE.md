# Review-only branch — do not merge or deploy

This branch records three fresh twenty-round review cycles through **R1–R60**, the source-lineage block, and checksum identities of the locally corrected incident-hardening work. It intentionally does **not** replace `main` runtime source because the governing File 02 corpus records a later approved `1.3.8 / DB 1.3.0 / passkey schema 1.1.0` modern-authentication lineage whose exact source bytes are not currently recovered.

The third fresh cycle R41–R60 followed strict review-first discipline: each round's full inspection completed before any correction began; all defects from that completed round were then corrected together and retested before the next round.

Current local review-only corrective identity: `1.2.5 / DB 1.2.0`; local candidate commit `e0057e42c6bb70a4a67abcdc0f23c46e76031ba1`; R41–R60 patch SHA-256 `fb71757617bef96861e4f310121de487202ea51e678c17ef002e555783dfcc33`.

Merging older 1.2.x runtime changes before recovering/reconciling the later source would risk a product-scope downgrade and violate the evidence-first/no-patch-stacking rule.

Unblock only after exact 1.3.8 (or later superseding) source recovery, manifest/checksum verification, R1–R60 hardening reconciliation, exact-head QA, staging acceptance and separate live authorization/re-test.
