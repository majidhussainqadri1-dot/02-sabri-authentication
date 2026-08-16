# R336 — Retest Defects Frozen Before Correction

Exact tested head: `5a7cd35de7a8807ce1e35ea1f0ac0cb52023b536`.

Permanent Release Integrity run: `31850688197`.

No correction to the defects below was started before this retest ledger was frozen.

## Retest result

The R336 correction successfully advanced release evidence and cross-file blocker closure, but the permanent workflow exposed two additional stale-test expectations:

1. **Architecture guard retains a retired login-risk implementation marker.** The release-constitution job now correctly derives runtime/DB identity from `RELEASE-LOCK.json`, then fails because the `SAUTH_Login_Risk` marker list still requires `SA_Authentication_Assurance::verify_and_record`. Exact current source no longer uses that older File 00-style step-up path: elevated password risk requires a separate File 02 passkey sign-in, provider health is projected through the non-mutating `SAUTH_Provider_Health::available_for_ui`, and passkey readiness is checked through `SAUTH_Passkeys::authentication_ready()`.
2. **R319 release-truth regression retains the R321–R330 status-line expectation.** PHP 8.3 cumulative regression reaches R319 after all earlier suites pass and then fails only because `STATUS.md` truthfully states the current R331–R336 corrective line rather than the historical R321–R330 line. The same test still describes the cross-file taxonomy blocker as open even though exact integration run `31850253635` has proven repository-level closure.

## Correction requirements

- Update the architecture guard's login-risk markers to the current intended design: new-device/network/recent-failure scoring, non-mutating membership provider-health projection, File 02 passkey readiness, explicit passkey-step-up-required failure path, and completion resolution. Do not reintroduce the retired assurance call merely to satisfy a stale test.
- Update R319 current-truth assertions to R331–R336 and exact cross-file integration evidence while preserving its original redirect/migration/contract hardening invariants.
- Rerun the full PHP 7.4/8.3 cumulative suite and release constitution before any package claim.

Runtime remains `1.2.6`; DB remains `1.2.1`; passkey schema remains `1.0.1`; staging/live/operational remain unclaimed.
