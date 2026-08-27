#!/usr/bin/env bash

set -euo pipefail

LARAVEL_BASE="${LARAVEL_BASE:-http://localhost:8080}"
SYMFONY_BASE="${SYMFONY_BASE:-http://localhost:8081}"
WARMUP_COUNT="${WARMUP_COUNT:-5}"
MEASURE_COUNT="${MEASURE_COUNT:-30}"
PAUSE_BETWEEN_FRAMEWORKS_SEC="${PAUSE_BETWEEN_FRAMEWORKS_SEC:-1}"
TASK_ID="${TASK_ID:-1}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAW_CSV="${SCRIPT_DIR}/read_benchmark_results.csv"
SUMMARY_CSV="${SCRIPT_DIR}/read_benchmark_summary.csv"
REPORT_MD="${SCRIPT_DIR}/read_benchmark_report.md"
RUN_META="${SCRIPT_DIR}/.read_benchmark_run_meta.txt"

HEADER_METRICS="X-Benchmark-Metrics: 1"
HEADER_ACCEPT="Accept: application/json"

if ! command -v curl >/dev/null 2>&1; then
  echo "Error: curl is required." >&2
  exit 1
fi
if ! command -v python3 >/dev/null 2>&1; then
  echo "Error: python3 is required." >&2
  exit 1
fi

SCENARIOS=(
  "list_per_page_15|/api/tasks?per_page=15|Lista zadań z paginacją, bez relacji (per_page=15)"
  "list_per_page_100|/api/tasks?per_page=100|Lista zadań z większą paginacją, bez relacji (per_page=100)"
  "single_task|/api/tasks/${TASK_ID}|Pojedyncze zadanie, bez relacji (id=${TASK_ID})"
  "list_with_project|/api/tasks?per_page=15&with=project|Lista zadań z relacją project (per_page=15)"
  "list_with_comments|/api/tasks?per_page=15&with=comments|Lista 15 zadań wraz z komentarzami"
  "list_with_tags|/api/tasks?per_page=15&with=tags|Lista 15 zadań wraz ze znacznikami"
  "list_with_all|/api/tasks?per_page=15&with=project,comments,tags|Lista zadań z pełnym zestawem relacji (per_page=15)"
  "list_100_with_all|/api/tasks?per_page=100&with=project,comments,tags|Lista 100 zadań z pełnym zestawem relacji"
  "single_with_all|/api/tasks/${TASK_ID}?with=project,comments,tags|Pojedyncze zadanie z pełnym zestawem relacji (id=${TASK_ID})"
)

header_value() {
  local headers="$1"
  local name="$2"
  printf '%s' "$headers" | python3 -c '
import sys
name = sys.argv[1].lower()
data = sys.stdin.read().splitlines()
for line in data:
    if ":" not in line:
        continue
    k, _, v = line.partition(":")
    if k.strip().lower() == name:
        print(v.strip().rstrip("\r"))
        break
' "$name"
}

json_ok() {
  local file="$1"
  python3 -c '
import json, sys
path = sys.argv[1]
try:
    with open(path, "rb") as f:
        raw = f.read()
    if not raw:
        sys.exit(1)
    json.loads(raw.decode("utf-8"))
except Exception:
    sys.exit(1)
sys.exit(0)
' "$file"
}


do_request() {
  local url="$1"
  local tmp_body tmp_hdrs
  tmp_body="$(mktemp)"
  tmp_hdrs="$(mktemp)"

  local curl_exit=0
  local http_code size
  http_code="$(curl -sS -D "$tmp_hdrs" -o "$tmp_body" \
    -H "$HEADER_METRICS" \
    -H "$HEADER_ACCEPT" \
    -w '%{http_code} %{size_download}' \
    "$url" 2>/dev/null)" || curl_exit=$?

  local status size_bytes
  if [[ "$curl_exit" -ne 0 ]]; then
    rm -f "$tmp_body" "$tmp_hdrs"
    printf '000|||0|0|curl_failed\n'
    return 0
  fi

  status="$(printf '%s' "$http_code" | awk '{print $1}')"
  size_bytes="$(printf '%s' "$http_code" | awk '{print $2}')"
  size_bytes="${size_bytes%%.*}"
  size_bytes="${size_bytes:-0}"

  local headers
  headers="$(cat "$tmp_hdrs")"
  local rt qc
  rt="$(header_value "$headers" "X-Response-Time-Ms")"
  qc="$(header_value "$headers" "X-Query-Count")"

  local valid=1
  local reason=""

  if [[ "$status" != "200" ]]; then
    valid=0
    if [[ "$status" == "404" ]]; then
      reason="http_404_not_found"
    else
      reason="http_${status}"
    fi
  fi
  if [[ -z "$rt" || -z "$qc" ]]; then
    valid=0
    reason="${reason:+$reason;}missing_benchmark_headers"
  fi
  if ! json_ok "$tmp_body"; then
    valid=0
    reason="${reason:+$reason;}invalid_or_empty_json"
  fi

  rm -f "$tmp_body" "$tmp_hdrs"
  printf '%s|%s|%s|%s|%s|%s\n' "$status" "$rt" "$qc" "$size_bytes" "$valid" "${reason:-ok}"
}

check_availability() {
  local name="$1"
  local base="$2"
  local url="${base}/api/tasks?per_page=1"
  echo -n "Checking ${name} (${base})... "
  local code
  code="$(curl -sS -o /dev/null -w '%{http_code}' -H "$HEADER_ACCEPT" "$url" 2>/dev/null || true)"
  if [[ "$code" != "200" ]]; then
    echo "FAIL (HTTP ${code:-unreachable})"
    echo "Error: ${name} is not available at ${base}" >&2
    exit 1
  fi
  echo "OK (HTTP ${code})"
}

warmup() {
  local name="$1"
  local url="$2"
  local i result valid reason
  echo "  Warm-up ${name} (${WARMUP_COUNT})..."
  for ((i = 1; i <= WARMUP_COUNT; i++)); do
    result="$(do_request "$url")"
    IFS='|' read -r _ _ _ _ valid reason <<<"$result"
    if [[ "$valid" != "1" ]]; then
      echo "  Warm-up failed on request ${i}: ${reason}"
      return 1
    fi
  done
  return 0
}

measure_framework() {
  local scenario="$1"
  local framework="$2"
  local url="$3"
  local i result status rt qc size valid reason

  if ! warmup "$framework" "$url"; then
    echo "  ABORT ${framework}/${scenario}: warm-up failed (endpoint/headers/JSON/task missing)"

    printf '%s,%s,0,error,,,,0\n' "$scenario" "$framework" >>"$RAW_CSV"
    return 1
  fi

  echo "  Measuring ${framework} (${MEASURE_COUNT})..."
  for ((i = 1; i <= MEASURE_COUNT; i++)); do
    result="$(do_request "$url")"
    IFS='|' read -r status rt qc size valid reason <<<"$result"

    printf '%s,%s,%d,%s,%s,%s,%s,%s\n' \
      "$scenario" "$framework" "$i" "$status" "$rt" "$qc" "$size" "$valid" >>"$RAW_CSV"

    if [[ "$valid" == "1" ]]; then
      printf '    [%2d/%d] OK  status=%s time=%s ms queries=%s size=%s B\n' \
        "$i" "$MEASURE_COUNT" "$status" "$rt" "$qc" "$size"
    else
      printf '    [%2d/%d] FAIL status=%s reason=%s\n' \
        "$i" "$MEASURE_COUNT" "${status:-?}" "$reason"
      echo "  ABORT ${framework}/${scenario}: invalid response (${reason})"
      return 1
    fi
  done
  return 0
}

echo "=== Read operations benchmark ==="
echo "Laravel:  ${LARAVEL_BASE}"
echo "Symfony:  ${SYMFONY_BASE}"
echo "Warm-up:  ${WARMUP_COUNT}  |  Measurements: ${MEASURE_COUNT}"
echo "Task ID:  ${TASK_ID}"
echo

check_availability "Laravel" "$LARAVEL_BASE"
check_availability "Symfony" "$SYMFONY_BASE"
echo

STARTED_AT="$(date '+%Y-%m-%d %H:%M:%S %Z')"
printf 'started_at=%s\nlaravel_base=%s\nsymfony_base=%s\nwarmup=%s\nmeasure=%s\ntask_id=%s\n' \
  "$STARTED_AT" "$LARAVEL_BASE" "$SYMFONY_BASE" "$WARMUP_COUNT" "$MEASURE_COUNT" "$TASK_ID" >"$RUN_META"

printf 'scenario,framework,iteration,status,response_time_ms,query_count,response_size_bytes,valid\n' >"$RAW_CSV"

for entry in "${SCENARIOS[@]}"; do
  IFS='|' read -r scenario_id path desc <<<"$entry"
  echo "────────────────────────────────────────"
  echo "Scenario: ${scenario_id}"
  echo "  ${desc}"
  echo "  Path: ${path}"

  measure_framework "$scenario_id" "laravel" "${LARAVEL_BASE}${path}" || true
  sleep "$PAUSE_BETWEEN_FRAMEWORKS_SEC"
  measure_framework "$scenario_id" "symfony" "${SYMFONY_BASE}${path}" || true
  sleep "$PAUSE_BETWEEN_FRAMEWORKS_SEC"
  echo
done

echo "=== Computing statistics & reports ==="
python3 - "$RAW_CSV" "$SUMMARY_CSV" "$REPORT_MD" "$RUN_META" <<'PY'
import csv
import math
import sys
from collections import defaultdict
from datetime import datetime

raw_path, summary_path, report_path, meta_path = sys.argv[1:5]

meta = {}
with open(meta_path, encoding="utf-8") as f:
    for line in f:
        line = line.strip()
        if not line or "=" not in line:
            continue
        k, _, v = line.partition("=")
        meta[k] = v

SCENARIO_ORDER = [
    "list_per_page_15",
    "list_per_page_100",
    "single_task",
    "list_with_project",
    "list_with_comments",
    "list_with_tags",
    "list_with_all",
    "list_100_with_all",
    "single_with_all",
]

task_id = meta.get("task_id", "10000")
SCENARIO_DESC = {
    "list_per_page_15": "GET /api/tasks?per_page=15 — lista bez relacji",
    "list_per_page_100": "GET /api/tasks?per_page=100 — lista bez relacji",
    "single_task": f"GET /api/tasks/{task_id} — pojedyncze zadanie bez relacji",
    "list_with_project": "GET /api/tasks?per_page=15&with=project",
    "list_with_comments": "GET /api/tasks?per_page=15&with=comments — Lista 15 zadań wraz z komentarzami",
    "list_with_tags": "GET /api/tasks?per_page=15&with=tags — Lista 15 zadań wraz ze znacznikami",
    "list_with_all": "GET /api/tasks?per_page=15&with=project,comments,tags",
    "list_100_with_all": "GET /api/tasks?per_page=100&with=project,comments,tags",
    "single_with_all": f"GET /api/tasks/{task_id}?with=project,comments,tags",
}

rows = []
with open(raw_path, newline="", encoding="utf-8") as f:
    for row in csv.DictReader(f):
        rows.append(row)

by = defaultdict(list)
for r in rows:
    by[(r["scenario"], r["framework"])].append(r)


def to_float(v):
    try:
        if v is None or v == "":
            return None
        return float(v)
    except ValueError:
        return None


def is_valid_row(r):
    if r.get("valid") != "1":
        return False
    if r.get("status") != "200":
        return False
    if to_float(r.get("response_time_ms")) is None:
        return False
    if to_float(r.get("query_count")) is None:
        return False
    if to_float(r.get("response_size_bytes")) is None:
        return False
    return True


def percentile(sorted_vals, p):
    n = len(sorted_vals)
    if n == 0:
        return None
    if n == 1:
        return sorted_vals[0]
    # nearest-rank
    rank = max(1, math.ceil(p / 100.0 * n))
    return sorted_vals[min(rank - 1, n - 1)]


def median(sorted_vals):
    n = len(sorted_vals)
    if n == 0:
        return None
    mid = n // 2
    if n % 2 == 1:
        return sorted_vals[mid]
    return (sorted_vals[mid - 1] + sorted_vals[mid]) / 2.0


def stddev(vals, mean):
    n = len(vals)
    if n < 2 or mean is None:
        return None if n == 0 else 0.0
    return math.sqrt(sum((x - mean) ** 2 for x in vals) / (n - 1))


def fmt(v, digits=3):
    if v is None:
        return "n/a"
    if isinstance(v, float):
        return f"{v:.{digits}f}"
    return str(v)


def fmt_pct(v):
    if v is None:
        return "n/a"
    return f"{v:.1f}%"


def compute(scenario, framework):
    all_rows = by.get((scenario, framework), [])
    # iteration 0 is abort marker without measurements
    measure_rows = [r for r in all_rows if r.get("iteration", "0") != "0"]
    ok = [r for r in measure_rows if is_valid_row(r)]
    aborted = any(r.get("iteration") == "0" for r in all_rows) or (
        len(measure_rows) > 0 and len(ok) < len(measure_rows)
    )
    # If no measure rows and abort marker → failed
    failed = len(ok) == 0

    times = [float(r["response_time_ms"]) for r in ok]
    queries = [float(r["query_count"]) for r in ok]
    sizes = [float(r["response_size_bytes"]) for r in ok]
    times_s = sorted(times)

    mean_t = sum(times) / len(times) if times else None
    mean_q = sum(queries) / len(queries) if queries else None
    mean_s = sum(sizes) / len(sizes) if sizes else None

    return {
        "ok": len(ok),
        "err": len(measure_rows) - len(ok) if measure_rows else (1 if failed else 0),
        "aborted": aborted or failed,
        "failed": failed,
        "mean_t": mean_t,
        "median_t": median(times_s),
        "min_t": min(times) if times else None,
        "max_t": max(times) if times else None,
        "std_t": stddev(times, mean_t) if times else None,
        "p90_t": percentile(times_s, 90),
        "p95_t": percentile(times_s, 95),
        "mean_q": mean_q,
        "min_q": min(queries) if queries else None,
        "max_q": max(queries) if queries else None,
        "mean_s": mean_s,
    }


stats = {}
for sc in SCENARIO_ORDER:
    stats[sc] = {
        "laravel": compute(sc, "laravel"),
        "symfony": compute(sc, "symfony"),
    }


def compare(sc):
    L = stats[sc]["laravel"]
    S = stats[sc]["symfony"]
    la, sa = L["mean_t"], S["mean_t"]
    lq, sq = L["mean_q"], S["mean_q"]

    faster = None
    diff_pct = None
    ratio = None
    sql_diff = None  # laravel - symfony

    if la is not None and sa is not None:
        if la < sa:
            faster = "laravel"
            diff_pct = (sa - la) / sa * 100.0 if sa else None
        elif sa < la:
            faster = "symfony"
            diff_pct = (la - sa) / la * 100.0 if la else None
        else:
            faster = "tie"
            diff_pct = 0.0
        if sa and sa != 0:
            ratio = la / sa  # laravel/symfony
    if lq is not None and sq is not None:
        sql_diff = lq - sq

    return {
        "faster": faster,
        "diff_pct": diff_pct,
        "ratio_l_over_s": ratio,
        "sql_diff_l_minus_s": sql_diff,
    }


comparisons = {sc: compare(sc) for sc in SCENARIO_ORDER}

# --- Terminal output ---
def print_scenario_table(sc):
    L = stats[sc]["laravel"]
    S = stats[sc]["symfony"]
    C = comparisons[sc]
    col1, coln = 30, 16
    line = "+" + "-" * (col1 + 2) + "+" + "-" * (coln + 2) + "+" + "-" * (coln + 2) + "+"
    print()
    print(f"Scenario: {sc}")
    print(f"  {SCENARIO_DESC.get(sc, sc)}")
    print(line)
    print(f"| {'Metric':<{col1}} | {'Laravel':>{coln}} | {'Symfony':>{coln}} |")
    print(line)
    rows_out = [
        ("Valid measurements", L["ok"], S["ok"], False),
        ("Errors", L["err"], S["err"], False),
        ("Avg response time (ms)", L["mean_t"], S["mean_t"], True),
        ("Median response time (ms)", L["median_t"], S["median_t"], True),
        ("Min response time (ms)", L["min_t"], S["min_t"], True),
        ("Max response time (ms)", L["max_t"], S["max_t"], True),
        ("Stddev response time (ms)", L["std_t"], S["std_t"], True),
        ("P90 response time (ms)", L["p90_t"], S["p90_t"], True),
        ("P95 response time (ms)", L["p95_t"], S["p95_t"], True),
        ("Avg SQL queries", L["mean_q"], S["mean_q"], True),
        ("Min SQL queries", L["min_q"], S["min_q"], True),
        ("Max SQL queries", L["max_q"], S["max_q"], True),
        ("Avg response size (B)", L["mean_s"], S["mean_s"], True),
    ]
    for label, lv, sv, is_f in rows_out:
        ls = fmt(lv) if is_f else str(lv)
        ss = fmt(sv) if is_f else str(sv)
        print(f"| {label:<{col1}} | {ls:>{coln}} | {ss:>{coln}} |")
    print(line)
    if C["faster"] and C["faster"] != "tie":
        print(
            f"  Faster avg: {C['faster']} "
            f"({fmt_pct(C['diff_pct'])} lower mean than the other)"
        )
    elif C["faster"] == "tie":
        print("  Faster avg: tie")
    else:
        print("  Faster avg: n/a (insufficient valid data)")
    if C["sql_diff_l_minus_s"] is not None:
        print(f"  SQL avg difference (Laravel − Symfony): {fmt(C['sql_diff_l_minus_s'])}")
    if C["ratio_l_over_s"] is not None:
        print(f"  Mean time ratio (Laravel / Symfony): {fmt(C['ratio_l_over_s'])}")
    if L["aborted"] or S["aborted"]:
        parts = []
        if L["aborted"]:
            parts.append("laravel aborted/failed")
        if S["aborted"]:
            parts.append("symfony aborted/failed")
        print("  Note: " + "; ".join(parts))


for sc in SCENARIO_ORDER:
    print_scenario_table(sc)

# Summary table terminal
print()
print("=== Summary (all scenarios) ===")
headers = [
    "Scenario",
    "L avg",
    "S avg",
    "L med",
    "S med",
    "L p95",
    "S p95",
    "L SQL",
    "S SQL",
    "L size",
    "S size",
    "Faster",
    "Diff %",
]
# compact widths
widths = [22, 8, 8, 8, 8, 8, 8, 7, 7, 8, 8, 8, 7]


def cell(s, w):
    s = str(s)
    if len(s) > w:
        return s[: w - 1] + "…"
    return s.rjust(w) if s.replace(".", "").replace("-", "").isdigit() or s.endswith("%") or s == "n/a" else s.ljust(w)


print(" | ".join(cell(h, w) for h, w in zip(headers, widths)))
print("-+-".join("-" * w for w in widths))

summary_rows = []
for sc in SCENARIO_ORDER:
    L, S, C = stats[sc]["laravel"], stats[sc]["symfony"], comparisons[sc]
    row = {
        "scenario": sc,
        "laravel_avg_ms": L["mean_t"],
        "symfony_avg_ms": S["mean_t"],
        "laravel_median_ms": L["median_t"],
        "symfony_median_ms": S["median_t"],
        "laravel_p95_ms": L["p95_t"],
        "symfony_p95_ms": S["p95_t"],
        "laravel_avg_queries": L["mean_q"],
        "symfony_avg_queries": S["mean_q"],
        "laravel_avg_size_bytes": L["mean_s"],
        "symfony_avg_size_bytes": S["mean_s"],
        "faster_framework": C["faster"] or "n/a",
        "difference_percent": C["diff_pct"],
    }
    summary_rows.append(row)
    vals = [
        sc,
        fmt(L["mean_t"], 2),
        fmt(S["mean_t"], 2),
        fmt(L["median_t"], 2),
        fmt(S["median_t"], 2),
        fmt(L["p95_t"], 2),
        fmt(S["p95_t"], 2),
        fmt(L["mean_q"], 1),
        fmt(S["mean_q"], 1),
        fmt(L["mean_s"], 0),
        fmt(S["mean_s"], 0),
        C["faster"] or "n/a",
        fmt_pct(C["diff_pct"]) if C["diff_pct"] is not None else "n/a",
    ]
    print(" | ".join(cell(v, w) for v, w in zip(vals, widths)))

# Relation impact comparison (per_page=15)
RELATION_COMPARE = [
    ("comments", "list_with_comments"),
    ("tags", "list_with_tags"),
    ("project", "list_with_project"),
    ("project+comments+tags", "list_with_all"),
]

print()
print("=== Relation comparison (per_page=15) ===")
rel_headers = ["Relacja", "Laravel avg", "Symfony avg", "Laravel SQL", "Symfony SQL"]
rel_widths = [24, 12, 12, 12, 12]
print(" | ".join(cell(h, w) for h, w in zip(rel_headers, rel_widths)))
print("-+-".join("-" * w for w in rel_widths))
relation_rows = []
for label, sc in RELATION_COMPARE:
    L, S = stats[sc]["laravel"], stats[sc]["symfony"]
    relation_rows.append(
        [
            label,
            fmt(L["mean_t"]),
            fmt(S["mean_t"]),
            fmt(L["mean_q"]),
            fmt(S["mean_q"]),
        ]
    )
    print(
        " | ".join(
            cell(v, w)
            for v, w in zip(
                [
                    label,
                    fmt(L["mean_t"], 2),
                    fmt(S["mean_t"], 2),
                    fmt(L["mean_q"], 1),
                    fmt(S["mean_q"], 1),
                ],
                rel_widths,
            )
        )
    )

# --- Summary CSV ---
with open(summary_path, "w", newline="", encoding="utf-8") as f:
    fieldnames = [
        "scenario",
        "laravel_avg_ms",
        "symfony_avg_ms",
        "laravel_median_ms",
        "symfony_median_ms",
        "laravel_p95_ms",
        "symfony_p95_ms",
        "laravel_avg_queries",
        "symfony_avg_queries",
        "laravel_avg_size_bytes",
        "symfony_avg_size_bytes",
        "faster_framework",
        "difference_percent",
    ]
    w = csv.DictWriter(f, fieldnames=fieldnames)
    w.writeheader()
    for row in summary_rows:
        out = {}
        for k in fieldnames:
            v = row[k]
            if isinstance(v, float):
                out[k] = f"{v:.6f}".rstrip("0").rstrip(".") if k != "difference_percent" else f"{v:.4f}"
            elif v is None:
                out[k] = ""
            else:
                out[k] = v
        w.writerow(out)

# --- Observations (facts only) ---
observations = []

faster_counts = {"laravel": 0, "symfony": 0, "tie": 0}
for sc in SCENARIO_ORDER:
    C = comparisons[sc]
    if C["faster"] in faster_counts:
        faster_counts[C["faster"]] += 1
if faster_counts["laravel"] or faster_counts["symfony"]:
    observations.append(
        f"Niższy średni czas odpowiedzi: Laravel w {faster_counts['laravel']} scenariuszach, "
        f"Symfony w {faster_counts['symfony']} scenariuszach"
        + (f", remis w {faster_counts['tie']}" if faster_counts["tie"] else "")
        + "."
    )

for sc in SCENARIO_ORDER:
    L, S = stats[sc]["laravel"], stats[sc]["symfony"]
    if L["mean_q"] is not None and S["mean_q"] is not None:
        if L["mean_q"] < S["mean_q"]:
            observations.append(
                f"{sc}: Laravel wykonał średnio mniej zapytań SQL ({fmt(L['mean_q'], 2)} vs {fmt(S['mean_q'], 2)})."
            )
        elif S["mean_q"] < L["mean_q"]:
            observations.append(
                f"{sc}: Symfony wykonał średnio mniej zapytań SQL ({fmt(S['mean_q'], 2)} vs {fmt(L['mean_q'], 2)})."
            )
        else:
            observations.append(
                f"{sc}: średnia liczba zapytań SQL była taka sama ({fmt(L['mean_q'], 2)})."
            )

# page size effect without relations
for fw in ("laravel", "symfony"):
    a = stats["list_per_page_15"][fw]["mean_t"]
    b = stats["list_per_page_100"][fw]["mean_t"]
    if a is not None and b is not None:
        if b > a:
            observations.append(
                f"{fw}: wzrost per_page z 15 do 100 (bez relacji) zwiększył średni czas "
                f"z {fmt(a)} ms do {fmt(b)} ms."
            )
        elif b < a:
            observations.append(
                f"{fw}: wzrost per_page z 15 do 100 (bez relacji) zmniejszył średni czas "
                f"z {fmt(a)} ms do {fmt(b)} ms."
            )
        else:
            observations.append(
                f"{fw}: średni czas dla per_page=15 i per_page=100 (bez relacji) był taki sam ({fmt(a)} ms)."
            )

# relations effect on query count
for fw in ("laravel", "symfony"):
    base = stats["list_per_page_15"][fw]["mean_q"]
    with_proj = stats["list_with_project"][fw]["mean_q"]
    with_all = stats["list_with_all"][fw]["mean_q"]
    if base is not None and with_proj is not None:
        if with_proj > base:
            observations.append(
                f"{fw}: dołączenie with=project zwiększyło średnią liczbę zapytań SQL "
                f"z {fmt(base, 2)} do {fmt(with_proj, 2)}."
            )
        elif with_proj < base:
            observations.append(
                f"{fw}: dołączenie with=project zmniejszyło średnią liczbę zapytań SQL "
                f"z {fmt(base, 2)} do {fmt(with_proj, 2)}."
            )
    if base is not None and with_all is not None:
        if with_all > base:
            observations.append(
                f"{fw}: dołączenie with=project,comments,tags zwiększyło średnią liczbę zapytań SQL "
                f"z {fmt(base, 2)} do {fmt(with_all, 2)}."
            )
        elif with_all < base:
            observations.append(
                f"{fw}: dołączenie with=project,comments,tags zmniejszyło średnią liczbę zapytań SQL "
                f"z {fmt(base, 2)} do {fmt(with_all, 2)}."
            )

# spread / stddev
for sc in SCENARIO_ORDER:
    for fw in ("laravel", "symfony"):
        st = stats[sc][fw]
        if st["mean_t"] and st["std_t"] is not None and st["mean_t"] > 0:
            cv = st["std_t"] / st["mean_t"]
            if cv >= 0.25:
                observations.append(
                    f"{sc}/{fw}: duży rozrzut czasu odpowiedzi "
                    f"(stddev {fmt(st['std_t'])} ms przy średniej {fmt(st['mean_t'])} ms; CV={fmt(cv, 2)})."
                )

for sc in SCENARIO_ORDER:
    for fw in ("laravel", "symfony"):
        if stats[sc][fw]["failed"] or stats[sc][fw]["aborted"]:
            if stats[sc][fw]["ok"] == 0:
                observations.append(
                    f"{sc}/{fw}: brak poprawnych pomiarów (scenariusz oznaczony jako błędny/przerwany)."
                )
            else:
                observations.append(
                    f"{sc}/{fw}: scenariusz przerwany przed ukończeniem wszystkich pomiarów "
                    f"(poprawnych: {stats[sc][fw]['ok']})."
                )

finished_at = datetime.now().astimezone().strftime("%Y-%m-%d %H:%M:%S %Z")

# --- Markdown report ---
def md_table(headers, rows):
    lines = []
    lines.append("| " + " | ".join(headers) + " |")
    lines.append("| " + " | ".join("---" for _ in headers) + " |")
    for r in rows:
        lines.append("| " + " | ".join(str(c) for c in r) + " |")
    return "\n".join(lines)


md = []
md.append("# Read operations benchmark report")
md.append("")
md.append(f"- **Started:** {meta.get('started_at', 'n/a')}")
md.append(f"- **Finished:** {finished_at}")
md.append(f"- **Laravel:** `{meta.get('laravel_base', '')}`")
md.append(f"- **Symfony:** `{meta.get('symfony_base', '')}`")
md.append(f"- **Warm-up requests per framework/scenario:** {meta.get('warmup', '')}")
md.append(f"- **Measured requests per framework/scenario:** {meta.get('measure', '')}")
md.append(f"- **Task id used in single-resource scenarios:** {meta.get('task_id', '10000')}")
md.append("")
md.append("## Scenarios")
md.append("")

for sc in SCENARIO_ORDER:
    L, S, C = stats[sc]["laravel"], stats[sc]["symfony"], comparisons[sc]
    md.append(f"### {sc}")
    md.append("")
    md.append(SCENARIO_DESC.get(sc, sc))
    md.append("")
    headers = ["Metric", "Laravel", "Symfony"]
    body = [
        ["Valid measurements", L["ok"], S["ok"]],
        ["Errors", L["err"], S["err"]],
        ["Avg response time (ms)", fmt(L["mean_t"]), fmt(S["mean_t"])],
        ["Median response time (ms)", fmt(L["median_t"]), fmt(S["median_t"])],
        ["Min response time (ms)", fmt(L["min_t"]), fmt(S["min_t"])],
        ["Max response time (ms)", fmt(L["max_t"]), fmt(S["max_t"])],
        ["Stddev response time (ms)", fmt(L["std_t"]), fmt(S["std_t"])],
        ["P90 response time (ms)", fmt(L["p90_t"]), fmt(S["p90_t"])],
        ["P95 response time (ms)", fmt(L["p95_t"]), fmt(S["p95_t"])],
        ["Avg SQL queries", fmt(L["mean_q"]), fmt(S["mean_q"])],
        ["Min SQL queries", fmt(L["min_q"]), fmt(S["min_q"])],
        ["Max SQL queries", fmt(L["max_q"]), fmt(S["max_q"])],
        ["Avg response size (bytes)", fmt(L["mean_s"], 1), fmt(S["mean_s"], 1)],
    ]
    md.append(md_table(headers, body))
    md.append("")
    md.append(
        f"- Faster framework (lower avg ms): **{C['faster'] or 'n/a'}**"
        + (f" ({fmt_pct(C['diff_pct'])} lower mean)" if C["diff_pct"] is not None else "")
    )
    md.append(
        f"- SQL query avg difference (Laravel − Symfony): **{fmt(C['sql_diff_l_minus_s'])}**"
    )
    md.append(
        f"- Mean response time ratio (Laravel / Symfony): **{fmt(C['ratio_l_over_s'])}**"
    )
    if L["aborted"] or S["aborted"]:
        notes = []
        if L["aborted"]:
            notes.append("Laravel aborted/failed")
        if S["aborted"]:
            notes.append("Symfony aborted/failed")
        md.append(f"- Note: {'; '.join(notes)}")
    md.append("")

md.append("## Summary")
md.append("")
sum_headers = [
    "Scenario",
    "Laravel avg ms",
    "Symfony avg ms",
    "Laravel median ms",
    "Symfony median ms",
    "Laravel p95 ms",
    "Symfony p95 ms",
    "Laravel SQL",
    "Symfony SQL",
    "Laravel avg response size",
    "Symfony avg response size",
    "Faster framework",
    "Difference %",
]
sum_body = []
for row in summary_rows:
    sum_body.append(
        [
            row["scenario"],
            fmt(row["laravel_avg_ms"]),
            fmt(row["symfony_avg_ms"]),
            fmt(row["laravel_median_ms"]),
            fmt(row["symfony_median_ms"]),
            fmt(row["laravel_p95_ms"]),
            fmt(row["symfony_p95_ms"]),
            fmt(row["laravel_avg_queries"]),
            fmt(row["symfony_avg_queries"]),
            fmt(row["laravel_avg_size_bytes"], 1),
            fmt(row["symfony_avg_size_bytes"], 1),
            row["faster_framework"],
            fmt_pct(row["difference_percent"]) if row["difference_percent"] is not None else "n/a",
        ]
    )
md.append(md_table(sum_headers, sum_body))
md.append("")
md.append("## Automatic observations")
md.append("")
md.append("Obserwacje oparte wyłącznie na zmierzonych wartościach (bez spekulacji o przyczynach):")
md.append("")
if observations:
    for o in observations:
        md.append(f"- {o}")
else:
    md.append("- Brak wystarczających poprawnych danych do automatycznych obserwacji.")
md.append("")
md.append("## Relation comparison")
md.append("")
md.append(
    "Zestawienie wpływu poszczególnych relacji na średni czas odpowiedzi oraz średnią liczbę zapytań SQL "
    "(scenariusze `per_page=15`). Wyłącznie dane pomiarowe:"
)
md.append("")
md.append(
    md_table(
        ["Relacja", "Laravel avg", "Symfony avg", "Laravel SQL", "Symfony SQL"],
        relation_rows,
    )
)
md.append("")
md.append("## Artifacts")
md.append("")
md.append(f"- Raw results: `{raw_path}`")
md.append(f"- Summary CSV: `{summary_path}`")
md.append(f"- This report: `{report_path}`")
md.append("")

with open(report_path, "w", encoding="utf-8") as f:
    f.write("\n".join(md))

print()
print(f"Raw CSV:     {raw_path}")
print(f"Summary CSV: {summary_path}")
print(f"Report MD:   {report_path}")
PY

rm -f "$RUN_META"
echo
echo "Done."
