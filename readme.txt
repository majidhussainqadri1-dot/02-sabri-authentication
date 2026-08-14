=== Sabri Authentication and Accounts ===
Contributors: sabrihomeopathy
Tags: authentication, passkeys, webauthn, google login, registration, accounts, recovery, sessions, security
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later

Complete authentication and account-entry orchestration for the Sabri Social Homeopathy Platform. File 00 — Sabri Membership Core remains the exclusive identity, membership, account-class, guardian, role, verification and MFA-policy authority; File 02 owns password, Google OAuth and WebAuthn/passkey authentication ceremonies.

== Truthful release status ==

Version 1.2.1 contains the repository correction for a bootstrap defect proven by a real File 00/File 02 WordPress integration run against 1.2.0. A real File 00/File 02 WordPress integration run proved that 1.2.0 could pass its File 00 dependency activation gate and then fatal on the next request because `SAUTH_Storage_Router::init()` was called without loading `class-sauth-storage-router.php`. Version 1.2.1 corrects that exact bootstrap defect without changing the File 02 DB schema or ownership contracts. Source completion and automated QA do not by themselves prove Hostinger staging acceptance, live deployment or operational acceptance.

== Canonical constitution ==

* Canonical repository name: `02-sabri-authentication-and-accounts`.
* Current historical GitHub transport repository: `02-sabri-authentication`; repository rename is an owner-level GitHub administration action and does not change the package slug.
* Package folder and WordPress slug: `02-sabri-authentication` / `sabri-authentication`.
* Canonical PHP prefix: `SAUTH_`; pre-1.1 `SA_` classes/constants remain compatibility aliases only.
* Canonical session route: `/account/sessions/`; the old `/account-sessions/` page redirects permanently.
* Passkey management route is a private, noindex/no-store File 02 managed page.

== Required dependency ==

File 00 — Sabri Membership Core with:

* `smc.cf01.membership-assurance` 1.0.0 or later;
* `smc.authentication-account` 1.1.0 or later; and
* for Advanced Trust elevation, File 00 Advanced Trust consumer support for `smc_file02_authentication_assurance_v1` 1.0.0.

If a required contract is missing, malformed or circuit-open, protected mutations fail closed. Public reading remains available.

== Implemented source capabilities ==

* Complete email/password and Google-first registration. Google proves email ownership only; all mandatory completion fields remain required.
* Required name, email, mobile/phone, country, city, full address, date of birth, sex, declared account type, National ID/Passport reference, guardian reference, profile-photograph completion acknowledgement, Terms, Privacy and separate Ethical Conduct consent.
* Male 15/female 12 platform baselines, every legal minor guardian requirement and adult-only professional/institutional declarations.
* File 00-owned account-class truth and verification; declared doctor/teacher/staff status never grants privilege.
* Signed one-time email verification with expiry, HMAC-only token storage, canonical-email binding, resend throttle, replay/concurrency protection and audit/event evidence.
* Password authentication using WordPress APIs, dummy hashing for unknown accounts, generic errors, rate controls and File 00 eligibility/completion rechecks.
* Google OAuth state, nonce, PKCE, issuer/audience/authorized-party/time validation, explicit same-email linking and collision protection.
* WebAuthn/passkey usernameless sign-in and passkey enrollment/revocation with required user verification, discoverable credentials, RP-ID/origin binding, one-time challenge replay claims, server-side CBOR/COSE parsing, ES256/RS256 verification, signature-counter checks and privacy-minimized metadata.
* Passkey registration accepts only the COSE public key embedded in authenticatorData inside an `attestation=none` attestation object; a browser-supplied public key is never trusted.
* Fresh passkey assurance is session-bound and projected to File 00 as owner=`file02`, contract `1.0.0`, level 3, `passkey_asserted=true`; hardware-backed status is not claimed when attestation provenance is intentionally unavailable.
* Passkey enrollment/revocation requires fresh reauthentication: a fresh File 02 passkey assurance, otherwise the current password. Retired File 00 Authenticator/recovery codes are never solicited or accepted as File 02 authentication authority.
* New-device/network/recent-failure risk scoring; elevated password risk requires a separate File 02 passkey sign-in.
* Loop-safe account-completion resolution including profile photograph, city, account type, ethical consent, phone, identity, guardian and MFA steps.
* Opaque per-session registry, current marker, generalized device/network presentation, individual revoke, revoke others and sign out everywhere.
* Password recovery/reset and all-session revocation.
* Versioned privacy-minimized event outbox including passkey registered/authenticated/revoked facts, provider circuit breakers, bounded HTTP controls, Safe Mode, System Check and guarded repair.
* File 01 module manifest and File 20 route/layout manifest with the canonical nested session route.
* Privacy export/erasure/anonymization, additive migration, non-destructive uninstall, deterministic package, manifest, checksums and SBOM.
* Green primary identity, logical responsive CSS, keyboard focus, reduced motion and RTL-ready structure.

== Passkey security and privacy boundaries ==

* HTTPS is mandatory except standards-permitted localhost development.
* Registration requests `attestation: none`; no attestation certificate chain or biometric information is retained.
* Credential IDs are opaque random identifiers; an encrypted copy supports exclusion UI while a stable SHA-256 lookup prevents WordPress salt rotation from breaking credential lookup.
* User handles are random opaque File 02 values and are erased with File 02 passkey data.
* Challenge completion uses an atomic WordPress option claim plus expiring challenge state so concurrent replay attempts cannot both succeed.
* Synchronized passkeys may legitimately use a zero signature counter; non-zero counter regression is treated as compromise and the credential is disabled.
* Authentication is not authorization. Every post-authentication protected action remains subject to File 00 claims and the native domain owner's object/state checks.

== External acceptance gates ==

* Owner-level GitHub repository rename to the canonical repository name.
* Hostinger staging fresh install and supported upgrades with real MySQL/dbDelta, SMTP, Google OAuth and real WebAuthn authenticators on the production RP ID/origin.
* File 00 Advanced Trust, File 01, File 20, File 03, File 24, theme and LiteSpeed integration.
* Real Founder/member/minor/guardian/suspended/security-operator journeys.
* IDOR/CSRF/replay/race/privacy, browser/mobile/RTL/WCAG, performance/load, backup/restore and rollback acceptance.
* Founder approval, controlled production deployment and monitored rollback window.

== Security ==

Passwords, reset keys, verification tokens, OAuth tokens, TOTP/recovery codes, passkey private keys, biometric templates, raw session tokens, full IP addresses and provider secrets are excluded from events and public diagnostics. Authentication success is never authorization.

== Changelog ==

= 1.2.1 =
* Corrects the live-integration-proven bootstrap defect in 1.2.0 by loading `class-sauth-storage-router.php` before `sauth_start_plugin()` invokes `SAUTH_Storage_Router::init()`.
* Adds a permanent bootstrap/runtime regression so dependency activation success must be followed by a clean subsequent WordPress request with File 02 still active.
* Preserves DB schema 1.2.0, account contract 1.1.0, passkey assurance 1.0.0 and all File 00/File 02 ownership boundaries.
* This repository correction is not a live-resolution claim; File 00 compatibility and File 02 live activation must be retested after controlled deployment.

= 1.2.0 =
* Added File 02-owned WebAuthn/passkey registration, authentication, management and privacy lifecycle for CV-005.
* Added server-side attestation-object CBOR parsing and COSE ES256/RS256 public-key extraction; removed any trust in a browser-supplied public key.
* Added atomic challenge replay claims, RP-ID/origin/user-verification binding, discoverable credentials, signature verification and counter-regression containment.
* Added versioned five-minute session-bound passkey assurance consumed by File 00 Advanced Trust without moving identity/MFA policy ownership out of File 00.
* Added conservative hardware-provenance semantics: attestation=none never claims `hardware_backed=true`.
* Integrated passkey events, privacy export/erasure, registration/revocation reauthentication, activation/deactivation lifecycle and no-network WebAuthn QA.
* Preserved all previous File 02 FR/NFR functionality and canonical `/account/sessions/` device/session center.

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
