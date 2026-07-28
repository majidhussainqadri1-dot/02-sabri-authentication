#!/usr/bin/env python3
"""Fail-fast architecture guard for File 02 corrective release."""
from __future__ import annotations

import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]
PHP = {p.relative_to(ROOT).as_posix(): p.read_text(encoding="utf-8") for p in ROOT.rglob("*.php")}
ALL = "\n".join(PHP.values())


def fail(message: str) -> None:
    print(f"ERROR: {message}", file=sys.stderr)
    raise SystemExit(1)


required_files = {
    "includes/class-sa-membership-adapter.php",
    "templates/google-account.php",
    "templates/google-verify.php",
}
missing = sorted(required_files - set(PHP))
if missing:
    fail("missing corrective files: " + ", ".join(missing))

main = PHP["sabri-authentication.php"]
if "Version: 0.2.0" not in main or "define( 'SA_VERSION', '0.2.0' );" not in main:
    fail("plugin version is not consistently 0.2.0")
if "class-sa-membership-adapter.php" not in main:
    fail("Membership Core adapter is not loaded")

forbidden = {
    "role creation": r"\badd_role\s*\(",
    "role removal": r"\bremove_role\s*\(",
    "role mutation": r"->\s*set_role\s*\(",
    "capability mutation": r"->\s*(?:add_cap|remove_cap)\s*\(",
    "parallel user creation": r"\b(?:wp_insert_user|wp_create_user)\s*\(",
    "parallel password login": r"\bwp_signon\s*\(",
}
for label, pattern in forbidden.items():
    if re.search(pattern, ALL):
        fail(f"forbidden {label} found")

adapter = PHP["includes/class-sa-membership-adapter.php"]
for marker in ("SMC_VERSION", "MIN_VERSION", "version_compare", "smc_page_url", "smc_user_status", "SMC_Security"):
    if marker not in adapter:
        fail(f"Membership Core adapter is missing {marker}")

registration = PHP["includes/class-sa-registration.php"]
if "SA_Membership_Adapter::register_url" not in registration or "SA_Membership_Adapter::login_url" not in registration:
    fail("legacy login/registration routes do not delegate to Membership Core")

profile = PHP["includes/class-sa-profile.php"]
if "SA_Membership_Adapter::profile_url" not in profile:
    fail("legacy profile route does not delegate to Membership Core")

access = PHP["includes/class-sa-access-control.php"]
for marker in ("privacy_hooks", "noindex", "noarchive", "nocache_headers", "no-store", "X-Robots-Tag", "Referrer-Policy: no-referrer", "X-Frame-Options"):
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
):
    if key not in privacy:
        fail(f"privacy coverage is missing {key}")

security = PHP["includes/class-sa-security.php"]
for marker in ("sa_rate_limits", "ON DUPLICATE KEY UPDATE", "clear_rate_limit", "aes-256-gcm", "state['expires']"):
    if marker not in security:
        fail(f"security implementation is missing {marker}")

activator = PHP["includes/class-sa-activator.php"]
for marker in ("sa_page_map", "_sa_managed_page", "exact_shortcode_page", "known_id"):
    if marker not in activator:
        fail(f"managed-page idempotency is missing {marker}")

print("File 02 architecture guard passed.")
print(f"PHP files checked: {len(PHP)}")
