# R326 — Complete Review Frozen Before Correction

Review scope: canonical routes and legacy redirects, unmanaged password-authentication boundary, private/no-cache/noindex headers, redirect sanitation, admin settings authorization/CSRF, Google provider-secret mutation, settings rollback, and admin evidence messages/status.

No R326 correction was started before this ledger was frozen.

## Frozen defect ledger

1. **Google authentication settings can still mutate while File 02 Safe Mode is active.** `SA_Plugin::run()` may register the settings admin-post hooks when schema reconciliation is already current even if Safe Mode was entered independently; `save_settings()` blocks only the `enable` case, so Client ID/secret/clear/disable mutations remain possible during containment.
2. **Failed atomic settings write claims rollback without proving rollback.** On a partial settings-store failure, the snapshot is rewritten but the restored values are not read back. The admin message says the complete unit “was rolled back” even when rollback persistence itself may have failed.
3. **Admin provider-status card can say `Ready` during Safe Mode.** The card uses `SA_Google_OAuth::configured()` alone, so a configured provider can be presented as ready while File 02 deliberately prohibits its use.

## Correction requirements

- Fail closed on every protected provider-settings mutation while Safe Mode is active.
- Verify rollback postconditions; if rollback cannot be proven, enter centralized Safe Mode and report a distinct rollback-integrity failure instead of claiming success.
- Make the admin readiness projection include Safe Mode.
- Add permanent R326 regression coverage.
