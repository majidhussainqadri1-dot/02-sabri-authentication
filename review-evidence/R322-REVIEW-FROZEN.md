# R322 — Complete Review Frozen Before Correction

Review scope: registration, password sign-in failure continuity, email verification, password recovery, reset completion truth, asynchronous recovery/resend job lifecycle, privacy-job binding, and relevant regression coverage.

No R322 correction was started before this ledger was frozen.

## Frozen defect ledger

1. **Password-login failure redirect double encoding.** `SA_Registration::login_failure()` pre-encodes `redirect_to` with `rawurlencode()` before `add_query_arg()`, so canonical redirect continuity can be double encoded.
2. **Password-reset retry key/login double encoding.** The invalid-password retry path pre-encodes `key` and `login` before `add_query_arg()`. A user correcting a password validation error can therefore receive a changed reset credential in the next request.
3. **False all-sessions-revoked completion claim.** After a successful password hash change, `SAUTH_Session_Manager::revoke_user_sessions()` is called but its boolean postcondition is ignored. File 02 can emit `PasswordResetCompleted.v1` with `all_sessions_revoked=true` and show a full-success message even when session-revocation evidence is unconfirmed.
4. **Queued password-recovery work is discarded on temporary dependency/circuit unavailability.** `run_recovery_job()` deletes its opaque transient and privacy-job index before Safe Mode/provider/email-circuit checks; a temporary outage consumes the one scheduled job with no bounded retry.
5. **Queued email-verification resend work has the same consume-before-readiness defect.** `run_resend_job()` deletes its transient/index before readiness checks and ignores a temporary `issue()` failure, silently losing a legitimate queued resend.

## Correction requirements

- Preserve raw logical values and let `add_query_arg()` perform query encoding once.
- Never claim `all_sessions_revoked=true` unless the session-manager postcondition returns true; an unconfirmed revocation must produce explicit non-success evidence and no false completion event.
- Keep privacy epoch protections while adding bounded retry for transient provider/circuit unavailability; retry must not survive privacy erasure, exceed the original job TTL, or become unbounded.
- Add permanent R322 regression coverage for every item above.
