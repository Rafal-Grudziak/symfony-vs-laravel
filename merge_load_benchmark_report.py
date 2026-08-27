#!/usr/bin/env python3
"""Merge k6 load-benchmark summary JSON files into CSV + Markdown report."""

from __future__ import annotations

import argparse
import csv
import json
import math
from pathlib import Path
from datetime import datetime


def metric_values(data: dict, name: str):
    m = (data.get("metrics") or {}).get(name) or {}
    return m.get("values") or {}


def pick(values: dict, *keys):
    for k in keys:
        if k in values and isinstance(values[k], (int, float)):
            return values[k]
    return None


def fmt(v, digits=3):
    if v is None:
        return "n/a"
    if isinstance(v, float):
        if math.isnan(v) or math.isinf(v):
            return "n/a"
        return f"{v:.{digits}f}"
    return str(v)


def fmt_pct(v, digits=2):
    if v is None:
        return "n/a"
    return f"{v:.{digits}f}%"


def load_scenario(results_dir: Path, framework: str, vus: int, exit_codes: dict):
    prefix = f"{framework}_{vus}"
    summary_path = results_dir / f"{prefix}_summary.json"
    row = {
        "framework": framework,
        "vus": vus,
        "requests": None,
        "requests_per_second": None,
        "avg_response_time_ms": None,
        "median_response_time_ms": None,
        "p90_response_time_ms": None,
        "p95_response_time_ms": None,
        "min_response_time_ms": None,
        "max_response_time_ms": None,
        "error_rate_percent": None,
        "failed_requests": None,
        "successful_requests": None,
        "validation_failures": None,
        "iterations": None,
        "data_received_bytes": None,
        "data_sent_bytes": None,
        "test_exit_code": exit_codes.get(prefix, ""),
        "_missing": False,
    }
    if not summary_path.is_file():
        row["_missing"] = True
        return row

    with summary_path.open(encoding="utf-8") as f:
        data = json.load(f)

    http_dur = metric_values(data, "http_req_duration")
    http_reqs = metric_values(data, "http_reqs")
    http_fail = metric_values(data, "http_req_failed")
    recv = metric_values(data, "data_received")
    sent = metric_values(data, "data_sent")
    iters = metric_values(data, "iterations")
    ok_c = metric_values(data, "successful_responses")

    val_fail = metric_values(data, "validation_failures")
    if not val_fail:
        val_fail = metric_values(data, "failed_responses")

    req_count = pick(http_reqs, "count")
    fail_rate = pick(http_fail, "rate")

    http_failed_count = None
    if req_count is not None and fail_rate is not None:
        http_failed_count = int(round(fail_rate * req_count))

    success_custom = pick(ok_c, "count")
    validation_count = pick(val_fail, "count")
    if validation_count is None:
        validation_count = 0

    row.update(
        {
            "requests": req_count,
            "requests_per_second": pick(http_reqs, "rate"),
            "avg_response_time_ms": pick(http_dur, "avg"),
            "median_response_time_ms": pick(http_dur, "med"),
            "p90_response_time_ms": pick(http_dur, "p(90)"),
            "p95_response_time_ms": pick(http_dur, "p(95)"),
            "min_response_time_ms": pick(http_dur, "min"),
            "max_response_time_ms": pick(http_dur, "max"),
            "error_rate_percent": (fail_rate * 100.0) if fail_rate is not None else None,
            "failed_requests": http_failed_count,
            "successful_requests": success_custom,
            "validation_failures": validation_count,
            "iterations": pick(iters, "count"),
            "data_received_bytes": pick(recv, "count"),
            "data_sent_bytes": pick(sent, "count"),
        }
    )
    return row


def compare(laravel: dict, symfony: dict):
    la = laravel.get("avg_response_time_ms")
    sa = symfony.get("avg_response_time_ms")
    lr = laravel.get("requests_per_second")
    sr = symfony.get("requests_per_second")
    le = laravel.get("error_rate_percent")
    se = symfony.get("error_rate_percent")

    out = {
        "faster": None,
        "diff_pct": None,
        "ratio_l_over_s": None,
        "rps_diff_l_minus_s": None,
        "throughput_diff_pct": None,
        "error_diff_l_minus_s": None,
    }
    if la is not None and sa is not None:
        if la < sa:
            out["faster"] = "laravel"
            out["diff_pct"] = ((sa - la) / sa * 100.0) if sa else None
        elif sa < la:
            out["faster"] = "symfony"
            out["diff_pct"] = ((la - sa) / la * 100.0) if la else None
        else:
            out["faster"] = "tie"
            out["diff_pct"] = 0.0
        if sa:
            out["ratio_l_over_s"] = la / sa
    if lr is not None and sr is not None:
        out["rps_diff_l_minus_s"] = lr - sr
        if sr:
            out["throughput_diff_pct"] = (lr - sr) / sr * 100.0
    if le is not None and se is not None:
        out["error_diff_l_minus_s"] = le - se
    return out


def md_table(headers, rows):
    lines = [
        "| " + " | ".join(headers) + " |",
        "| " + " | ".join("---" for _ in headers) + " |",
    ]
    for r in rows:
        lines.append("| " + " | ".join(str(c) for c in r) + " |")
    return "\n".join(lines)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--results-dir", required=True)
    ap.add_argument("--summary-csv", required=True)
    ap.add_argument("--report-md", required=True)
    ap.add_argument("--started-at", default="")
    ap.add_argument("--laravel-base", default="")
    ap.add_argument("--symfony-base", default="")
    ap.add_argument("--exit-log", default="")
    ap.add_argument("--vus-levels", default="10 50 100")
    ap.add_argument("--duration", default="30s")
    ap.add_argument("--sleep", default="0.2")
    ap.add_argument("--warmup-requests", default="10")

    ap.add_argument("--ramp-up", default="")
    ap.add_argument("--steady", default="")
    ap.add_argument("--ramp-down", default="")
    args = ap.parse_args()

    results_dir = Path(args.results_dir)
    vus_levels = [int(x) for x in args.vus_levels.split() if x.strip()]

    exit_codes = {}
    if args.exit_log and Path(args.exit_log).is_file():
        for line in Path(args.exit_log).read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if "exit_code=" in line:
                prefix, _, code = line.partition(" exit_code=")
                exit_codes[prefix.strip()] = code.strip()

    rows = []
    by = {}
    for vus in vus_levels:
        for fw in ("laravel", "symfony"):
            row = load_scenario(results_dir, fw, vus, exit_codes)
            rows.append(row)
            by[(fw, vus)] = row

    fieldnames = [
        "framework",
        "vus",
        "requests",
        "requests_per_second",
        "avg_response_time_ms",
        "median_response_time_ms",
        "p90_response_time_ms",
        "p95_response_time_ms",
        "min_response_time_ms",
        "max_response_time_ms",
        "error_rate_percent",
        "failed_requests",
        "successful_requests",
        "validation_failures",
        "iterations",
        "data_received_bytes",
        "data_sent_bytes",
        "test_exit_code",
    ]

    summary_csv = Path(args.summary_csv)
    with summary_csv.open("w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=fieldnames, quoting=csv.QUOTE_MINIMAL)
        w.writeheader()
        for row in rows:
            out = {k: row.get(k, "") for k in fieldnames}
            for k, v in list(out.items()):
                if isinstance(v, float):
                    out[k] = f"{v:.6f}".rstrip("0").rstrip(".")
                elif v is None:
                    out[k] = ""
            w.writerow(out)

    finished = datetime.now().astimezone().strftime("%Y-%m-%d %H:%M:%S %Z")
    md = []
    md.append("# Load / stress benchmark report (k6)")
    md.append("")
    md.append(f"- **Started:** {args.started_at or 'n/a'}")
    md.append(f"- **Finished:** {finished}")
    md.append(f"- **Laravel:** `{args.laravel_base}`")
    md.append(f"- **Symfony:** `{args.symfony_base}`")
    md.append(
        f"- **Endpoint:** `GET /api/tasks?per_page=100&with=project,comments,tags`"
    )
    md.append(f"- **Executor:** `constant-vus`")
    md.append(
        f"- **Measured duration:** {args.duration} "
        f"(statistics cover only this steady window; no ramp-up/ramp-down)"
    )
    md.append(
        f"- **Warm-up:** {args.warmup_requests} HTTP requests outside k6 "
        f"(not included in metrics)"
    )
    md.append(f"- **VU sleep between requests:** {args.sleep}s")
    md.append("")
    md.append(
        "Wartości pochodzą ze standardowych metryk k6 (`http_req_duration`, `http_reqs`, "
        "`http_req_failed`, itd.) zebrane wyłącznie w oknie `constant-vus`. "
        "Szybsza aplikacja przy tej samej liczbie VU zwykle wykonuje więcej żądań — "
        "traktuj `requests_per_second` jako miarę przepustowości."
    )
    md.append("")
    md.append(
        "**HTTP error rate / HTTP failed requests** = `http_req_failed` "
        "(błędy transportowe / status inny niż sukces). "
        "**Validation failures** = własne metryki (`validation_failures`) dla odpowiedzi "
        "HTTP 200 z niepoprawnym lub pustym JSON — nie są wliczane do HTTP error rate."
    )
    md.append("")

    for vus in vus_levels:
        L = by[("laravel", vus)]
        S = by[("symfony", vus)]
        C = compare(L, S)
        md.append(f"## {vus} VU")
        md.append("")
        if L.get("_missing") or S.get("_missing"):
            missing = []
            if L.get("_missing"):
                missing.append("laravel")
            if S.get("_missing"):
                missing.append("symfony")
            md.append(f"_Brak pliku summary dla: {', '.join(missing)}_")
            md.append("")
        body = [
            ["Avg response time (ms)", fmt(L["avg_response_time_ms"]), fmt(S["avg_response_time_ms"])],
            ["Median response time (ms)", fmt(L["median_response_time_ms"]), fmt(S["median_response_time_ms"])],
            ["P90 response time (ms)", fmt(L["p90_response_time_ms"]), fmt(S["p90_response_time_ms"])],
            ["P95 response time (ms)", fmt(L["p95_response_time_ms"]), fmt(S["p95_response_time_ms"])],
            ["Requests per second", fmt(L["requests_per_second"]), fmt(S["requests_per_second"])],
            ["Total requests", fmt(L["requests"], 0), fmt(S["requests"], 0)],
            ["HTTP error rate (%)", fmt_pct(L["error_rate_percent"]), fmt_pct(S["error_rate_percent"])],
            ["HTTP failed requests", fmt(L["failed_requests"], 0), fmt(S["failed_requests"], 0)],
            ["Validation failures", fmt(L["validation_failures"], 0), fmt(S["validation_failures"], 0)],
        ]
        md.append(md_table(["Metric", "Laravel", "Symfony"], body))
        md.append("")
        md.append(
            f"- Faster avg response: **{C['faster'] or 'n/a'}**"
            + (f" ({fmt(C['diff_pct'], 1)}% lower mean)" if C["diff_pct"] is not None else "")
        )
        md.append(
            f"- Mean time ratio (Laravel / Symfony): **{fmt(C['ratio_l_over_s'])}**"
        )
        md.append(
            f"- Throughput difference (Laravel − Symfony req/s): **{fmt(C['rps_diff_l_minus_s'])}**"
        )
        md.append(
            f"- Throughput difference vs Symfony (%): **{fmt(C['throughput_diff_pct'], 1)}**"
        )
        md.append(
            f"- HTTP error rate difference (Laravel − Symfony, pp): **{fmt(C['error_diff_l_minus_s'])}**"
        )
        md.append("")

    md.append("## Summary")
    md.append("")
    sum_headers = [
        "VU",
        "Laravel avg",
        "Symfony avg",
        "Laravel P95",
        "Symfony P95",
        "Laravel req/s",
        "Symfony req/s",
        "Laravel HTTP errors",
        "Symfony HTTP errors",
    ]
    sum_body = []
    for vus in vus_levels:
        L = by[("laravel", vus)]
        S = by[("symfony", vus)]
        sum_body.append(
            [
                vus,
                fmt(L["avg_response_time_ms"]),
                fmt(S["avg_response_time_ms"]),
                fmt(L["p95_response_time_ms"]),
                fmt(S["p95_response_time_ms"]),
                fmt(L["requests_per_second"]),
                fmt(S["requests_per_second"]),
                fmt_pct(L["error_rate_percent"]),
                fmt_pct(S["error_rate_percent"]),
            ]
        )
    md.append(md_table(sum_headers, sum_body))
    md.append("")
    md.append("## Artifacts")
    md.append("")
    md.append(f"- Results directory: `{results_dir}`")
    md.append(f"- Summary CSV: `{summary_csv}`")
    md.append(
        f"- Per-scenario: `{{framework}}_{{vus}}_summary.json`, `_raw.json`, `_report.txt`, `_metrics.csv`"
    )
    md.append("")

    Path(args.report_md).write_text("\n".join(md), encoding="utf-8")
    print(f"Wrote {summary_csv}")
    print(f"Wrote {args.report_md}")


if __name__ == "__main__":
    main()
