#!/usr/bin/env bash

set -euo pipefail

LARAVEL_BASE="${LARAVEL_BASE:-http://localhost:8080}"
SYMFONY_BASE="${SYMFONY_BASE:-http://localhost:8081}"
WARMUP_COUNT="${WARMUP_COUNT:-5}"
MEASURE_COUNT="${MEASURE_COUNT:-30}"
PAUSE_BETWEEN_FRAMEWORKS_SEC="${PAUSE_BETWEEN_FRAMEWORKS_SEC:-1}"
TASK_ID="${TASK_ID:-1}"
CURL_TIMEOUT_SEC="${CURL_TIMEOUT_SEC:-30}"
PROJECT_ID="${PROJECT_ID:-1}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAW_CSV="${SCRIPT_DIR}/orm_isolation_benchmark_results.csv"
SUMMARY_CSV="${SCRIPT_DIR}/orm_isolation_benchmark_summary.csv"
REPORT_MD="${SCRIPT_DIR}/orm_isolation_benchmark_report.md"
READ_SUMMARY="${SCRIPT_DIR}/read_benchmark_summary.csv"
READ_SUMMARY_PRE_TIMER="${SCRIPT_DIR}/read_benchmark_summary_pre_timer_fix.csv"
WRITE_SUMMARY="${SCRIPT_DIR}/write_benchmark_summary.csv"
RUN_META="${SCRIPT_DIR}/.orm_isolation_run_meta.txt"

HEADER_METRICS="X-Benchmark-Metrics: 1"
HEADER_ACCEPT="Accept: application/json"
HEADER_CONTENT="Content-Type: application/json"

PREFIX="/api/benchmark/no-orm/tasks"

SCENARIOS=(
  "list_per_page_15|GET|?per_page=15|200|0"
  "list_per_page_100|GET|?per_page=100|200|0"
  "single_task|GET|/{id}|200|0"
  "list_with_project|GET|?per_page=15&with=project|200|0"
  "list_with_comments|GET|?per_page=15&with=comments|200|0"
  "list_with_tags|GET|?per_page=15&with=tags|200|0"
  "list_with_all|GET|?per_page=15&with=project,comments,tags|200|0"
  "create_task|POST||201|1"
  "update_task|PUT|/{id}|200|1"
  "delete_task|DELETE|/{id}|204|0"
)

if ! command -v curl >/dev/null 2>&1; then
  echo "Error: curl is required." >&2
  exit 1
fi
if ! command -v python3 >/dev/null 2>&1; then
  echo "Error: python3 is required." >&2
  exit 1
fi

header_value() {
  local headers="$1"
  local name="$2"
  printf '%s' "$headers" | python3 -c '
import sys
name = sys.argv[1].lower()
for line in sys.stdin.read().splitlines():
    if ":" not in line:
        continue
    k, _, v = line.partition(":")
    if k.strip().lower() == name:
        print(v.strip().rstrip("\r"))
        break
' "$name"
}

build_url() {
  local base="$1"
  local suffix="$2"
  suffix="${suffix//\{id\}/${TASK_ID}}"
  printf '%s%s%s' "$base" "$PREFIX" "$suffix"
}

create_body() {
  python3 -c '
import json, sys, time
print(json.dumps({
    "project_id": int(sys.argv[1]),
    "title": f"NoORM create {int(time.time()*1000)}",
    "description": "ORM isolation create",
    "status": "todo",
    "priority": "medium",
}))
' "$PROJECT_ID"
}

update_body() {
  python3 -c '
import json, sys
print(json.dumps({
    "project_id": int(sys.argv[1]),
    "title": "NoORM updated title",
    "description": "ORM isolation update",
    "status": "done",
    "priority": "high",
}))
' "$PROJECT_ID"
}

do_request() {
  local method="$1"
  local url="$2"
  local expected="$3"
  local body="${4:-}"

  local tmp_body tmp_hdrs
  tmp_body="$(mktemp)"
  tmp_hdrs="$(mktemp)"

  local args=(-sS -D "$tmp_hdrs" -o "$tmp_body" --max-time "$CURL_TIMEOUT_SEC"
    -X "$method" -H "$HEADER_METRICS" -H "$HEADER_ACCEPT")
  if [[ -n "$body" ]]; then
    args+=(-H "$HEADER_CONTENT" -d "$body")
  fi

  local writeout curl_exit=0
  writeout="$(curl "${args[@]}" -w '%{http_code} %{size_download}' "$url" 2>/dev/null)" || curl_exit=$?

  if [[ "$curl_exit" -ne 0 ]]; then
    rm -f "$tmp_body" "$tmp_hdrs"
    printf '000|||0|0|curl_failed\n'
    return 0
  fi

  local status size_bytes
  status="$(printf '%s' "$writeout" | awk '{print $1}')"
  size_bytes="$(printf '%s' "$writeout" | awk '{print $2}')"
  size_bytes="${size_bytes%%.*}"
  size_bytes="${size_bytes:-0}"

  local headers rt qc
  headers="$(cat "$tmp_hdrs")"
  rt="$(header_value "$headers" "X-Response-Time-Ms")"
  qc="$(header_value "$headers" "X-Query-Count")"

  local valid=1 reason=""

  if [[ "$status" != "$expected" ]]; then
    valid=0
    reason="http_${status}_expected_${expected}"
  fi
  if [[ -z "$rt" || -z "$qc" ]]; then
    valid=0
    reason="${reason:+$reason;}missing_benchmark_headers"
  fi
  if [[ -n "$qc" && "$qc" != "0" ]]; then
    valid=0
    reason="${reason:+$reason;}sql_not_zero_got_${qc}"
  fi
  if [[ "$expected" != "204" ]]; then
    if ! python3 -c 'import json,sys; json.load(open(sys.argv[1],"rb"))' "$tmp_body" 2>/dev/null; then
      valid=0
      reason="${reason:+$reason;}invalid_json"
    fi
  fi

  rm -f "$tmp_body" "$tmp_hdrs"
  printf '%s|%s|%s|%s|%s|%s\n' "$status" "${rt:-}" "${qc:-}" "$size_bytes" "$valid" "${reason:-ok}"
}

check_availability() {
  local name="$1" base="$2"
  local url
  url="$(build_url "$base" "?per_page=15")"
  echo -n "Checking ${name} no-ORM (${url})... "
  local result status valid reason
  result="$(do_request GET "$url" 200)"
  IFS='|' read -r status _ _ _ valid reason <<<"$result"
  if [[ "$valid" != "1" ]]; then
    echo "FAIL (${reason}, HTTP ${status})"
    echo "Error: ${name} no-ORM endpoint not usable" >&2
    exit 1
  fi
  echo "OK"
}

warm_catalog() {
  local framework="$1" base="$2"
  local i url
  echo "  Catalog warm-up ${framework} (POST /warmup + repeated reads)..."
  curl -sS -o /dev/null -X POST -H "$HEADER_ACCEPT" "${base}/api/benchmark/no-orm/warmup" || true
  url="$(build_url "$base" "?per_page=15&with=project,comments,tags")"
  for ((i = 1; i <= 20; i++)); do
    do_request GET "$url" 200 >/dev/null || true
  done
}

base_for() {
  case "$1" in
    laravel) printf '%s' "$LARAVEL_BASE" ;;
    symfony) printf '%s' "$SYMFONY_BASE" ;;
    *) return 1 ;;
  esac
}

: >"$RAW_CSV"
echo "scenario,framework,iteration,http_status,response_time_ms,query_count,response_size_bytes,valid,error" >"$RAW_CSV"

STARTED_AT="$(date '+%Y-%m-%d %H:%M:%S %Z')"
printf '%s\n' "$STARTED_AT" "$LARAVEL_BASE" "$SYMFONY_BASE" "$WARMUP_COUNT" "$MEASURE_COUNT" >"$RUN_META"

echo "ORM isolation benchmark (no-ORM control endpoints)"
echo "Warm-up: ${WARMUP_COUNT} | Measurements: ${MEASURE_COUNT}"
echo

check_availability "Laravel" "$LARAVEL_BASE"
check_availability "Symfony" "$SYMFONY_BASE"
warm_catalog "laravel" "$LARAVEL_BASE"
warm_catalog "symfony" "$SYMFONY_BASE"
echo

for entry in "${SCENARIOS[@]}"; do
  IFS='|' read -r scenario method suffix expected needs_body <<<"$entry"
  echo "=== ${scenario} (${method}) ==="

  for framework in laravel symfony; do
    base="$(base_for "$framework")"
    url="$(build_url "$base" "$suffix")"
    body=""
    if [[ "$needs_body" == "1" ]]; then
      if [[ "$method" == "POST" ]]; then
        body="$(create_body)"
      else
        body="$(update_body)"
      fi
    fi

    echo "  Warm-up ${framework}..."
    for ((i = 1; i <= WARMUP_COUNT; i++)); do

      if [[ "$method" == "POST" ]]; then
        body="$(create_body)"
      fi
      result="$(do_request "$method" "$url" "$expected" "$body")"
      IFS='|' read -r _ _ _ _ valid reason <<<"$result"
      if [[ "$valid" != "1" ]]; then
        echo "  ABORT warm-up ${framework}/${scenario}: ${reason}" >&2
        exit 1
      fi
    done

    echo "  Measuring ${framework} (${MEASURE_COUNT})..."
    for ((i = 1; i <= MEASURE_COUNT; i++)); do
      if [[ "$method" == "POST" ]]; then
        body="$(create_body)"
      fi
      result="$(do_request "$method" "$url" "$expected" "$body")"
      IFS='|' read -r status rt qc size valid reason <<<"$result"
      if [[ "$valid" != "1" ]]; then
        echo "  ABORT measure ${framework}/${scenario} #${i}: ${reason}" >&2
        exit 1
      fi
      printf '%s,%s,%s,%s,%s,%s,%s,%s,%s\n' \
        "$scenario" "$framework" "$i" "$status" "$rt" "$qc" "$size" "$valid" "$reason" >>"$RAW_CSV"
    done

    sleep "$PAUSE_BETWEEN_FRAMEWORKS_SEC"
  done
  echo
done

FINISHED_AT="$(date '+%Y-%m-%d %H:%M:%S %Z')"

python3 - "$RAW_CSV" "$SUMMARY_CSV" "$REPORT_MD" "$READ_SUMMARY" "$READ_SUMMARY_PRE_TIMER" "$WRITE_SUMMARY" \
  "$STARTED_AT" "$FINISHED_AT" "$LARAVEL_BASE" "$SYMFONY_BASE" \
  "$WARMUP_COUNT" "$MEASURE_COUNT" <<'PY'
import csv, math, statistics, sys
from pathlib import Path

raw_path, summary_path, report_path, read_sum, read_sum_pre, write_sum = map(Path, sys.argv[1:7])
started, finished, lar_base, sym_base = sys.argv[7:11]
warmup, measure = sys.argv[11], sys.argv[12]

ORDER = [
    "list_per_page_15", "list_per_page_100", "single_task",
    "list_with_project", "list_with_comments", "list_with_tags", "list_with_all",
    "create_task", "update_task", "delete_task",
]

rows = list(csv.DictReader(raw_path.open()))
stats = {}
for sc in ORDER:
    stats[sc] = {}
    for fw in ("laravel", "symfony"):
        times = [float(r["response_time_ms"]) for r in rows if r["scenario"] == sc and r["framework"] == fw]
        sizes = [int(r["response_size_bytes"]) for r in rows if r["scenario"] == sc and r["framework"] == fw]
        qcs = [int(r["query_count"]) for r in rows if r["scenario"] == sc and r["framework"] == fw]
        times_sorted = sorted(times)
        def pct(p):
            if not times_sorted:
                return 0.0
            k = (len(times_sorted) - 1) * p / 100
            f = math.floor(k)
            c = math.ceil(k)
            if f == c:
                return times_sorted[int(k)]
            return times_sorted[f] * (c - k) + times_sorted[c] * (k - f)
        stats[sc][fw] = {
            "n": len(times),
            "avg": statistics.mean(times) if times else 0,
            "median": statistics.median(times) if times else 0,
            "min": min(times) if times else 0,
            "max": max(times) if times else 0,
            "stddev": statistics.pstdev(times) if len(times) > 1 else 0,
            "p95": pct(95),
            "sql": statistics.mean(qcs) if qcs else 0,
            "size": statistics.mean(sizes) if sizes else 0,
        }

with summary_path.open("w", newline="") as f:
    w = csv.writer(f)
    w.writerow([
        "scenario", "laravel_avg_ms", "symfony_avg_ms", "laravel_median_ms", "symfony_median_ms",
        "laravel_p95_ms", "symfony_p95_ms", "laravel_avg_queries", "symfony_avg_queries",
        "laravel_avg_size_bytes", "symfony_avg_size_bytes",
    ])
    for sc in ORDER:
        L, S = stats[sc]["laravel"], stats[sc]["symfony"]
        w.writerow([
            sc, f"{L['avg']:.6f}", f"{S['avg']:.6f}", f"{L['median']:.6f}", f"{S['median']:.6f}",
            f"{L['p95']:.6f}", f"{S['p95']:.6f}", f"{L['sql']:.3f}", f"{S['sql']:.3f}",
            f"{L['size']:.1f}", f"{S['size']:.1f}",
        ])

def load_full(path):
    if not path.is_file():
        return {}
    out = {}
    with path.open() as f:
        for r in csv.DictReader(f):
            lar = (r.get("laravel_avg_ms") or "").strip()
            sym = (r.get("symfony_avg_ms") or "").strip()
            if not lar or not sym:
                continue
            out[r["scenario"]] = {
                "laravel": float(lar),
                "symfony": float(sym),
            }
    return out

full = {}
full.update(load_full(read_sum))
full.update(load_full(write_sum))
full_pre_timer = load_full(read_sum_pre)

md = []
md.append("# ORM isolation benchmark report")
md.append("")
md.append(f"- **Started:** {started}")
md.append(f"- **Finished:** {finished}")
md.append(f"- **Laravel:** `{lar_base}`")
md.append(f"- **Symfony:** `{sym_base}`")
md.append(f"- **Warm-up / measured:** {warmup} / {measure}")
md.append(f"- **Endpoint prefix:** `/api/benchmark/no-orm/tasks`")
md.append("")
md.append("Control experiment: same HTTP/framework/serialization path with **in-memory data** (no ORM SQL).")
md.append("Every request must report `X-Query-Count: 0` (otherwise the run aborts).")
md.append("")
md.append("Interpretation note: `full − no_orm` approximates cost of the removed data-access path,")
md.append("not a laboratory measurement of Doctrine vs Eloquent in isolation.")
md.append("")

for sc in ORDER:
    md.append(f"## {sc}")
    md.append("")
    md.append("| Metric | Laravel | Symfony |")
    md.append("| --- | --- | --- |")
    L, S = stats[sc]["laravel"], stats[sc]["symfony"]
    md.append(f"| Valid measurements | {L['n']} | {S['n']} |")
    md.append(f"| Avg response time (ms) | {L['avg']:.3f} | {S['avg']:.3f} |")
    md.append(f"| Median response time (ms) | {L['median']:.3f} | {S['median']:.3f} |")
    md.append(f"| Min response time (ms) | {L['min']:.3f} | {S['min']:.3f} |")
    md.append(f"| Max response time (ms) | {L['max']:.3f} | {S['max']:.3f} |")
    md.append(f"| Stddev response time (ms) | {L['stddev']:.3f} | {S['stddev']:.3f} |")
    md.append(f"| P95 response time (ms) | {L['p95']:.3f} | {S['p95']:.3f} |")
    md.append(f"| Avg SQL queries | {L['sql']:.3f} | {S['sql']:.3f} |")
    md.append(f"| Avg response size (bytes) | {L['size']:.1f} | {S['size']:.1f} |")
    md.append("")

md.append("## Summary (no-ORM)")
md.append("")
md.append("| Scenario | Laravel avg | Symfony avg | Laravel P95 | Symfony P95 | Laravel SQL | Symfony SQL |")
md.append("| --- | ---: | ---: | ---: | ---: | ---: | ---: |")
for sc in ORDER:
    L, S = stats[sc]["laravel"], stats[sc]["symfony"]
    md.append(
        f"| {sc} | {L['avg']:.3f} | {S['avg']:.3f} | {L['p95']:.3f} | {S['p95']:.3f} | "
        f"{L['sql']:.0f} | {S['sql']:.0f} |"
    )
md.append("")

md.append("## Comparison: full endpoints vs no-ORM (avg ms)")
md.append("")
md.append("| Scenario | Laravel full | Laravel no ORM | Symfony full | Symfony no ORM |")
md.append("| --- | ---: | ---: | ---: | ---: |")
missing = []
for sc in ORDER:
    if sc not in full:
        missing.append(sc)
        md.append(f"| {sc} | — | {stats[sc]['laravel']['avg']:.3f} | — | {stats[sc]['symfony']['avg']:.3f} |")
    else:
        md.append(
            f"| {sc} | {full[sc]['laravel']:.3f} | {stats[sc]['laravel']['avg']:.3f} | "
            f"{full[sc]['symfony']:.3f} | {stats[sc]['symfony']['avg']:.3f} |"
        )
md.append("")
if missing:
    md.append(
        "Scenarios without a matching row in `read_benchmark_summary.csv` / "
        f"`write_benchmark_summary.csv`: {', '.join(missing)}."
    )
    md.append("")

md.append("## Laravel timer scope fix (read scenarios, avg ms)")
md.append("")
md.append("After moving `BenchmarkMetricsMiddleware` before `SubstituteBindings`, Laravel full read timings include route model binding and comparable request setup.")
md.append("")
if full_pre_timer:
    md.append("| Scenario | Laravel full (before timer fix) | Laravel full (after timer fix) | Delta (ms) |")
    md.append("| --- | ---: | ---: | ---: |")
    read_scenarios = [
        "list_per_page_15", "list_per_page_100", "single_task",
        "list_with_project", "list_with_comments", "list_with_tags", "list_with_all",
    ]
    for sc in read_scenarios:
        if sc in full_pre_timer and sc in full:
            before = full_pre_timer[sc]["laravel"]
            after = full[sc]["laravel"]
            md.append(f"| {sc} | {before:.3f} | {after:.3f} | {after - before:+.3f} |")
    md.append("")
else:
    md.append("_No pre-timer snapshot at `read_benchmark_summary_pre_timer_fix.csv`._")
    md.append("")

md.append("## Approximate path cost removed in no-ORM (`full − no_orm`)")
md.append("")
md.append("Values below approximate the cost of the **removed data-access path** (ORM + DB),")
md.append("not a direct measurement of Doctrine vs Eloquent in isolation.")
md.append("")
md.append("| Scenario | Framework | full (ms) | no_orm (ms) | delta (ms) | share of full (%) |")
md.append("| --- | --- | ---: | ---: | ---: | ---: |")
for sc in ORDER:
    if sc not in full:
        continue
    for fw, label in (("laravel", "Laravel"), ("symfony", "Symfony")):
        full_t = full[sc][fw]
        no = stats[sc][fw]["avg"]
        delta = full_t - no
        share = (delta / full_t * 100.0) if full_t > 0 else 0.0
        md.append(f"| {sc} | {label} | {full_t:.3f} | {no:.3f} | {delta:.3f} | {share:.1f} |")
md.append("")

md.append("## Methodological limits")
md.append("")
md.append("- No-ORM still uses Eloquent/Doctrine **classes** for serialization shapes, but not the query unit of work / DB.")
md.append("- Store/update skip DB `exists` checks present on production endpoints.")
md.append("- In-memory payloads are deterministic fixtures (5 comments, 2 tags), not a sample of seed data.")
md.append("- Full vs no-ORM timings come from separate runs; treat deltas as approximate.")
md.append("- This is **not** a direct Doctrine vs Eloquent micro-benchmark.")
md.append("")
md.append("## Artifacts")
md.append("")
md.append(f"- Raw CSV: `{raw_path}`")
md.append(f"- Summary CSV: `{summary_path}`")
md.append(f"- This report: `{report_path}`")
md.append("")

report_path.write_text("\n".join(md), encoding="utf-8")
print(f"Report: {report_path}")
print(f"Summary: {summary_path}")
PY

echo "Done."
rm -f "$RUN_META"
