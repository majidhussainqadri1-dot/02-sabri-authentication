# File 02 — R337 Fresh Review — Frozen Defect Ledger

**Review discipline:** This R337 review was completed first against the exact pre-correction source. No R337 correction was started until this ledger was frozen.

- Repository: `majidhussainqadri1-dot/02-sabri-authentication`
- Review branch: `review/file02-r337-fresh-audit-2026-08-16`
- Frozen pre-correction HEAD: `972f5fd2cc59fe69bf465b844ac36c740533f7dd`
- Runtime identity reviewed: File 02 `1.2.6`, DB `1.2.1`, passkey schema `1.0.1`
- Governing basis: File 02 Authentication and Accounts plan + consolidated platform master plan + current File 00 provider/runtime contract evidence.
- Scope: authentication dependency gates, registration taxonomy/age validation, passkey state persistence, WordPress session projection, password/Google/passkey successful-session postconditions, core login-surface compatibility, and release-evidence truth.

## Frozen findings

### R337-D01 — High — File 00 dependency gate accepts an incompatible provider runtime

`SA_Membership_Adapter::MIN_VERSION` is `1.2.43`. File 02 exposes the canonical File 00 account taxonomy (`member`, `patient`, `student`, `doctor`, `teacher`, `researcher`, `pharmacy`, `clinic`, `publisher`), but File 00 `1.2.43` still validates the v1.1 registration provider against the older restricted vocabulary. File 00 `1.2.44` is the first reviewed candidate in the current evidence that derives provider account types from canonical `smc_account_types()` and was the runtime used by the paired integration proof. File 02 therefore must fail closed below `1.2.44` rather than advertise compatibility with `1.2.43`.

### R337-D02 — Medium — WordPress minimum is internally contradictory with the mandatory File 00 runtime

File 02 plugin/readme still declare WordPress `6.0+`, while the corrected mandatory File 00 `1.2.44` runtime declares WordPress `6.4+`. A dependency cannot truthfully support a platform version on which its mandatory provider does not. File 02 metadata and activation diagnostics must align to WordPress `6.4+`.

### R337-D03 — High — Professional-adult registration prevalidation uses stale account types

File 02 registration correctly offers canonical account types, but its age gate still treats only `doctor`, `teacher`, `clinic_staff`, and `institution_representative` as professional. The latter two are obsolete/unreachable in the canonical taxonomy, while `researcher`, `pharmacy`, `clinic`, and `publisher` are omitted. This allows a locally accepted under-18 professional payload to reach File 00 instead of failing at the File 02 validation boundary. The File 02 professional list must match current canonical professional types: `doctor`, `teacher`, `researcher`, `pharmacy`, `clinic`, `publisher`.

### R337-D04 — High — Passkey authentication can report success without persisting credential security state

After a valid WebAuthn assertion, `finish_authentication()` updates `sign_count`, `backup_state`, and usage timestamps but ignores the database mutation result and performs no post-read verification. If the write fails or the credential is concurrently deactivated, File 02 can still create assurance/session state and emit authentication success. This is a false-success path and can weaken future signature-counter clone detection. Credential-state persistence must be a verified precondition before any successful passkey session is established.

### R337-D05 — High — Password, Google, and passkey paths can report success after session-registry persistence failed

`SAUTH_Session_Manager::register_cookie()` already destroys the newly issued WordPress token when the File 02 session projection cannot be persisted, but it does not communicate that failure back to the synchronous caller. Password, Google, and passkey login paths therefore continue with success events/redirects even though the token was destroyed. The session manager needs a per-attempt synchronous postcondition result, and all three authentication success paths must fail closed if the projection was not verified.

### R337-D06 — Medium — File 02 intercepts WordPress administrative email confirmation

`redirect_core_login_surface()` preserves `logout`, `postpass`, and `confirmaction`, but not WordPress core `confirm_admin_email`. Consequently a legitimate administrative-email confirmation request can be redirected into the File 02 login surface even though it is not an account-authentication ceremony owned by File 02. Preserve the core administrative confirmation action while continuing to redirect actual account login/recovery/register surfaces.

### R337-D07 — Medium — Release evidence carries stale branch/review identity

`RELEASE-LOCK.json` still names an older `fix/file02-passkey-index-reconciliation-1.2.6` candidate branch and stops its review line at R336 while current corrective work is on the R337 review branch. Release evidence must not imply a stale source location. Update evidence after corrections without advancing Packaged, Automated-QA, Staging, Live, or Operational status beyond what the exact post-correction HEAD actually proves.

## Review conclusion before correction

R337 found **7 verified defects: 4 High and 3 Medium**. No live/deployed conclusion is made by this source review.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
