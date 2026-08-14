# R325 — Complete Review Frozen Before Correction

Review scope: File 02 session projection/revocation, password-login risk, CF-01 authentication assurance, professional reauthentication, provider-health consumption at the login-risk boundary, and relevant R306/R316 hardening regressions.

No R325 correction was started before this ledger was frozen.

## Frozen defect ledger

1. **Active Sessions UI can convert a registry DB failure into false zero-session evidence.** `list_for_user()` returns an empty array for both a legitimate empty result and a failed query. `render()` then reports `Total active sessions: 0`, which is stronger than the evidence when storage is unavailable.
2. **Session risk projection can label an unknown DB state as `low`.** `current_risk_level()` converts a failed/null risk-device query to `0` via `absint()` and stores/displays a low-risk projection instead of an unknown state.
3. **Password-login risk consumes the Membership provider half-open probe without performing a provider request.** `evaluate()` calls `SAUTH_Provider_Health::allow_request( 'membership' )`; on an active auth action this can claim the single half-open lease, but the function performs no membership provider operation and never records success/failure for that lease. This can suppress the real recovery probe for the lease window.

## Correction requirements

- Distinguish session-list storage failure from a real empty list and render an explicit unavailable state without claiming zero active sessions.
- Persist/display `unknown` risk when the current-risk lookup cannot be trusted.
- Use the non-mutating provider-health projection for the risk heuristic; reserve `allow_request()` for the actual provider operation.
- Add permanent R325 regressions for all three defects.
