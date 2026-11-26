#!/usr/bin/env python3
"""V12.3: offline, read-only FILE/BYTE parity checker. NO deploy or live functional claim.

Input 1: authorized P00 file02-live-reality-freeze-readonly.php JSON output.
Input 2: immutable exact-head 02-sabri-authentication-*-MANIFEST.json.
Never needs WordPress credentials or live network. Do not use on untrusted manifests.
"""
from __future__ import annotations
import argparse
import hashlib
import json
import pathlib
import re
import sys

HEX = re.compile(r"^[0-9a-f]{64}$")
LABELS = ("File02-standard", "File02-package")
EXPECTED_SCHEMA = "sauth.live-reality-freeze.readonly.v1"


def tree_digest(files: dict[str, str]) -> str:
    digest = hashlib.sha256()
    for name in sorted(files):
        digest.update(name.encode("utf-8") + b"\x00" + files[name].encode("ascii") + b"\n")
    return digest.hexdigest()


def compare(snapshot: dict, manifest: dict) -> dict:
    result = {
        "schema": "sauth.offline-deployment-parity.v1",
        "file_byte_parity": "unverified",
        "plugin_label": None,
        "reason": [],
        "source_package_root": manifest.get("package_root") if isinstance(manifest, dict) else None,
        "DB_version_verified": False,
        "migration_verified": False,
        "live_functional_verification": "not_tested",
    }
    if not isinstance(snapshot, dict) or snapshot.get("schema") != EXPECTED_SCHEMA:
        result["reason"].append("missing_or_wrong_authorized_snapshot_schema")
        return result
    if not isinstance(manifest, dict) or manifest.get("schema") != "sauth.package-manifest.v1":
        result["reason"].append("missing_or_wrong_source_manifest_schema")
        return result
    if manifest.get("slug") != "sabri-authentication" or manifest.get("package_root") != "02-sabri-authentication":
        result["reason"].append("unexpected_package_identity")
        return result
    files = manifest.get("files")
    if not isinstance(files, list) or not files or manifest.get("file_count") != len(files):
        result["reason"].append("missing_or_inconsistent_manifest_files")
        return result
    expected: dict[str, str] = {}
    for entry in files:
        if not isinstance(entry, dict):
            result["reason"].append("bad_manifest_entry")
            return result
        name, sha = entry.get("path"), entry.get("sha256")
        if (not isinstance(name, str) or not name or name.startswith("/") or
                ".." in pathlib.PurePosixPath(name).parts or "\\" in name or
                name in expected or not isinstance(sha, str) or not HEX.fullmatch(sha)):
            result["reason"].append("unsafe_or_duplicate_manifest_file")
            return result
        expected[name] = sha
    # build-package.sh writes the manifest itself into the installed ZIP.
    # Recreate the exact deterministic bytes written by the build script.
    package_manifest = json.dumps(manifest, indent=2, sort_keys=True, ensure_ascii=True) + "\n"
    if "PACKAGE-MANIFEST.json" in expected:
        result["reason"].append("self_reference_in_manifest")
        return result
    expected["PACKAGE-MANIFEST.json"] = hashlib.sha256(package_manifest.encode("utf-8")).hexdigest()
    expected_tree = tree_digest(expected)
    plugins = snapshot.get("plugins")
    if not isinstance(plugins, dict):
        result["reason"].append("missing_active_plugins_snapshot")
        return result
    active = [
        label for label in LABELS
        if isinstance(plugins.get(label), dict) and
        (plugins[label].get("active_site") is True or plugins[label].get("active_network") is True)
    ]
    if len(active) != 1:
        result["reason"].append("file02_plugin_activation_not_uniquely_identified")
        return result
    label = active[0]
    result["plugin_label"] = label
    source = plugins[label].get("source", {})
    if not isinstance(source, dict) or source.get("present") is not True:
        result["reason"].append("active_plugin_source_not_present")
        return result
    if source.get("read_error") is not False or source.get("symlink_count_skipped") != 0:
        result["reason"].append("incomplete_or_symlinked_plugin_tree")
        return result
    if not isinstance(source.get("tree_sha256"), str) or not HEX.fullmatch(source["tree_sha256"]):
        result["reason"].append("missing_deployed_tree_digest")
        return result
    if source.get("plugin_header_version") != manifest.get("version"):
        result["reason"].append("runtime_version_mismatch")
        result["file_byte_parity"] = "mismatch"
        return result
    if source.get("file_count") != len(expected) or source.get("tree_sha256") != expected_tree:
        result["reason"].append("installed_file_tree_differs_from_package_manifest")
        result["file_byte_parity"] = "mismatch"
        return result
    main_sha = expected.get("sabri-authentication.php")
    if source.get("main_php_sha256") != main_sha:
        result["reason"].append("main_plugin_sha256_mismatch")
        result["file_byte_parity"] = "mismatch"
        return result
    result["file_byte_parity"] = "match"
    result["reason"].append("file_bytes_match_only; no_db_migration_or_functional_acceptance")
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--snapshot", required=True, type=pathlib.Path)
    parser.add_argument("--manifest", required=True, type=pathlib.Path)
    args = parser.parse_args()
    try:
        snapshot = json.loads(args.snapshot.read_text(encoding="utf-8"))
        manifest = json.loads(args.manifest.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, ValueError) as error:
        print(json.dumps({"file_byte_parity":"unverified","error":type(error).__name__}))
        return 2
    report = compare(snapshot, manifest)
    print(json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True))
    return 0 if report["file_byte_parity"] == "match" else 1


if __name__ == "__main__":
    sys.exit(main())
