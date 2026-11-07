# F02 v12 — Run 21 exact consumer audit (2026-11-05)

## Governing clauses
Original Library Sabri-Website-Master-Plan-v12.docx p.84: F02-X24-011 default 24h, configurable 1h–7d, canonical-owner mediated pending change; F02-X24-012 no silent merge, one-time fingerprint-bound collision case. This is a bounded 2-ID audit, not 48-ID acceptance.

## Immutable evidence
- File02 main HEAD 224c39bcb8c28f77504c7348dbad41226753c7e8 (GitHub branches/main).
- File02 PR22 previous HEAD 8c1f4af1e9b386f510f439f1439d82b2af7eae8b; draft/open/unmerged; branch audit/f02-run14-claim-failure-replay-20261105; base main 224c39bcb8c28f77504c7348dbad41226753c7e8.
- File02 orchestrator blob df974001dc0f9bd31bc63a95cd95ca6116d9ea59 at previous PR22 HEAD.
- File00 PR55 exact HEAD b3e9253d06a2897f092981f3a1f77e16b4b78578; draft/open/unmerged; main base 2fa7c022ee9cd1b65432e900579512f304532442.
- File00 PR55 source/sabri-membership-core/includes/class-smc-authentication-contract.php blob 64623bffab340baab734f8cf2bac74b0d4a687e0; contract version 1.0.0.
- File00 PR55 source/sabri-membership-core/includes/class-smc-authentication-contract-v11.php blob 2851d416214e6fcf29dd5724073a7dc14366a11f; contract version 1.1.0.
- File00 PR55 qa/file02-account-contract-runtime-v1243.php blob 49e60dd736713ead517d987365a9d31c75329087; mock contract version 1.0.0.
- Candidate File02 source/database/passkey schema version in PR diff: 1.4.0/1.4.0/1.1.0; live versions unknown.

## Direct source observations
File02 apply_recovery_change() decrypts and validates JSON, atomically changes pending→processing, invokes apply_filters('sauth_apply_recovery_change_v1',null,change_kind,user_id,payload), and marks applied only on strict true. Failure leaves processing; crash-after-callback-before-applied recovery remains unverified.
File00 PR55 canonical authentication providers expose register_account and other typed account operations, but the two inspected provider files do not register the named sauth_apply_recovery_change_v1 or sauth_resolve_collision_case_v1 hooks. File00 PR55 QA file uses an apply_filters mock returning its input. **This does not establish absence across the whole PR55 tree or other consumers**; the exact hook implementation/registration and its idempotent side effects remain unresolved. No production patch is justified without fail-first contract reproduction.

## Exact-SHA CI
File02 previous PR22 HEAD 8c1f4af1e9b386f510f439f1439d82b2af7eae8b: 5 completed-success GitHub workflow runs: 42025237651 (review integrity), 42025237639 (recovery claim), 42025237796 (canonical storage), 42025237838 (release integrity), 42025237631 (File00 integration). 5 pass/0 fail at workflow level; not real WP/MariaDB concurrency or live proof. No tests executed locally in this run.

## Traceability
| ID | A confirmed missing/broken | B source but insufficient proof | C external acceptance |
|---|---|---|---|
| F02-X24-011 | 0 new confirmed | Atomic claim and invalid-payload guard present; callback idempotency and crash recovery unproven | Exact File00 callback registration, real WP/MariaDB workers, staging/founder |
| F02-X24-012 | 0 new confirmed | Fingerprint-bound one-time claim present; consumer side effects unproven | Exact owner resolution implementation, MariaDB race, staging/founder |

## Changes and next exact action
This commit is evidence-only: 0 new production defects proven, 0 fixes, 0 new tests, no merge/deploy/migration. Inspect entire File00 PR55 tree and any external consumer registrations for both exact hook names; if no canonical adapter exists, reproduce with a fail-first integration fixture, then implement the minimum owner-approved adapter on the owning module with idempotency semantics. Independently execute two-worker real MariaDB and crash-after-owner tests. Do not equate workflow green with acceptance.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
