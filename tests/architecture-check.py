#!/usr/bin/env python3
"""Fail-fast architecture guard for File 02 plan-harmonization candidate."""
from __future__ import annotations

import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]
PHP = {p.relative_to(ROOT).as_posix(): p.read_text(encoding="utf-8") for p in ROOT.rglob("*.php")}
SOURCE_PHP = {path: text for path, text in PHP.items() if not path.startswith("tests/")}
ALL = "\n".join(SOURCE_PHP.values())


def fail(message: str) -> None:
    print(f"ERROR: {message}", file=sys.stderr)
    raise SystemExit(1)


required_files = {
    "includes/class-sa-membership-adapter.php",
    "includes/class-sa-authentication-assurance.php",
    "includes/class-sauth-account-contract.php",
    "includes/class-sauth-event-outbox.php",
    "includes/class-sauth-email-verification.php",
    "includes/class-sauth-session-manager.php",
    "templates/login.php",
    "templates/signup.php",
    "templates/google-account.php",
    "templates/google-verify.php",
    "templates/reset-password.php",
}
missing = sorted(required_files - set(PHP))
if missing:
    fail("missing harmonization files: " + ", ".join(missing))

main = PHP["sabri-authentication.php"]
if "Version: 0.4.0" not in main or "define( 'SA_VERSION', '0.4.0' );" not in main:
    fail("plugin version is not consistently 0.4.0")
for marker in (
    "class-sa-security.php",
    "class-sauth-account-contract.php",
    "class-sauth-event-outbox.php",
    "class-sauth-email-verification.php",
    "class-sauth-session-manager.php",
    "class-sa-authentication-assurance.php",
    "class-sa-membership-adapter.php",
    "SA_ACCOUNT_CONTRACT_VERSION",
    "SA_AUTH_EVENT_SCHEMA_VERSION",
    "SAUTH_Event_Outbox::init()",
    "SAUTH_Email_Verification::init()",
    "SAUTH_Session_Manager::init()",
    "SA_Authentication_Assurance::init()",
):
    if marker not in main:
        fail(f"main plugin bootstrap is missing {marker}")

forbidden = {
    "role creation": r"\badd_role\s*\(",
    "role removal": r"\bremove_role\s*\(",
    "role mutation": r"->\s*set_role\s*\(",
    "capability mutation": r"->\s*(?:add_cap|remove_cap)\s*\(",
    "parallel user creation": r"\b(?:wp_insert_user|wp_create_user)\s*\(",
    "opaque wp_signon bypass": r"\bwp_signon\s*\(",
    "File 00 private 2FA flag": r"_smc_2fa_enabled",
    "File 00 private TOTP storage": r"_smc_totp_secret(?:_enc)?",
    "direct File 00 recovery-code mutation": r"SMC_Security::consume_recovery_code",
    "direct File 00 TOTP verification": r"SMC_Security::verify_(?:totp|setup_code|two_factor_challenge)",
}
for label, pattern in forbidden.items():
    if re.search(pattern, ALL):
        fail(f"forbidden {label} found")

adapter = PHP["includes/class-sa-membership-adapter.php"]
for marker in (
    "MIN_VERSION     = '1.2.7'",
    "SMC_CF01_CONTRACT_VERSION",
    "SMC_CF01_Contract::membership_assertion",
    "SA_Authentication_Assurance::verify_and_record",
    "SA_Authentication_Assurance::assertion",
    "SA_Security::page_url( 'login'",
    "SA_Security::page_url( 'signup'",
    "authentication_link",
    "authentication_unlink",
):
    if marker not in adapter:
        fail(f"Membership Core adapter is missing {marker}")

account = PHP["includes/class-sauth-account-contract.php"]
for marker in (
    "sauth.account-orchestration",
    "smc.authentication-account",
    "SMC_AUTHENTICATION_CONTRACT_VERSION",
    "register_account",
    "mark_email_verified",
    "get_completion_state",
    "provider_unavailable",
    "provider_contract_invalid",
):
    if marker not in account:
        fail(f"account orchestration boundary is missing {marker}")
for forbidden_marker in ("wp_insert_user", "wp_create_user", "add_role", "set_role"):
    if forbidden_marker in account:
        fail(f"account orchestration boundary contains parallel owner mutation: {forbidden_marker}")

outbox = PHP["includes/class-sauth-event-outbox.php"]
for marker in (
    "AccountAuthenticationSucceeded.v1",
    "AccountAuthenticationFailed.v1",
    "EmailVerified.v1",
    "PasswordResetCompleted.v1",
    "AuthSessionRevoked.v1",
    "dead_letter",
    "trace_id",
    "sanitize_payload",
    "sa_auth_outbox",
):
    if marker not in outbox:
        fail(f"authentication event outbox is missing {marker}")

email = PHP["includes/class-sauth-email-verification.php"]
for marker in (
    "TOKEN_TTL",
    "RESEND_DELAY",
    "MAX_ATTEMPTS",
    "token_hash",
    "hash_equals",
    "mark_email_verified",
    "EmailVerified.v1",
    "delivery_failed",
    "sa_email_verifications",
    "sauth_email_verification_delivery",
):
    if marker not in email:
        fail(f"email verification lifecycle is missing {marker}")
for forbidden_marker in ("update_user_meta", "wp_insert_user", "wp_create_user", "access_token", "refresh_token"):
    if forbidden_marker in email:
        fail(f"email verification contains forbidden ownership or secret marker: {forbidden_marker}")

registration = PHP["includes/class-sa-registration.php"]
for marker in (
    "SAUTH_Account_Contract::register_account",
    "SAUTH_Email_Verification::issue",
    "validate_registration",
    "MIN_MALE_AGE",
    "MIN_FEMALE_AGE",
    "guardian_reference",
    "identity_reference",
    "wp_check_password",
    "wp_set_auth_cookie",
    "membership_assertion",
    "get_completion_state",
    "AccountAuthenticationSucceeded.v1",
    "AccountAuthenticationFailed.v1",
    "check_password_reset_key",
    "reset_password",
    "destroy_all",
    "PasswordResetCompleted.v1",
):
    if marker not in registration:
        fail(f"registration/authentication surface is missing {marker}")
for forbidden_marker in ("wp_insert_user", "wp_create_user", "set_role", "add_role"):
    if forbidden_marker in registration:
        fail(f"registration surface contains File 00 ownership bypass: {forbidden_marker}")

sessions = PHP["includes/class-sauth-session-manager.php"]
for marker in (
    "destroy_others",
    "destroy_all",
    "generalize_user_agent",
    "generalize_ip",
    "AuthSessionRevoked.v1",
):
    if marker not in sessions:
        fail(f"session manager is missing {marker}")
if "session_token' =>" in sessions or '$_COOKIE' in sessions:
    fail("session manager may expose raw session material")

assurance = PHP["includes/class-sa-authentication-assurance.php"]
for marker in (
    "sa.cf01.authentication-assurance",
    "set_logged_in_cookie",
    "clear_auth_cookie",
    "provider_available",
    "provider_contract_invalid",
    "session_binding",
    "scope_hash",
    "pending_receipt",
    "assurance_missing_expired_or_mismatched",
    "SMC_CF01_Contract::verify_step_up",
    "SMC_CF01_Contract::membership_assertion",
):
    if marker not in assurance:
        fail(f"authentication assurance is missing {marker}")
for forbidden_marker in ("wp_get_session_token() );", "'session_token' =>", "'code' =>", "'secret' =>"):
    if forbidden_marker in assurance:
        fail(f"authentication assurance may expose sensitive material: {forbidden_marker}")

profile = PHP["includes/class-sa-profile.php"]
if "SA_Membership_Adapter::profile_url" not in profile:
    fail("legacy profile route does not delegate to Membership Core")

access = PHP["includes/class-sa-access-control.php"]
for marker in (
    "privacy_hooks",
    "noindex",
    "noarchive",
    "nocache_headers",
    "no-store",
    "X-Robots-Tag",
    "Referrer-Policy: no-referrer",
    "X-Frame-Options",
    "sabri_auth_verify_email",
    "sabri_auth_reset_password",
    "sabri_auth_sessions",
):
    if marker not in access:
        fail(f"private-page response protection is missing {marker}")

google = PHP["includes/class-sa-google-oauth.php"]
for marker in (
    "_sa_google_link_version",
    "SA_Membership_Adapter::verify_second_factor",
    "SA_Membership_Adapter::can_use_google",
    "The Google email must exactly match",
    "This Google account is not linked",
):
    if marker not in google:
        fail(f"Google flow is missing security marker: {marker}")
if "find_or_create_user" in google:
    fail("legacy Google auto-create path remains")

privacy = PHP["includes/class-sa-privacy.php"]
for key in (
    "_sa_account_type",
    "_sa_profile_complete",
    "_sa_terms_accepted_at",
    "_sa_privacy_accepted_at",
    "_sa_google_sub",
    "_sa_google_link_version",
    "sa_email_verifications",
    "token",
):
    if key not in privacy:
        fail(f"privacy coverage is missing {key}")

security = PHP["includes/class-sa-security.php"]
for marker in ("sa_rate_limits", "ON DUPLICATE KEY UPDATE", "clear_rate_limit", "aes-256-gcm", "state['expires']"):
    if marker not in security:
        fail(f"security implementation is missing {marker}")

activator = PHP["includes/class-sa-activator.php"]
for marker in (
    "sa_page_map",
    "_sa_managed_page",
    "exact_shortcode_page",
    "known_id",
    "sa_auth_outbox",
    "sa_email_verifications",
    "sauth_dummy_password_hash",
    "verify-email",
    "reset-password",
    "account-sessions",
):
    if marker not in activator:
        fail(f"activation/schema implementation is missing {marker}")

login_template = PHP["templates/login.php"]
for marker in ("user_login", "current-password", "sa_login", "password_ready"):
    if marker not in login_template:
        fail(f"password login template is missing {marker}")

signup_template = PHP["templates/signup.php"]
for marker in ("date_of_birth", "identity_reference", "guardian_reference", "accept_terms", "accept_privacy"):
    if marker not in signup_template:
        fail(f"registration template is missing {marker}")

print("File 02 architecture guard passed.")
print(f"PHP files checked: {len(PHP)}")
