#!/usr/bin/env python3
"""V12.3 File02 NFR004: privacy-minimized, offline staging latency analysis.

NO network, no raw URL/IP/user ID/patient data; no production PASS claims.
CSV columns: timestamp_utc,route,device_class,network_profile,cache_state,
http_status,elapsed_ms. Explicit per-route budget approval is mandatory before
even a *lab budget* evaluation. Percentiles are within exact profile groups.
"""
from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import math
import pathlib
import re
import sys
from collections import defaultdict

SAFE_LABEL = re.compile(r"^[a-z][a-z0-9_]{0,63}$", re.ASCII)
FIELDS = ("timestamp_utc", "route", "device_class", "network_profile", "cache_state", "http_status", "elapsed_ms")
MAX_BYTES = 16 * 1024 * 1024
MAX_ROWS = 100000


def valid_label(value: str) -> bool:
    return isinstance(value, str) and bool(SAFE_LABEL.fullmatch(value))


def parse_utc(value: str) -> str:
    if not isinstance(value, str) or not value.endswith("Z"):
        raise ValueError("timestamp must be an ISO-8601 UTC Z value")
    parsed = dt.datetime.fromisoformat(value.replace("Z", "+00:00"))
    if parsed.utcoffset() != dt.timedelta(0):
        raise ValueError("non-UTC timestamp")
    return parsed.isoformat().replace("+00:00", "Z")


def percentile(values: list[float], fraction: float) -> float:
    """R7 / linear-interpolated sample percentile, not CrUX field percentile."""
    if not values:
        raise ValueError("percentile needs non-empty sample")
    xs = sorted(values)
    position = (len(xs) - 1) * fraction
    low = math.floor(position)
    high = math.ceil(position)
    return round(xs[low] + (position - low) * (xs[high] - xs[low]), 3)


def load_budget(path: pathlib.Path | None) -> dict:
    if path is None:
        return {}
    if path.stat().st_size > 65536:
        raise ValueError("budget file too large")
    obj = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(obj, dict) or obj.get("schema") != "file02.v12.3.staging-approved-budgets.v1":
        raise ValueError("invalid budget schema")
    if obj.get("approved") is not True or not valid_label(obj.get("approval_owner", "")):
        raise ValueError("missing explicit staging budget approval owner")
    result = obj.get("route_p95_ms")
    if not isinstance(result, dict) or not result:
        raise ValueError("missing positive route budgets")
    for route, value in result.items():
        if not valid_label(route) or not isinstance(value, (int, float)) or isinstance(value, bool) or not math.isfinite(value) or value <= 0:
            raise ValueError("invalid approved route p95 budget")
    return result


def analyze(csv_path: pathlib.Path, min_samples: int = 100, budget: dict | None = None) -> dict:
    if csv_path.stat().st_size > MAX_BYTES:
        raise ValueError("CSV exceeds bounded size")
    if min_samples < 3 or min_samples > 100000:
        raise ValueError("invalid minimum sample policy")
    groups: dict[tuple[str, str, str, str], list[tuple[int, float, str]]] = defaultdict(list)
    total = 0
    with csv_path.open("r", encoding="utf-8", newline="") as handle:
        reader = csv.DictReader(handle)
        if not reader.fieldnames or tuple(reader.fieldnames) != FIELDS:
            raise ValueError("CSV must contain exactly the privacy-safe ordered schema")
        for row in reader:
            total += 1
            if total > MAX_ROWS:
                raise ValueError("too many rows")
            if row is None or any(key not in row for key in FIELDS):
                raise ValueError("missing required field")
            for k in ("route", "device_class", "network_profile", "cache_state"):
                if not valid_label(row[k]):
                    raise ValueError("unsafe or identifying label: " + k)
            if row["cache_state"] not in ("cold", "warm"):
                raise ValueError("unsupported cache state")
            timestamp = parse_utc(row["timestamp_utc"])
            if not re.fullmatch(r"[1-5][0-9]{2}", row["http_status"]):
                raise ValueError("invalid HTTP status")
            http_status = int(row["http_status"])
            elapsed = float(row["elapsed_ms"])
            if not math.isfinite(elapsed) or not (0 < elapsed <= 300000):
                raise ValueError("invalid latency")
            key = (row["route"], row["device_class"], row["network_profile"], row["cache_state"])
            groups[key].append((http_status, elapsed, timestamp))
    if not total:
        raise ValueError("empty measurement file")
    out = []
    for key, records in sorted(groups.items()):
        good = [lat for status, lat, _ in records if 200 <= status < 400]
        failures = len(records) - len(good)
        adequate = len(good) >= min_samples
        b = (budget or {}).get(key[0])
        p75 = percentile(good, .75) if good else None
        p95 = percentile(good, .95) if good else None
        if not adequate:
            verdict = "insufficient_sample"
        elif failures:
            verdict = "http_failures_need_review"
        elif b is None:
            verdict = "no_approved_budget"
        elif p95 is not None and p95 <= b:
            verdict = "within_approved_lab_budget"
        else:
            verdict = "exceeds_approved_lab_budget"
        out.append({
            "route": key[0], "device_class": key[1], "network_profile": key[2], "cache_state": key[3],
            "total_samples": len(records), "successful_samples": len(good), "http_failure_count": failures,
            "p75_ms": p75 if adequate else None, "p95_ms": p95 if adequate else None,
            "budget_p95_ms": b, "lab_verdict": verdict,
            "time_window_utc": [min(ts for _, _, ts in records), max(ts for _, _, ts in records)],
        })
    return {
        "schema": "file02.v12.3.staging-latency-report.v1",
        "source": "operator-supplied-disposable-staging-csv-only",
        "method": "R7 linear interpolated sample quantiles over successful HTTP responses",
        "no_pii_fields": True,
        "field_core_web_vitals": "NOT_MEASURED",
        "production_acceptance": "NOT_VERIFIED",
        "min_success_samples_per_exact_group": min_samples,
        "total_csv_rows": total,
        "profiles": out,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("csv_file", type=pathlib.Path)
    parser.add_argument("--approved-budget", type=pathlib.Path, default=None)
    parser.add_argument("--min-samples", type=int, default=100)
    args = parser.parse_args()
    try:
        result = analyze(args.csv_file, args.min_samples, load_budget(args.approved_budget))
    except (ValueError, OSError, UnicodeError, json.JSONDecodeError) as exc:
        print("ERROR: invalid staging measurement input: " + str(exc), file=sys.stderr)
        return 2
    print(json.dumps(result, indent=2, ensure_ascii=False, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
