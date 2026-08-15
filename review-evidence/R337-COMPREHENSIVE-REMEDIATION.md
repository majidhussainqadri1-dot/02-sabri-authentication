# R337 Comprehensive Remediation Evidence

## Scope

This review reconciles File 02 with the attached governing plans and the current File 00 `1.2.44` ownership boundary. It focuses on concurrent mutation, exact persistence evidence, authentication-session binding, passkey state integrity, privacy completeness and release truth.

## Corrected source invariants

- Legacy `sa_*` migration uses an explicit nested-safe storage-router suspension; SQL text cannot declare itself trusted migration traffic.
- Email-verification issuance, delivery publication, verification claim and provider rollback use compare-and-set transitions with exact readbacks.
- Google registration/login/link/unlink serialize on shared connection-owned subject/user locks. Registration proves the subject is free before File 00 mutation and validates the returned `subject_uuid` before linkage.
- Password, Google and passkey authentication require the exact WordPress session token to exist in an active, unexpired File 02 projection; success also requires device and attempt evidence to persist. Failed lazy legacy-session projection now clears that exact session and authentication state.
- User-facing revoke-other-sessions uses the same exact WordPress/File 02 postcondition helper as security-policy callers.
- Passkey backup eligibility cannot change, a stored non-zero counter cannot reset or fail to increase, and stored keys are revalidated against their claimed algorithm on every assertion. RS256 requires at least 2048 bits and exponent 65537.
- Runtime and compatibility passkey enrollment share one named-lock namespace, release that lock before every response, and remove an unproven credential row if exact persistence readback fails. Request-specific assurance receipts bind to the exact session.
- Privacy export includes device and risk state. Erasure is bounded to 50-row batches, covers canonical and preserved legacy tables, and recursively removes identity keys from outbox payloads with readback proof.
- Professional reauthentication validates and retains provider contract, version, purpose, scope and trace provenance. Retired File 00 MFA steps are ignored only when a fresh File 00 assertion explicitly proves retirement.

## Release boundary

Runtime and DB identity are `1.3.0`; passkey schema remains `1.0.1`. The DB bump makes supported installations rerun the repaired legacy copy. Exact-head PHP/WordPress/MariaDB CI, deterministic release artifact evidence, Hostinger staging, live deployment and operational verification remain separate gates. Historical run `31850253635` proves File 02 `1.2.6` only.
