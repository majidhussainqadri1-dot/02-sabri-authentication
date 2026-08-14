# R323 — Complete Review Frozen Before Correction

Review scope: Google login, Google linking/unlinking, Google-first registration, OIDC state/nonce/PKCE, provider HTTP guard/circuit ownership, link containment, state-cookie continuity, continuation redirect encoding, and related permanent regressions.

No R323 correction was started before this ledger was frozen.

## Frozen defect ledger

1. **Google-first continuation token is pre-encoded before `add_query_arg()`.** The callback passes `rawurlencode( $registration_token )` into `add_query_arg()`, creating the same double-encoding class already removed from other auth/recovery redirects.
2. **Google linkage containment bypasses the centralized Safe Mode revocation epoch.** `contain_linkage_failure()` writes `SAFE_MODE_OPTION` directly if linkage-disable markers cannot be proven, so a linkage incident can enter Safe Mode without the R321 challenge-revocation epoch.
3. **Linkage-containment session revocation is asserted but not proven.** The containment path calls `WP_Session_Tokens::destroy_all()` directly only when the class exists and does not verify File 02's durable session projection; its own comment says all sessions are revoked either way, which is stronger than the evidence.
4. **OIDC state-cookie persistence is not checked before redirecting to Google.** Both Google login/link start and Google-first registration call `setcookie()` but ignore its boolean result. If the secure state cookie cannot be established, the external flow is started even though callback state binding is guaranteed to fail.

## Correction requirements

- Pass the logical continuation token directly to `add_query_arg()`.
- Route linkage containment through `SAUTH_Operations::enter_safe_mode()` and `SAUTH_Session_Manager::revoke_user_sessions()` with evidence-aware fallback.
- Make state-cookie creation return a boolean and fail before external redirect when the state cookie cannot be established.
- Add permanent R323 regression coverage for all four defects.
