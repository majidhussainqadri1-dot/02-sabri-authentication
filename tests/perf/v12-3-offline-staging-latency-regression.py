#!/usr/bin/env python3
"""File02 V12.3 P08/T24 offline analyzer regression; no network or real site."""
import csv
import importlib.util
import json
import pathlib
import tempfile

ROOT = pathlib.Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "tools/file02-staging-latency-analyzer.py"
spec = importlib.util.spec_from_file_location("file02_staging_latency", SCRIPT)
module = importlib.util.module_from_spec(spec)
assert spec and spec.loader
spec.loader.exec_module(module)

FIELDS = ("timestamp_utc", "route", "device_class", "network_profile", "cache_state", "http_status", "elapsed_ms")


def must(value, message):
    if not value:
        raise SystemExit("FAIL staging latency analyzer: " + message)


def write_csv(path, data):
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDS)
        writer.writeheader()
        writer.writerows(data)


def sample(i, route="account_safety", status="200"):
    return {
        "timestamp_utc": "2026-11-14T10:00:00Z",
        "route": route, "device_class": "mobile_low", "network_profile": "slow4g",
        "cache_state": "cold", "http_status": status, "elapsed_ms": str(80 + i),
    }


with tempfile.TemporaryDirectory(prefix="file02-lab-metrics-") as d:
    root = pathlib.Path(d)
    f = root / "clean.csv"
    write_csv(f, [sample(i) for i in range(120)])
    clean = module.analyze(f)
    must(clean["production_acceptance"] == "NOT_VERIFIED", "source cannot accept production")
    must(clean["field_core_web_vitals"] == "NOT_MEASURED", "cannot promote lab to 28-day CrUX")
    must(len(clean["profiles"]) == 1, "profile grouping")
    p = clean["profiles"][0]
    must(p["successful_samples"] == 120 and p["http_failure_count"] == 0, "sample count")
    must(p["lab_verdict"] == "no_approved_budget", "must not invent budget")
    must(p["p75_ms"] is not None and p["p95_ms"] is not None and p["p95_ms"] > p["p75_ms"], "quantiles")
    approved = module.analyze(f, budget={"account_safety": 500.0})
    must(approved["profiles"][0]["lab_verdict"] == "within_approved_lab_budget", "explicit lab budget")
    failed_budget = module.analyze(f, budget={"account_safety": 10.0})
    must(failed_budget["profiles"][0]["lab_verdict"] == "exceeds_approved_lab_budget", "budget overrun")
    low = root / "low.csv"
    write_csv(low, [sample(i) for i in range(3)])
    must(module.analyze(low)["profiles"][0]["lab_verdict"] == "insufficient_sample", "low sample must never pass")
    must(module.analyze(low)["profiles"][0]["p95_ms"] is None, "low sample hides unsupported p95")
    failing = root / "failed.csv"
    write_csv(failing, [sample(i) for i in range(120)] + [sample(121, status="500")])
    must(module.analyze(failing, budget={"account_safety": 500})["profiles"][0]["lab_verdict"] == "http_failures_need_review", "HTTP failures block green")
    unsafe = root / "unsafe.csv"
    write_csv(unsafe, [sample(i, route="/account/safety/?user=123") for i in range(120)])
    try:
        module.analyze(unsafe)
        raise SystemExit("FAIL staging latency analyzer: URL/PII label was accepted")
    except ValueError:
        pass
    budget_file = root / "budget.json"
    budget_file.write_text(json.dumps({
        "schema": "file02.v12.3.staging-approved-budgets.v1",
        "approved": False,
        "approval_owner": "file24",
        "route_p95_ms": {"account_safety": 500}
    }), encoding="utf-8")
    try:
        module.load_budget(budget_file)
        raise SystemExit("FAIL staging latency analyzer: unapproved budget accepted")
    except ValueError:
        pass
    budget_file.write_text(json.dumps({
        "schema": "file02.v12.3.staging-approved-budgets.v1",
        "approved": True,
        "approval_owner": "file24",
        "route_p95_ms": {"account_safety": 500}
    }), encoding="utf-8")
    must(module.load_budget(budget_file)["account_safety"] == 500, "explicit approved budget parse")
print("PASS File02 V12.3 staging offline p75/p95 tool, no invented field/live/budget PASS")
