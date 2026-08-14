#!/usr/bin/env python3
"""Fail-fast architecture guard for the current File 02 release-locked source candidate."""
from __future__ import annotations

import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]
LOCK = json.loads((ROOT / "RELEASE-LOCK.json").read_text(encoding="utf-8"))
RELEASE_VERSION = str(LOCK.get("release_version", ""))
DB_VERSION = str(LOCK.get("database_version", ""))
PASSKEY_SCHEMA_VERSION = str(LOCK.get("passkey_schema_version", ""))
if not RELEASE_VERSION or not DB_VERSION or not PASSKEY_SCHEMA_VERSION:
    print("ERROR: RELEASE-LOCK.json is missing current release/schema identity", file=sys.stderr)
    raise SystemExit(1)

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
    "includes/class-sauth-storage-router.php",
    "includes/class-sauth-operations.php",
    "includes/class-sauth-google-registration.php",
    "includes/class-sauth-canonical-routes.php",
    "includes/class-sauth-passkeys.php",
    "templates/login.php",
    "templates/signup.php",
    "templates/google-account.php",
    "templates/google-verify.php",
    "templates/reset-password.php",
}
missing = sorted(required_files - set(PHP))
if missing:
    fail("missing current source files: " + ", ".join(missing))

main = PHP["sabri-authentication.php"]
for marker in (
    f"Version: {RELEASE_VERSION}",
    f"define( 'SAUTH_VERSION', '{RELEASE_VERSION}' );",
    f"define( 'SAUTH_DB_VERSION', '{DB_VERSION}' );",
    "define( 'SAUTH_ACCOUNT_CONTRACT_VERSION', '1.1.0' );",
    "define( 'SAUTH_PASSKEY_CONTRACT_VERSION', '1.0.0' );",
    "class-sauth-storage-router.php",
    "class-sauth-google-registration.php",
    "class-sauth-canonical-routes.php",
    "class-sauth-passkeys.php",
    "SAUTH_Storage_Router::init()",
    "SAUTH_Google_Registration::init()",
    "SAUTH_Canonical_Routes::init()",
    "SAUTH_Passkeys::init()",
):
    if marker not in main:
        fail(f"main plugin bootstrap is missing {marker}")

storage_require = "require_once SAUTH_DIR . 'includes/class-sauth-storage-router.php';"
if storage_require not in main:
    fail("main plugin bootstrap does not load the storage router")
if main.index(storage_require) > main.index("function sauth_start_plugin()"):
    fail("storage router must load before File 02 startup")

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
        "PROVIDER_MIN_VERSION = '1.1.0'",
        "SMC_Authentication_Contract_V11",
        "register_account",
        "mark_email_verified",
        "get_completion_state",
        "ethical_conduct_version",
        "profile_photo_required",
        "account_type",
        "city",
    ),
)

require_markers(
    "includes/class-sauth-google-registration.php",
    (
        "code_challenge_method",
        "S256",
        "nonce",
        "email_verified",
        "hash_equals",
        "finalize_link",
        "get_users",
        "google_registration_context",
    ),
)

require_markers(
    "includes/class-sauth-canonical-routes.php",
    (
        "^account/sessions/?$",
        "/account/sessions/",
        "02-sabri-authentication-and-accounts",
        "SAUTH_",
        "sabri_shell_route_manifests",
        "spf_module_manifests",
    ),
)

require_markers(
    "includes/class-sa-registration.php",
    (
        "SAUTH_Google_Registration::context",
        "SAUTH_Google_Registration::finalize_link",
        "authentication_method",
        "account_type",
        "ethical_conduct_version",
        "profile_photo_required",
        "city",
        "MIN_MALE_AGE",
        "MIN_FEMALE_AGE",
        "guardian_reference",
        "wp_check_password",
        "membership_assertion",
        "check_password_reset_key",
        "revoke_user_sessions",
    ),
)

require_markers(
    "includes/class-sauth-completion-resolver.php",
    (
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
        "SAUTH_Provider_Health::available_for_ui",
        "SAUTH_Passkeys::authentication_ready",
        "SAUTH_Completion_Resolver::resolve",
        "passkey_step_up_required",
    ),
)

require_markers(
    "includes/class-sauth-session-manager.php",
    (
        "token_hash",
        "public_id",
        "deny_revoked_session",
        "revoke_one",
        "revoke_others",
        "revoke_user_sessions",
        "destroy_others",
        "destroy_all",
    ),
)
if "'session_token' =>" in PHP["includes/class-sauth-session-manager.php"]:
    fail("session manager may expose raw session material")

passkeys = PHP["includes/class-sauth-passkeys.php"]
for marker in (
    "CONTRACT_VERSION      = '1.0.0'",
    f"SCHEMA_VERSION        = '{PASSKEY_SCHEMA_VERSION}'",
    "smc_file02_authentication_assurance_v1",
    "webauthn.create",
    "webauthn.get",
    "parse_attestation_object",
    "cose_public_key_to_pem",
    "attestation_format_not_allowed",
    "credential_public_key",
    "add_option( self::challenge_claim_key",
    "userVerification' => 'required",
    "residentKey' => 'required",
    "hash( 'sha256', (string) $raw_id )",
    "$hardware_backed = false;",
    "PasskeyRegistered.v1",
    "PasskeyAuthenticated.v1",
    "PasskeyRevoked.v1",
):
    if marker not in passkeys:
        fail(f"passkey/WebAuthn source is missing {marker}")
js = (ROOT / "assets/js/authentication.js").read_text(encoding="utf-8")
if "getPublicKey(" in js:
    fail("registration may not trust a client-supplied WebAuthn public key")
if "attestation_object" not in js:
    fail("browser must return attestationObject to the server for key extraction")

outbox = PHP["includes/class-sauth-event-outbox.php"]
for event in ("PasskeyRegistered.v1", "PasskeyAuthenticated.v1", "PasskeyRevoked.v1"):
    if event not in outbox:
        fail(f"event outbox is missing {event}")

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

signup = PHP["templates/signup.php"]
for marker in (
    'name="city"',
    'name="account_type"',
    'name="profile_photo_required"',
    'name="accept_ethics"',
    'name="google_registration_token"',
):
    if marker not in signup:
        fail(f"registration surface is missing {marker}")

login = PHP["templates/login.php"]
if "data-sauth-passkey-login" not in login:
    fail("login surface does not expose passkey authentication")

print(f"File 02 {RELEASE_VERSION} release-locked four-plan architecture guard passed.")
print(f"DB identity checked: {DB_VERSION}; passkey schema: {PASSKEY_SCHEMA_VERSION}")
print(f"PHP files checked: {len(PHP)}")
