# File 02 — Three-Plan Plan-to-Code Traceability

**Governing sources**

1. Definitive Master Plan v3.0
2. File 02 Complete Master Plan `SSH-F02-PLAN-2026-v1.0`
3. Consolidated All-Chats Directives v2.1

**Candidate branch:** `codex/file02-three-plan-completion-1.1.0`  
**Candidate version/schema:** `1.1.0 / 1.1.0`  
**Paired File 00 contract:** `smc.authentication-account 1.1.0`

## Ownership and constitution

| Constitution item | Resolution |
|---|---|
| Canonical repository | `02-sabri-authentication-and-accounts`; owner-level rename remains external |
| Package folder | `02-sabri-authentication` |
| WordPress slug/text domain | `sabri-authentication` |
| Public PHP prefix | `SAUTH_`; legacy `SA_` identifiers are bounded compatibility aliases |
| Canonical session route | `/account/sessions/`; legacy route redirects permanently |
| Identity/roles/guardian/verification owner | File 00 only |
| Authentication surfaces/orchestration owner | File 02 only |
| Shell/layout owner | File 20 only; File 02 supplies manifests and semantic content |
| Profile photograph owner | File 03; File 00 supplies the completion truth and route |

## Parent-plan mandatory registration fields

| Field/control | Source evidence | Owner |
|---|---|---|
| Complete name | `templates/signup.php`, `SA_Registration` | File 00 truth |
| Email | password or Google-verified email | File 00/WordPress identity |
| Mobile/phone | registration payload plus File 00 completion | File 00 verification |
| Country and city | required fields and File 00 v1.1 private state | File 00 |
| Full address | validated and encrypted by File 00 | File 00 |
| Date of birth and sex | age validation plus File 00 eligibility | File 00 |
| Declared account type | required; never grants privileges | File 00 |
| Password or Google | separate complete registration paths | File 02 |
| Profile photograph | required completion acknowledgement and File 03 hook | File 03/File 00 completion |
| National ID/Passport | explicit type and reference handoff | File 00 |
| Guardian reference | required for every under-18 account | File 00 |
| Terms | versioned consent | File 00 |
| Privacy | versioned consent | File 00 |
| Ethical Conduct Charter | separate versioned consent | File 00 |

## Functional requirements

| Requirement | Version 1.1.0 source evidence | Status |
|---|---|---|
| F02-FR-001 Account registration | `SAUTH_Registration` compatibility alias, mandatory fields, password/Google methods, idempotency and File 00 v1.1 provider | Implemented |
| F02-FR-002 Email verification | signed one-time token, canonical email, expiry, resend/attempt controls, atomic replay protection and audit | Implemented |
| F02-FR-003 Password authentication | WordPress hashing APIs, dummy verification, generic errors, rate controls and File 00 rechecks | Implemented |
| F02-FR-004 Google OAuth | state, nonce, PKCE, issuer/audience/azp/time/email validation for login and registration | Implemented |
| F02-FR-005 Linking/unlinking | exact-email, current session, step-up, duplicate lock, unlink and session revocation | Implemented |
| F02-FR-006 Password recovery | non-enumerating initiation, one-time key, strength check and all-session revocation | Implemented |
| F02-FR-007 Sessions | HMAC-only bindings, opaque IDs, current marker, generalized presentation and scoped revoke operations | Implemented |
| F02-FR-008 Login risk | new device/network/failure/provider state and File 00-owned second factor | Implemented |
| F02-FR-009 Completion | File 00 state including city/type/ethics/photo, same-origin route and loop prevention | Implemented |
| F02-FR-010 Redirect safety | same-origin allowlist and canonical route validation | Implemented |
| F02-FR-011 Audit events | versioned outbox, trace IDs, retries/dead-letter, secret stripping and all auth facts | Implemented |
| F02-FR-012 Degraded UX | provider circuits, Safe Mode, bounded HTTP, explicit failure states and public-read preservation | Implemented |

## Non-functional requirements

| Requirement | Source resolution | Evidence boundary |
|---|---|---|
| F02-NFR-001 Authorization | File 00 mandatory, nonce/subject binding, fail closed | Real-role IDOR staging pending |
| F02-NFR-002 Privacy | minimization, HMAC/encryption, export/erasure/anonymization | Provider/deletion staging pending |
| F02-NFR-003 Reliability | idempotency, atomic claims, retry/dead-letter, circuit breakers | Failure injection pending |
| F02-NFR-004 Performance | bounded lists/indexes/timeouts and route-scoped assets | Measured p75/p95 pending |
| F02-NFR-005 Accessibility | labels, status roles, keyboard/focus/touch/reflow/reduced motion | Human WCAG/RTL/browser pending |
| F02-NFR-006 Observability | traces, audits, provider state, System Check and diagnostics | Implemented |
| F02-NFR-007 Migration/rollback | additive versioning, option/route compatibility, non-destructive uninstall | Real upgrade/rollback pending |
| F02-NFR-008 Operability | Safe Mode, health report, guarded repair and manifests | Implemented |
| F02-NFR-009 Compatibility | PHP 7.4/8.3 CI, WordPress APIs, deterministic package and clean extraction | Hostinger pending |
| F02-NFR-010 Localization | text domain, semantic markup, logical CSS and mixed-direction baseline | Linguistic/visual QA pending |

## Consolidated directives

| Directive | Resolution |
|---|---|
| Green primary identity | Authentication CSS uses green identity |
| Islamic privacy and dignity | minimization, no raw secrets/full IP, separate Ethical Conduct consent |
| One canonical owner | File 02/File 00/File 03/File 20 boundaries are explicit |
| Fresh review → fix → retest | enforced by review record and CI |
| Zero known defect release gate | source gate only; new evidence reopens review |
| Observable progress | branch, exact head, jobs, artifacts, checksums and remaining gates reported |
| Responsive/RTL/accessibility | source baseline present; real acceptance remains separate |
| Staging first | no live authorization in repository |

## External evidence gates

- owner-level repository rename;
- paired File 00 contract acceptance/merge;
- current exact-head GitHub Actions success and retained artifact;
- Hostinger fresh install/upgrade/deactivate/reactivate/uninstall;
- real SMTP/Google/File 01/File 20/File 03/File 24/theme/LiteSpeed;
- real roles, IDOR, privacy, replay and race tests;
- mobile/browser/RTL/WCAG/performance/load;
- backup/restore and rollback rehearsal;
- Founder staging approval, production deployment and operational monitoring.

These external gates do not invalidate completed source coding, but they prohibit any claim that the system is staging-accepted, live or operational before evidence exists.
