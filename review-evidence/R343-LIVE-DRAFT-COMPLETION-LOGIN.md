# R343 — Live draft/incomplete account sign-in deadlock

Date: 2026-08-31

## Live reality frozen before coding

- Live File 02 version: `1.3.4`.
- Live File 02 deployed ZIP supplied from Hostinger contained 59 plugin files.
- The deployed ZIP matched the R342 reviewed package/current `main` tree byte-for-byte: 59/59 files, 0 missing, 0 extra, 0 modified.
- Repository baseline: `4a657c1b304bc45239c3110c0f2306221f19ceb4`.
- Live File 00: `1.2.44`; Live File 02 DB version: `1.3.0`.
- Live test user ID `13` had `wp_smc_applications.status = draft`.
- Password credentials were accepted, but sign-in was denied as `membership_not_eligible`.

## Exact root cause

The exact deployed sign-in predicate required `membership.active === true` before honoring File 00's separately returned completion state. File 00 intentionally creates new accounts in `draft`, while its authentication account contract returns canonical `missing_steps` and `next_route` so the subject can finish verification. This formed the circular lock: `draft -> inactive -> cannot sign in -> cannot complete -> cannot become active`.

## Remediation

R343 centralizes completion-aware sign-in admission in `SA_Membership_Adapter::sign_in_allowed()`. Unknown and suspended states stay fail-closed. Fully eligible `allow` stays allowed. A canonical `deny` is admitted only when its reason is exactly `membership_prerequisite_denied` and File 00 simultaneously returns completion `allow` with non-empty `missing_steps` and `next_route`. File 00's `membership.active` truth is not rewritten.

Password, passkey, passkey-runtime and Google sign-in now use the same admission rule.

## Release state

Repository remediation only. Live resolution requires reviewed CI, package deployment, deployment-parity confirmation and live re-test.
