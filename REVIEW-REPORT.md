# File 02 — Four-Round Three-Plan Review, Correction and Retest Report

**Module:** File 02 — Authentication and Accounts  
**Candidate:** `1.1.0 / schema 1.1.0`  
**Reviewed immutable source/package head:** `27839667c957fe8d20c9df663b2fa6782a42a82f`  
**Paired File 00 head:** `2548d98782b14992a9cde697243b3ce169e5924c`  
**Governing sources:** Definitive Master Plan v3.0; `SSH-F02-PLAN-2026-v1.0`; Consolidated All-Chats Directives v2.1.  
**Review rule:** review → correction → fresh adversarial review → correction → retest until zero known source-level blocker/critical defects.

## Round 1 — Plan constitution, ownership, registration and routes

### Scope

- File 02/File 00/File 03/File 20 ownership boundaries;
- repository/package/prefix/route constitution;
- all parent-plan registration fields and age/guardian rules;
- password and provider-first journeys;
- status and traceability truth.

### Defects found and corrected

1. **Missing city field** — added to the form, validation, File 02/File 00 DTO and private completion state.
2. **Missing declared account type** — added as a declaration only; it never grants role/capability/verification. Professional and institutional declarations require an adult account.
3. **Missing separate Ethical Conduct consent** — added as a distinct versioned File 00 consent, separate from Terms and Privacy.
4. **Missing profile-photograph completion gate** — added required acknowledgement and File 03/File 00 completion hooks without making File 02 the media owner.
5. **Password-only first registration** — added complete Google-first registration while retaining every non-provider identity, location, guardian, account-type and consent field.
6. **Non-canonical session route** — added `/account/sessions/`; the legacy page redirects permanently and safely.
7. **Incomplete public naming constitution** — promoted public constants/classes/contracts/options/tables to `SAUTH_`/`sauth_*`, retaining bounded compatibility aliases only.
8. **Stale repository status evidence** — replaced obsolete 0.2.0/0.4.0 root status, lock, manifest, README and traceability records; preserved historical evidence under `history/0.2.0/`.

**Round 1 result:** all identified plan-constitution defects corrected; source moved to fresh adversarial security review.

## Round 2 — Authentication security, replay, collision and storage migration

### Scope

- Google-first state/nonce/PKCE and claims validation;
- callback expiry, provider degradation and replay behavior;
- exact-email and provider-subject collision controls;
- session, outbox, challenge and rate-limit storage;
- activation/upgrade fail-closed behavior.

### Defects found and corrected

1. **Google callback could continue without a fresh circuit/Safe Mode recheck** — callback now rechecks provider health, File 00 contract and Safe Mode before mutation.
2. **State/context age was implicit in transient expiry only** — explicit created-at bounds and future-clock checks were added.
3. **Google registration link race** — added an atomic, expiring provider-subject lock and duplicate-owner scan.
4. **Google account email mismatch risk** — final link requires the canonical WordPress/File 00 email to exactly match the verified Google email.
5. **Sensitive temporary variables lived longer than necessary** — authorization code, client secret, ID token and claims are cleared after use.
6. **Legacy table names remained active runtime identifiers** — seven canonical `sauth_*` tables were created; legacy rows copy idempotently and retained compatibility queries route to canonical storage.
7. **Migration router could rewrite its own legacy source into a canonical self-copy** — explicit one-way migration queries are exempted and covered by a regression test.
8. **Activation could begin mutation before proving the File 00 v1.1 account contract** — a pre-mutation activation gate now validates both File 00 and `smc.authentication-account 1.1.0`.
9. **Current outbox still used a legacy table literal** — event persistence/dispatch now directly uses canonical storage.
10. **A correction introduced a PHP parenthesis error in the security helper** — immediately found by lint, corrected and retested before acceptance.

**Round 2 result:** all found authentication/storage defects corrected; no known replay, external-redirect, duplicate-provider-owner or legacy-active-storage path remained in repository tests.

## Round 3 — File 00 canonical truth, privacy and idempotency

### Scope

- File 00 v1.1 provider contract;
- city/account-type/ethics/photo completion ownership;
- Google-first password/bootstrap handoff;
- privacy export/erasure and encryption;
- repeated registration and quarantine behavior.

### Defects found and corrected

1. **File 00 initially stored city as plaintext user metadata** — city is now purpose- and subject-context-encrypted and decrypted only for completion/export.
2. **`update_user_meta()` false-on-unchanged could be treated as failure** — storage now writes and compares exact persisted values, making idempotent replay safe.
3. **Google picture candidate privacy needed explicit protection** — it is encrypted in File 00 private state and is not a public provider truth.
4. **Incomplete extra-field initialization needed containment** — sessions are revoked, a private quarantine marker is recorded and the contract returns `unknown`; successful repair removes quarantine.
5. **Completion omitted the new parent-plan fields** — city, account type, Ethical Conduct and profile photograph are included in File 00-owned `missing_steps` and canonical next-route resolution.
6. **File 00 runtime version was accidentally advanced while extending only a contract** — runtime identity was restored to `1.2.11`; the new account contract alone is versioned `1.1.0`.
7. **Contract tests depended on exact whitespace formatting** — architecture/CI checks were made semantic and formatting-independent.

### Paired File 00 automated evidence

Exact File 00 head `2548d98782b14992a9cde697243b3ce169e5924c` completed successfully in all current workflows:

- File 00 CF-01 and Forty-Round Contract Integrity — success;
- File 00 1.2.11 Three-Plan QA — success;
- File 00 to File 02 Account Contract — PHP 7.4, 8.1 and 8.3 success.

**Round 3 result:** File 00 remains the sole identity/membership/account-class/guardian/verification authority; all found v1.1 privacy and idempotency defects corrected.

## Round 4 — Release evidence, package, documentation and final regression

### Scope

- current status/contract/data/migration/rollback/privacy/backup documents;
- SPDX SBOM truth;
- source/package parity and archive safety;
- PHP/JavaScript/CSS validation;
- all File 02 policy/security/assurance/registration/storage suites;
- retained exact-head release artifact.

### Defects found and corrected

1. **Stale 1.0 contract/data/rollback/privacy documentation** — all current registers now describe 1.1.0, canonical storage, Google-first registration and File 00 v1.1 ownership.
2. **Obsolete release evidence remained at repository root** — moved intact to a versioned historical directory; current root now represents only the 1.1.0 candidate.
3. **SBOM claimed incomplete per-file analysis and had reversed optional-dependency direction** — corrected to package-level analysis and proper Google optional-dependency direction; exact per-file hashes remain in the generated package manifest.
4. **Documentation CI secret detector matched its own detector expression** — workflow definitions were excluded from the runtime/source secret scan while the independent primary repository scan remains active.
5. **Migration guide did not name the exact entry point required by the documentation gate** — added `SAUTH_Activator::migrate_legacy_tables()` explicitly.
6. **Package artifacts were ephemeral** — exact-head CI now retains ZIP, manifest and checksum evidence for 30 days.

### Exact File 02 automated evidence

GitHub Actions run `31054945090` on exact reviewed head `27839667c957fe8d20c9df663b2fa6782a42a82f` completed successfully:

- `1.1.0 source, plans, architecture and public-safety integrity` — success;
- JavaScript syntax and CSS structural validation — success;
- PHP 7.4 lint and runtime suites — success;
- PHP 8.3 lint and runtime suites — success;
- deterministic double build, byte-identical package/manifest, clean extraction and path safety — success;
- canonical storage migration, current documentation and SPDX SBOM — success.

### Immutable artifact evidence

- Workflow artifact ID: `8951005430`
- Artifact name: `file02-authentication-and-accounts-1.1.0-27839667c957fe8d20c9df663b2fa6782a42a82f`
- Installable ZIP SHA-256: `5bda0aeb19d77285ea5343d30420bad12ed0098bc0d519a25ce40d88a301a173`
- Generated manifest SHA-256: `6e1a40d026659827c550a5e3e0eb2ca7ded746080f349a3d0def2e8bd0982223`
- Installable manifest file count: `57`
- Packaged PHP files: `31`
- Canonical top-level folder: `02-sabri-authentication`
- Tests/workflows committed inside installable ZIP: `No`

**Round 4 result:** no known unresolved blocker or critical defect remains in the repository-reviewable source, cross-repository contract, automated-test or deterministic-package scope.

## Honest completion boundary

This report establishes a reviewed **source-complete, cross-contract-complete, automated-QA-green and reproducibly packaged staging candidate**. It does not prove:

- the owner-level GitHub repository rename to `02-sabri-authentication-and-accounts`;
- Hostinger fresh install/upgrade/deactivate/reactivate/uninstall;
- real MySQL/dbDelta, SMTP or Google provider behavior;
- File 01/File 20/File 03/File 24/theme/LiteSpeed acceptance;
- real-role IDOR/CSRF/replay/race and independent penetration testing;
- Urdu RTL, English LTR, keyboard, screen-reader, zoom, browser/mobile or measured performance/load acceptance;
- database/files/keys restore and rollback rehearsal;
- Founder staging approval, live deployment or operational monitoring.

Discovery of new evidence reopens review. “Zero known source-level defects” is a release gate, not a claim of absolute infallibility.

**Completed:** Thursday, 06 August 2026 — 04:44 AM PKT (Asia/Karachi)
