#!/usr/bin/env python3
import json
import pathlib
import sys

ROOT = pathlib.Path('.').resolve()


def safe_path(value: str) -> pathlib.Path:
    rel = pathlib.PurePosixPath(value)
    if rel.is_absolute() or '..' in rel.parts:
        raise SystemExit(f'unsafe path: {value}')
    path = (ROOT / pathlib.Path(*rel.parts)).resolve()
    if ROOT not in path.parents and path != ROOT:
        raise SystemExit(f'path escapes repository: {value}')
    return path


def main() -> None:
    if len(sys.argv) != 2:
        raise SystemExit('usage: apply-round-ledger.py review-evidence/RNNN-ledger.json')
    ledger_path = safe_path(sys.argv[1])
    data = json.loads(ledger_path.read_text(encoding='utf-8'))
    round_name = str(data.get('round', ''))
    if not round_name.startswith('R') or not round_name[1:].isdigit():
        raise SystemExit('invalid round identity')

    for item in data.get('replacements', []):
        path = safe_path(str(item['path']))
        old = str(item['old'])
        new = str(item['new'])
        text = path.read_text(encoding='utf-8')
        actual = text.count(old)
        if item.get('replace_all'):
            minimum = int(item.get('min_count', 1))
            maximum = int(item.get('max_count', actual))
            if actual < minimum or actual > maximum:
                raise SystemExit(f'{round_name}: {path.relative_to(ROOT)} replace-all count {actual} outside [{minimum},{maximum}]')
            path.write_text(text.replace(old, new), encoding='utf-8')
            continue
        expected = int(item.get('count', 1))
        if actual != expected:
            raise SystemExit(f'{round_name}: {path.relative_to(ROOT)} expected {expected} exact old fragments, found {actual}')
        path.write_text(text.replace(old, new, expected), encoding='utf-8')

    for item in data.get('creates', []):
        path = safe_path(str(item['path']))
        if path.exists():
            raise SystemExit(f'{round_name}: create target already exists: {path.relative_to(ROOT)}')
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(str(item.get('content', '')), encoding='utf-8')

    for item in data.get('deletes', []):
        path = safe_path(str(item['path']))
        if not path.exists():
            raise SystemExit(f'{round_name}: delete target missing: {path.relative_to(ROOT)}')
        if path.is_dir():
            raise SystemExit(f'{round_name}: refusing directory delete: {path.relative_to(ROOT)}')
        path.unlink()

    print(f'{round_name} frozen defect ledger applied')


if __name__ == '__main__':
    main()
