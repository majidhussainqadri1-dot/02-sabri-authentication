#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
VERSION="$(sed -n "s/^ \* Version: \([0-9.]*\)$/\1/p" "$ROOT/sabri-authentication.php" | head -n1)"
if [[ -z "$VERSION" ]]; then
  echo "Unable to determine plugin version." >&2
  exit 1
fi

OUTPUT_DIR="${1:-$ROOT/dist}"
PACKAGE_ROOT="$OUTPUT_DIR/package"
PLUGIN_DIR="$PACKAGE_ROOT/02-sabri-authentication"
ARCHIVE="$OUTPUT_DIR/02-sabri-authentication-${VERSION}-SOURCE-CANDIDATE.zip"
MANIFEST="$OUTPUT_DIR/02-sabri-authentication-${VERSION}-MANIFEST.json"
CHECKSUMS="$OUTPUT_DIR/CHECKSUMS.sha256"
SOURCE_DATE_EPOCH="${SOURCE_DATE_EPOCH:-1767225600}"

rm -rf "$OUTPUT_DIR"
mkdir -p "$PLUGIN_DIR"

cd "$ROOT"
RUNTIME_PATHS=(
  sabri-authentication.php
  uninstall.php
  readme.txt
  admin
  assets
  includes
  templates
)
DOCUMENT_PATHS=(
  ARCHITECTURE.md
  CONTRACTS.md
  DATA-DICTIONARY.md
  MIGRATION.md
  ROLLBACK.md
  BACKUP-RESTORE.md
  INCIDENT.md
  STAGING-ACCEPTANCE.md
  THREAT-MODEL.md
  PRIVACY-RETENTION.md
  CHANGELOG.md
  SBOM.spdx.json
)

for path in "${RUNTIME_PATHS[@]}" "${DOCUMENT_PATHS[@]}"; do
  if [[ ! -e "$ROOT/$path" ]]; then
    echo "Required package path is missing: $path" >&2
    exit 1
  fi
  if [[ -d "$ROOT/$path" ]]; then
    while IFS= read -r source; do
      relative="${source#./}"
      mkdir -p "$PLUGIN_DIR/$(dirname "$relative")"
      cp -p "$ROOT/$relative" "$PLUGIN_DIR/$relative"
    done < <(find "./$path" -type f -print | LC_ALL=C sort)
  else
    mkdir -p "$PLUGIN_DIR/$(dirname "$path")"
    cp -p "$ROOT/$path" "$PLUGIN_DIR/$path"
  fi
done

python3 - "$PLUGIN_DIR" "$VERSION" "$MANIFEST" <<'PY'
import hashlib
import json
import pathlib
import sys

plugin = pathlib.Path(sys.argv[1])
version = sys.argv[2]
manifest_path = pathlib.Path(sys.argv[3])
entries = []
for path in sorted(p for p in plugin.rglob('*') if p.is_file()):
    data = path.read_bytes()
    entries.append({
        'path': path.relative_to(plugin).as_posix(),
        'bytes': len(data),
        'sha256': hashlib.sha256(data).hexdigest(),
    })
manifest = {
    'schema': 'sauth.package-manifest.v1',
    'plugin': 'Sabri Authentication and Accounts',
    'slug': 'sabri-authentication',
    'package_root': plugin.name,
    'version': version,
    'file_count': len(entries),
    'files': entries,
}
manifest_text = json.dumps(manifest, indent=2, sort_keys=True) + '\n'
manifest_path.write_text(manifest_text, encoding='utf-8')
(plugin / 'PACKAGE-MANIFEST.json').write_text(manifest_text, encoding='utf-8')
PY

find "$PACKAGE_ROOT" -exec touch -h -d "@$SOURCE_DATE_EPOCH" {} +
find "$OUTPUT_DIR" -maxdepth 1 -type f -exec touch -h -d "@$SOURCE_DATE_EPOCH" {} +

cd "$PACKAGE_ROOT"
LC_ALL=C find '02-sabri-authentication' -type f -print | LC_ALL=C sort | zip -X -q -@ "$ARCHIVE"

cd "$OUTPUT_DIR"
sha256sum "$(basename "$ARCHIVE")" "$(basename "$MANIFEST")" > "$CHECKSUMS"
zip -T "$ARCHIVE" >/dev/null

rm -rf "$PACKAGE_ROOT"
printf 'Package: %s\n' "$ARCHIVE"
printf 'Manifest: %s\n' "$MANIFEST"
printf 'Checksums: %s\n' "$CHECKSUMS"
