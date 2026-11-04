# File 02 v12 — Run 6: primary-plan recheck and recovery/collision source audit (2026-11-05)

## Primary authority and traceability
Original **Sabri-Website-Master-Plan-v12.docx** was located and its File 02 clauses independently read from the Library in this run (p.84). F02-X24-011 requires configurable 1h–7d recovery cooling-off (default 24h), canonical-owner-mediated pending change. F02-X24-012 requires a one-time fingerprint-bound collision-resolution case and forbids silent merges. No v13 scope assumed.

## Exact source examined
Repository `majidhussainqadri1-dot/02-sabri-authentication`, PR #21 candidate source at immutable commit `8b28a9e62cd400fd4eab546b72deb280d4ceea2e`. `includes/class-sauth-security-orchestrator.php` (previously identified blob `2d01c5ad20ce52b46ed6de13a6bad79d5ec06c0a`) was fetched and examined. `tests/recovery-once-claim-gate.php` blob `4805c0178dbd6965876b681eda84867f33d9cd7a` independently fetched. Prior Run 5 note blob `ca29f70ae24ef6844f7696eadbf81cdb8a877741` independently fetched.

- `resolve_collision_case`: reads `pending`, validates fingerprint/token/payload, calls `sauth_resolve_collision_case_v1` **before** `$wpdb->update(... status='applied' WHERE status='pending')`.
- `apply_recovery_change`: reads `pending`, checks cooldown/token, invokes `sauth_apply_recovery_change_v1` **before** conditional pending→applied update.
- Therefore both hooks can run twice under concurrent callers before one later update fails; the post-hook update is not an at-most-once side-effect guarantee. This is a **source-ordering finding**, not evidence that the owner handler actually mutates state or that a live exploit occurred.
- The existing gate is a static ordering heuristic, not a concurrent behavioral test. Its expected failure was inferred from source; it was **not executed** in this run.
- Canonical File 00 repository `majidhussainqadri1-dot/00-sabri-membership-core` was discovered. Scoped code search for the exact hook names returned no indexed matches; this is **not proof of absence**. The owner consumer's actual idempotency, rollback and hook implementation remain unverified.

## Verification and limits
Local `git ls-remote` to github.com failed due DNS resolution; PHP CLI 8.4.24 is installed. GitHub connector read succeeded. No cloned checkout, no real PHP test, no WP/MariaDB two-connection test, no new CI execution, no schema migration, no production source patch and no deploy. Current main and exact current PR heads were not independently frozen via a ref endpoint; immutable candidate above is a **historical inspected commit**, not a claim about current HEAD.

## Requirement-by-requirement disposition
- **A — F02-X24-011:** pre-claim canonical owner invocation; at-most-once guarantee not established. Pending fix.
- **A — F02-X24-012:** same ordering for collision resolution. Pending fix.
- **B:** one static gate exists, runtime re-entry, failure/retry and idempotency untested.
- **C:** File 00 owner contract, WP/MariaDB integration, deployed artifact/DB parity, staging/live and Founder acceptance unverified.

## Work accounting
2 requirement IDs revisited; 2 previously known ordering gaps reconfirmed; 0 **new** confirmed defects; 0 fixes; 0 tests executed; CI pass/fail not determined for this commit. This note is an evidence-only update on the existing draft review branch, not production acceptance.

## Next exact action
Retrieve File 00 canonical consumer from an immutable source tree (do not infer absent from code search), determine owner side effects and idempotency. Implement a true two-connection WP/MariaDB fail-first test with duplicate callback and crash/retry assertions. Only then design a conditional claim plus bounded recovery/lease and idempotent owner handshake, verify migration compatibility, patch the minimal source, run CI and preserve draft status.

## Five-field truth
Repository HEAD: inspected immutable PR #21 candidate `8b28a9e62cd400fd4eab546b72deb280d4ceea2e`; current main/PR HEAD unverified.
Deployed Version: unknown.
DB Version: live unknown; previous candidate declaration 1.4.0, not independently rechecked here.
Migration State: live unknown; none executed.
Live Verification Status: not performed. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
