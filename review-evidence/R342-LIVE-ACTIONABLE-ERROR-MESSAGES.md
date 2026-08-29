# R342 — Live actionable authentication error messages

Date: 2026-08-30
Baseline repository HEAD: `9658c9e9f8421414362edc9ec126390b096a5dc2`
Live deployed File 02 before remediation: `1.3.4`, previously verified byte-identical to that HEAD.
Live File 00: `1.2.44`.

## Live evidence frozen before coding

Two controlled live registration attempts proved that File 00 returned distinct canonical failure reasons while File 02 rendered the same generic public notice:

1. Existing mobile number -> File 02 outbox `AccountAuthenticationFailed.v1` recorded `method=registration`, `reason=phone_collision`.
2. Different mobile number but existing identity document -> File 02 outbox recorded `method=registration`, `reason=identity_collision`.

In both cases the public registration page displayed the same generic message: `Registration could not be completed. The details may already belong to an account, or the membership service may require review.`

The backend collision decisions were correct. The live defect was the File 02 presentation boundary: meaningful canonical reason codes were discarded before the signed redirect notice was created.

## Remediation

R342 adds `SAUTH_User_Error_Messages`, which:

- observes only the already-persisted `sauth_event_recorded` event in the same request;
- does not query, duplicate or mutate File 00 membership/identity storage;
- rewrites only the two known generic File 02 notices (registration and password sign-in);
- regenerates the complete signed notice tuple through `SA_Security::notice_query_args()`;
- maps phone, identity and email collision reasons to field-specific actionable guidance;
- maps validation, provider, security-initialization, rate-limit and membership-sign-in reasons to clear next actions;
- retains credential ambiguity for invalid credentials and retains the existing password-recovery anti-enumeration response.

## Security invariants

- No File 00 private table access is added.
- No account owner, account ID or linked identity is disclosed.
- Collision copy says that a value cannot be used for a new account and offers sign-in/recovery or correction; it does not identify another account.
- Password recovery continues to use the uniform `If the account exists...` response.
- Unknown/internal failures remain safe and actionable without leaking raw reason codes.

## Permanent regression

`tests/r342-actionable-auth-error-messages-regression.php` freezes bootstrap wiring, collision mappings, login mappings, signed-notice regeneration, File 00 boundary isolation and password-recovery anti-enumeration behavior.

## Release state

This evidence records repository remediation only. Live status must remain **unverified/not resolved** until the reviewed build is packaged, deployed and the two live collision cases are re-tested on the exact deployed artifact.
