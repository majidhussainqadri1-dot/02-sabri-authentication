# File 02 — Authentication and Accounts

**Canonical repository name:** `02-sabri-authentication-and-accounts`  
**Current historical GitHub transport repository:** `02-sabri-authentication`  
**Package folder / WordPress slug:** `02-sabri-authentication` / `sabri-authentication`  
**Source candidate:** `1.1.0`  
**Governing plans:** Definitive Master Plan v3.0, `SSH-F02-PLAN-2026-v1.0`, Consolidated All-Chats Directives v2.1.

## Canonical ownership

File 02 owns email/password and Google authentication surfaces and adapters, account-entry orchestration, linking/unlinking, recovery, session presentation, login-risk challenge and account-completion routing.

File 00 remains the sole owner of platform identity, membership eligibility, declared account class, age/guardian truth, roles/capabilities, verification, suspension, institutional authority and MFA policy. Authentication never grants object authorization.

## Version 1.1.0 completion scope

The candidate implements:

- email/password and secure Google-first registration;
- mandatory name, email, mobile/phone, country, city, address, date of birth, sex, declared account type, National ID/Passport reference, guardian context, profile-photograph completion acknowledgement, Terms, Privacy and separate Ethical Conduct consent;
- male 15/female 12 baseline and guardian/adult-professional rules;
- signed one-time email verification;
- password and Google authentication, link and unlink;
- recovery/reset and all-session revocation;
- opaque session/device registry and canonical `/account/sessions/` route;
- suspicious-login risk challenge and File 00-owned step-up;
- loop-safe completion routing;
- privacy-minimized versioned event outbox;
- provider circuit breakers, Safe Mode, System Check and guarded repair;
- File 01 and File 20 manifests;
- privacy export/erasure/anonymization, migration, rollback, backup/restore and incident documentation;
- deterministic source package, manifest, SHA-256 checksums and SPDX SBOM;
- canonical `SAUTH_` public naming with bounded compatibility aliases for legacy `SA_` integrations.

## Required File 00 contract

The paired File 00 candidate must expose:

```text
smc.authentication-account 1.1.0
```

through `SMC_Authentication_Contract_V11`, preserving File 00 as the only membership and identity authority.

## Truthful status

| Gate | Status |
|---|---|
| Specified | Complete |
| Source coding | Complete candidate; current exact-head CI must pass |
| Cross-repository File 00 contract | Implemented candidate; acceptance/merge pending |
| Packaged | Built and retained by current CI when green |
| Automated QA | Determined by current immutable head |
| Hostinger staging | Pending |
| Real SMTP/Google/browser/RTL/WCAG/load | Pending |
| Backup/restore and rollback rehearsal | Pending |
| Founder acceptance | Pending |
| Live/Operational | Not claimed |

The owner-level GitHub repository rename and all real-environment acceptance gates remain external to source coding and are not silently represented as completed.
