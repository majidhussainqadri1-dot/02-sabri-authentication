#!/usr/bin/env python3
"""V12.3: pure offline parity must detect identical files, extras and ambiguous activation."""
import hashlib
import importlib.util
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parents[2]
SOURCE = ROOT / "tools" / "file02-offline-deployment-parity.py"
spec = importlib.util.spec_from_file_location("file02_parity", SOURCE)
assert spec and spec.loader
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)

def sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()

files = [
    {"path": "sabri-authentication.php", "bytes": 21, "sha256": sha(b"example plugin header")},
    {"path": "includes/owner.php", "bytes": 6, "sha256": sha(b"helper")},
]
manifest = {
    "schema": "sauth.package-manifest.v1",
    "plugin": "Sabri Authentication and Accounts",
    "slug": "sabri-authentication",
    "package_root": "02-sabri-authentication",
    "version": "1.4.0",
    "file_count": len(files),
    "files": files,
}
installed = {row["path"]: row["sha256"] for row in files}
self_bytes = (json.dumps(manifest, indent=2, sort_keys=True) + "\n").encode()
installed["PACKAGE-MANIFEST.json"] = sha(self_bytes)
source = {
    "present": True, "main_php_sha256": files[0]["sha256"],
    "plugin_header_version": "1.4.0", "file_count": len(installed),
    "tree_sha256": module.tree_digest(installed),
    "symlink_count_skipped": 0, "read_error": False,
}
snapshot = {
    "schema": "sauth.live-reality-freeze.readonly.v1",
    "plugins": {
        "File02-standard": {"active_site": False, "active_network": False,
                            "source": {"present": False}},
        "File02-package": {"active_site": True, "active_network": False,
                           "source": source},
    },
}
actual = module.compare(snapshot, manifest)
assert actual["file_byte_parity"] == "match", actual
assert actual["live_functional_verification"] == "not_tested", actual
assert actual["DB_version_verified"] is False and actual["migration_verified"] is False
# Extra installed file must be a real mismatch, not a success by primary PHP alone.
import copy
extra = copy.deepcopy(snapshot)
extra["plugins"]["File02-package"]["source"]["file_count"] += 1
assert module.compare(extra, manifest)["file_byte_parity"] == "mismatch"
# A plugin-root collision cannot be treated as a verified match.
duplicate = copy.deepcopy(snapshot)
duplicate["plugins"]["File02-standard"]["active_site"] = True
assert module.compare(duplicate, manifest)["file_byte_parity"] == "unverified"
# Hash errors and unreadable trees are UNKNOWN, not false mismatches.
corrupt = copy.deepcopy(snapshot)
corrupt["plugins"]["File02-package"]["source"]["read_error"] = True
assert module.compare(corrupt, manifest)["file_byte_parity"] == "unverified"
# Mismatching code, schema and package identity MUST never become a match.
bad_version = copy.deepcopy(snapshot)
bad_version["plugins"]["File02-package"]["source"]["plugin_header_version"] = "1.3.4"
assert module.compare(bad_version, manifest)["file_byte_parity"] == "mismatch"
wrong_pkg = copy.deepcopy(manifest)
wrong_pkg["package_root"] = "sabri-authentication"
assert module.compare(snapshot, wrong_pkg)["file_byte_parity"] == "unverified"
unsafe = copy.deepcopy(manifest)
unsafe["files"][0]["path"] = "../file.php"
assert module.compare(snapshot, unsafe)["file_byte_parity"] == "unverified"
print("PASS File02 P00/P11 offline parity: exact bytes, extras, duplicate install, unverified failures and live nonclaims")
