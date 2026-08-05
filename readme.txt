=== Sabri Authentication and Accounts ===
Contributors: sabrihomeopathy
Tags: authentication, google login, registration, accounts, recovery, sessions, security
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Complete authentication and account-entry orchestration for the Sabri Social Homeopathy Platform. File 00 — Sabri Membership Core remains the exclusive identity, membership, account-class, guardian, role, verification and MFA-policy authority.

== Truthful release status ==

Version 1.1.0 is the three-plan-complete source candidate for SSH-F02-PLAN-2026-v1.0, the Definitive Master Plan v3.0 and the Consolidated All-Chats Directives v2.1. Source completion and automated QA do not by themselves prove Hostinger staging acceptance, live deployment or operational acceptance.

== Canonical constitution ==

* Canonical repository name: `02-sabri-authentication-and-accounts`.
* Current historical GitHub transport repository: `02-sabri-authentication`; repository rename is an owner-level GitHub administration action and does not change the package slug.
* Package folder and WordPress slug: `02-sabri-authentication` / `sabri-authentication`.
* Canonical PHP prefix: `SAUTH_`; pre-1.1 `SA_` classes/constants remain compatibility aliases only.
* Canonical session route: `/account/sessions/`; the old `/account-sessions/` page redirects permanently.

== Required dependency ==

File 00 — Sabri Membership Core 1.3.0 or later with:

* `smc.cf01.membership-assurance` 1.0.0 or later; and
* `smc.authentication-account` 1.1.0 or later.

If a required contract is missing, malformed or circuit-open, protected mutations fail closed. Public reading remains available.

== Implemented source capabilities ==

* Complete email/password and Google-first registration. Google proves email ownership only; all mandatory completion fields remain required.
* Required name, email, mobile/phone, country, city, full address, date of birth, sex, declared account type, National ID/Passport reference, guardian reference, profile-photograph completion acknowledgement, Terms, Privacy and separate Ethical Conduct consent.
* Male 15/female 12 platform baselines, every legal minor guardian requirement and adult-only professional/institutional declarations.
* File 00-owned account-class truth and verification; declared doctor/teacher/staff status never grants privilege.
* Signed one-time email verification with expiry, HMAC-only token storage, canonical-email binding, resend throttle, replay/concurrency protection and audit/event evidence.
* Password authentication using WordPress APIs, dummy hashing for unknown accounts, generic errors, rate controls and File 00 eligibility/completion rechecks.
* Google OAuth state, nonce, PKCE, issuer/audience/authorized-party/time validation, explicit same-email linking and collision protection.
* New-device/network/recent-failure risk scoring with File 00-owned step-up.
* Loop-safe account-completion resolution including profile photograph, city, account type, ethical consent, phone, identity, guardian and MFA steps.
* Opaque per-session registry, current marker, generalized device/network presentation, individual revoke, revoke others and sign out everywhere.
* Password recovery/reset and all-session revocation.
* Versioned privacy-minimized event outbox, provider circuit breakers, bounded HTTP controls, Safe Mode, System Check and guarded repair.
* File 01 module manifest and File 20 route/layout manifest with the canonical nested session route.
* Privacy export/erasure/anonymization, additive migration, non-destructive uninstall, deterministic package, manifest, checksums and SBOM.
* Green primary identity, logical responsive CSS, keyboard focus, reduced motion and RTL-ready structure.

== External acceptance gates ==

* Owner-level GitHub repository rename to the canonical repository name.
* Compatible File 00 v1.1 account-contract branch acceptance/merge.
* Hostinger staging fresh install, upgrades, real MySQL/dbDelta, SMTP and Google OAuth.
* File 01/File 20/File 03/File 24/theme/LiteSpeed integration.
* Real Founder/member/minor/guardian/suspended/security-operator journeys.
* IDOR/CSRF/replay/race/privacy, browser/mobile/RTL/WCAG, performance/load, backup/restore and rollback acceptance.
* Founder approval, controlled production deployment and monitored rollback window.

== Security ==

Passwords, reset keys, verification tokens, OAuth tokens, TOTP/recovery codes, raw session tokens, full IP addresses and provider secrets are excluded from events and public diagnostics. Authentication success is never authorization.

== Changelog ==

= 1.1.0 =
* Added all missing Definitive Master Plan registration fields: city, declared account type, profile-photo completion and separate Ethical Conduct consent.
* Added secure Google-first registration with state, nonce, PKCE, exact claims validation, one-time context and duplicate-safe link finalization.
* Added File 00 `smc.authentication-account` 1.1.0 consumer boundary.
* Added canonical `/account/sessions/` route and legacy redirect.
* Added canonical `SAUTH_` constants/classes with bounded backward compatibility.
* Reconciled repository/package/route/version/status documentation and release evidence.
* Added three-plan source guard and package artifact retention.

= 1.0.0 =
* Completed F02-FR-001 through F02-FR-012 source implementation.
* Added password registration/authentication, signed email verification, risk challenges, completion resolver, session registry, provider health and operational controls.

= 0.3.0 =
* Added session/purpose/scope-bound authentication assurance and professional reauthentication bridge.

= 0.2.0 =
* Made File 00 mandatory and removed parallel role/profile ownership.
