#!/usr/bin/env python3
"""Fail-fast architecture guard for the File 02 1.0.0 source candidate."""
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


def require_markers(path: str, markers: tuple[str, ...]) -> None:
    if path not in PHP:
        fail(f"missing required PHP file: {path}")
    for marker in markers:
        if marker not in PHP[path]:
            fail(f"{path} is missing {marker}")


required_files = {
    "includes/class-sa-security.php",
    "includes/class-sa-membership-adapter.php",
    "includes/class-sa-authentication-assurance.php",
    "includes/class-sauth-account-contract.php",
    "includes/class-sauth-completion-resolver.php",
    "includes/class-sauth-event-outbox.php",
    "includes/class-sauth-email-verification.php",
    "includes/class-sauth-login-risk.php",
    "includes/class-sauth-provider-health.php",
    "includes/class-sauth-provider-http-guard.php",
    "includes/class-sauth-session-manager.php",
    "includes/class-sauth-operations.php",
    "templates/login.php",
    "templates/signup.php",
    "templates/google-account.php",
    "templates/google-verify.php",
    "templates/reset-password.php",
}
missing = sorted(required_files - set(PHP))
if missing:
    fail("missing 1.0.0 source files: " + ", ".join(missing))

main = PHP["sabri-authentication.php"]
for marker in (
    "Version: 1.0.0",
    "define( 'SA_VERSION', '1.0.0' );",
    "define( 'SA_DB_VERSION', '1.0.0' );",
    "class-sauth-provider-health.php",
    "class-sauth-provider-http-guard.php",
    "class-sauth-completion-resolver.php",
    "class-sauth-login-risk.php",
    "SAUTH_Provider_Health::init()",
    "SAUTH_Provider_HTTP_Guard::init()",
    "SAUTH_Login_Risk::init()",
    "SAUTH_Session_Manager::init()",
    "SAUTH_Operations::init()",
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

require_markers(
    "includes/class-sauth-account-contract.php",
    (
        "sauth.account-orchestration",
        "smc.authentication-account",
        "SMC_AUTHENTICATION_CONTRACT_VERSION",
        "register_account",
        "mark_email_verified",
        "get_completion_state",
        "provider_unavailable",
        "provider_contract_invalid",
    ),
)

require_markers(
    "includes/class-sauth-completion-resolver.php",
    (
        "sauth.account-completion-resolver",
        "completion_loop_prevented",
        "canonical_completion_route",
        "MAX_REPEAT_VISITS",
        "wp_validate_redirect",
        "missing_steps",
    ),
)

require_markers(
    "includes/class-sauth-login-risk.php",
    (
        "new_device",
        "new_network",
        "recent_failures",
        "SA_Authentication_Assurance::verify_and_record",
        "step_up_verified",
        "sa_auth_risk_challenges",
        "sa_auth_devices",
        "sa_auth_attempts",
        "AccountAuthenticationSucceeded.v1",
    ),
)

require_markers(
    "includes/class-sauth-session-manager.php",
    (
        "sa_auth_sessions",
        "token_hash",
        "public_id",
        "deny_revoked_session",
        "revoke_one",
        "revoke_others",
        "revoke_user_sessions",
        "destroy_others",
        "destroy_all",
        "AuthSessionRevoked.v1",
    ),
)
if "'session_token' =>" in PHP["includes/class-sauth-session-manager.php"]:
    fail("session manager may expose raw session material")

require_markers(
    "includes/class-sauth-provider-health.php",
    (
        "FAILURE_THRESHOLD",
        "OPEN_SECONDS",
        "allow_request",
        "record_success",
        "record_failure",
        "half_open",
    ),
)
require_markers(
    "includes/class-sauth-provider-http-guard.php",
    (
        "pre_http_request",
        "http_request_args",
        "http_api_debug",
        "reject_unsafe_urls",
        "sslverify",
        "SAUTH_Provider_Health",
    ),
)

require_markers(
    "includes/class-sauth-operations.php",
    (
        "sauth.system-check",
        "SAFE_MODE_OPTION",
        "system_check",
        "handle_repair",
        "foundation_manifest",
        "shell_manifest",
        "route_manifest",
    ),
)

require_markers(
    "includes/class-sa-registration.php",
    (
        "SAUTH_Login_Risk::complete_password_login",
        "SAUTH_Completion_Resolver",
        "SAUTH_Provider_Health",
        "SAUTH_Account_Contract::register_account",
        "SAUTH_Email_Verification::issue",
        "validate_registration",
        "MIN_MALE_AGE",
        "MIN_FEMALE_AGE",
        "guardian_reference",
        "identity_reference",
        "wp_check_password",
        "membership_assertion",
        "check_password_reset_key",
        "reset_password",
        "revoke_user_sessions",
        "PasswordResetCompleted.v1",
    ),
)

require_markers(
    "includes/class-sa-authentication-assurance.php",
    (
        "sa.cf01.authentication-assurance",
        "set_logged_in_cookie",
        "clear_auth_cookie",
        "session_binding",
        "scope_hash",
        "pending_receipt",
        "SMC_CF01_Contract::verify_step_up",
        "SMC_CF01_Contract::membership_assertion",
    ),
)

require_markers(
    "includes/class-sauth-event-outbox.php",
    (
        "AccountAuthenticationSucceeded.v1",
        "AccountAuthenticationFailed.v1",
        "EmailVerified.v1",
        "PasswordResetCompleted.v1",
        "AuthSessionRevoked.v1",
        "dead_letter",
        "trace_id",
        "sanitize_payload",
        "sa_auth_outbox",
    ),
)

require_markers(
    "includes/class-sauth-email-verification.php",
    (
        "TOKEN_TTL",
        "RESEND_DELAY",
        "MAX_ATTEMPTS",
        "token_hash",
        "hash_equals",
        "mark_email_verified",
        "EmailVerified.v1",
        "delivery_failed",
        "sa_email_verifications",
    ),
)

require_markers(
    "includes/class-sa-activator.php",
    (
        "SA_DB_VERSION",
        "required_tables",
        "create_session_table",
        "create_device_table",
        "create_risk_challenge_table",
        "create_attempt_table",
        "confirm-sign-in",
        "sabri_auth_risk_challenge",
        "repair",
    ),
)

require_markers(
    "includes/class-sa-access-control.php",
    (
        "sabri_auth_risk_challenge",
        "noindex",
        "noarchive",
        "no-store",
        "Referrer-Policy: no-referrer",
        "Cross-Origin-Opener-Policy: same-origin",
    ),
)

privacy = PHP["includes/class-sa-privacy.php"]
for marker in (
    "sa_email_verifications",
    "sa_auth_sessions",
    "sa_auth_devices",
    "sa_auth_risk_challenges",
    "sa_auth_attempts",
    "privacy_anonymized",
):
    if marker not in privacy:
        fail(f"privacy lifecycle is missing {marker}")

security = PHP["includes/class-sa-security.php"]
for marker in ("sa_rate_limits", "ON DUPLICATE KEY UPDATE", "clear_rate_limit", "aes-256-gcm", "safe_redirect"):
    if marker not in security:
        fail(f"security implementation is missing {marker}")

require_markers("templates/login.php", ("user_login", "current-password", "sa_login", "password_ready"))
require_markers("templates/signup.php", ("date_of_birth", "identity_reference", "guardian_reference", "accept_terms", "accept_privacy"))

print("File 02 1.0.0 architecture guard passed.")
print(f"PHP files checked: {len(PHP)}")
