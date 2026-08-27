#!/usr/bin/env bash

set -euo pipefail


LARAVEL_BASE="${LARAVEL_BASE:-http://localhost:8080}"
SYMFONY_BASE="${SYMFONY_BASE:-http://localhost:8081}"
WARMUP_COUNT="${WARMUP_COUNT:-5}"
MEASURE_COUNT="${MEASURE_COUNT:-30}"
PAUSE_BETWEEN_FRAMEWORKS_SEC="${PAUSE_BETWEEN_FRAMEWORKS_SEC:-1}"
PROJECT_ID="${PROJECT_ID:-1}"
CURL_TIMEOUT_SEC="${CURL_TIMEOUT_SEC:-30}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RAW_CSV="${SCRIPT_DIR}/write_benchmark_results.csv"
SUMMARY_CSV="${SCRIPT_DIR}/write_benchmark_summary.csv"
REPORT_MD="${SCRIPT_DIR}/write_benchmark_report.md"
RUN_META="${SCRIPT_DIR}/.write_benchmark_run_meta.txt"
TRACKED_IDS="${SCRIPT_DIR}/.write_benchmark_tracked_ids.txt"

HEADER_METRICS="X-Benchmark-Metrics: 1"
HEADER_ACCEPT="Accept: application/json"
HEADER_CONTENT="Content-Type: application/json"

if ! command -v curl >/dev/null 2>&1; then
  echo "Error: curl is required." >&2
  exit 1
fi
if ! command -v python3 >/dev/null 2>&1; then
  echo "Error: python3 is required." >&2
  exit 1
fi


: >"$TRACKED_IDS"

track_id() {
  local framework="$1"
  local id="$2"
  [[ -n "$id" && "$id" != "null" ]] || return 0
  printf '%s %s\n' "$framework" "$id" >>"$TRACKED_IDS"
}

untrack_id() {
  local framework="$1"
  local id="$2"
  local tmp
  tmp="$(mktemp)"
  awk -v fw="$framework" -v tid="$id" '$1 != fw || $2 != tid' "$TRACKED_IDS" >"$tmp" || true
  mv "$tmp" "$TRACKED_IDS"
}

base_for() {
  case "$1" in
    laravel) printf '%s' "$LARAVEL_BASE" ;;
    symfony) printf '%s' "$SYMFONY_BASE" ;;
    *) echo "Unknown framework: $1" >&2; return 1 ;;
  esac
}

silent_delete() {
  local framework="$1"
  local id="$2"
  local base
  base="$(base_for "$framework")"
  curl -sS -o /dev/null \
    --max-time "$CURL_TIMEOUT_SEC" \
    -X DELETE \
    -H "$HEADER_ACCEPT" \
    "${base}/api/tasks/${id}" >/dev/null 2>&1 || true
  untrack_id "$framework" "$id"
}

cleanup_all_tracked() {
  if [[ ! -f "$TRACKED_IDS" ]]; then
    return 0
  fi
  local fw id

  while read -r fw id; do
    [[ -n "${fw:-}" && -n "${id:-}" ]] || continue
    silent_delete "$fw" "$id"
  done < <(awk 'NF==2 { print $1, $2 }' "$TRACKED_IDS" | awk '!seen[$0]++')
  : >"$TRACKED_IDS"
}

on_exit() {
  local ec=$?
  echo
  echo "Emergency cleanup of tracked benchmark tasks..."
  cleanup_all_tracked || true
  rm -f "$RUN_META" "$TRACKED_IDS" 2>/dev/null || true
  exit "$ec"
}
trap on_exit EXIT


extract_task_id() {
  local body_file="$1"
  python3 - "$body_file" <<'PY'
import json, sys
path = sys.argv[1]

def find_id(obj, depth=0):
    if depth > 4 or obj is None:
        return None
    if isinstance(obj, dict):
        if "id" in obj and isinstance(obj["id"], int):
            return obj["id"]
        if "id" in obj and isinstance(obj["id"], str) and obj["id"].isdigit():
            return int(obj["id"])
        for key in ("data", "task", "result"):
            if key in obj:
                found = find_id(obj[key], depth + 1)
                if found is not None:
                    return found
        # fallback: first nested dict with id
        for v in obj.values():
            if isinstance(v, (dict, list)):
                found = find_id(v, depth + 1)
                if found is not None:
                    return found
    elif isinstance(obj, list):
        for item in obj[:3]:
            found = find_id(item, depth + 1)
            if found is not None:
                return found
    return None

try:
    with open(path, "rb") as f:
        raw = f.read()
    if not raw:
        sys.exit(0)
    data = json.loads(raw.decode("utf-8"))
    tid = find_id(data)
    if tid is not None:
        print(tid)
except Exception:
    pass
PY
}

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

unique_suffix() {
  python3 -c 'import time,uuid; print(f"{time.time_ns()}-{uuid.uuid4().hex[:8]}")'
}

build_create_payload() {
  local suffix="$1"
  python3 -c '
import json, sys
project_id = int(sys.argv[1])
suffix = sys.argv[2]
print(json.dumps({
    "project_id": project_id,
    "title": f"Benchmark task {suffix}",
    "description": "Task created during write benchmark",
    "status": "todo",
    "priority": "medium",
}))
' "$PROJECT_ID" "$suffix"
}

build_update_payload() {
  local suffix="$1"
  python3 -c '
import json, sys
project_id = int(sys.argv[1])
suffix = sys.argv[2]
print(json.dumps({
    "project_id": project_id,
    "title": f"Updated benchmark task {suffix}",
    "description": "Task updated during write benchmark",
    "status": "done",
    "priority": "high",
}))
' "$PROJECT_ID" "$suffix"
}


http_request() {
  local method="$1"
  local url="$2"
  local body="${3:-}"
  local with_metrics="${4:-1}"

  local tmp_body tmp_hdrs
  tmp_body="$(mktemp)"
  tmp_hdrs="$(mktemp)"

  local curl_args=(
    -sS
    --max-time "$CURL_TIMEOUT_SEC"
    -D "$tmp_hdrs"
    -o "$tmp_body"
    -X "$method"
    -H "$HEADER_ACCEPT"
    -w '%{http_code} %{size_download} %{time_total}'
  )
  if [[ "$with_metrics" == "1" ]]; then
    curl_args+=(-H "$HEADER_METRICS")
  fi
  if [[ -n "$body" ]]; then
    curl_args+=(-H "$HEADER_CONTENT" -d "$body")
  fi

  local curl_out curl_exit=0
  curl_out="$(curl "${curl_args[@]}" "$url" 2>/dev/null)" || curl_exit=$?

  if [[ "$curl_exit" -ne 0 ]]; then
    rm -f "$tmp_body" "$tmp_hdrs"
    printf '000|||0|0||curl_failed_or_timeout|\n'
    return 0
  fi

  local status size_bytes time_total client_ms
  status="$(printf '%s' "$curl_out" | awk '{print $1}')"
  size_bytes="$(printf '%s' "$curl_out" | awk '{print $2}')"
  time_total="$(printf '%s' "$curl_out" | awk '{print $3}')"
  size_bytes="${size_bytes%%.*}"
  size_bytes="${size_bytes:-0}"
  client_ms="$(python3 -c "print(round(float('${time_total:-0}') * 1000, 3))")"

  local headers rt qc record_id error=""
  headers="$(cat "$tmp_hdrs")"
  rt="$(header_value "$headers" "X-Response-Time-Ms")"
  qc="$(header_value "$headers" "X-Query-Count")"
  record_id="$(extract_task_id "$tmp_body")"

  if [[ "$with_metrics" == "1" ]]; then
    if [[ -z "$rt" || -z "$qc" ]]; then
      error="missing_benchmark_headers"
      echo "    WARNING: missing X-Response-Time-Ms and/or X-Query-Count for ${method} ${url}" >&2
    fi
  fi

  printf '%s|%s|%s|%s|%s|%s|%s|%s\n' \
    "$status" "$rt" "$qc" "$client_ms" "$size_bytes" "${record_id}" "${error}" "$tmp_body"
  rm -f "$tmp_hdrs"
}

helper_create() {
  local framework="$1"
  local suffix="$2"
  local base payload result status record_id body_path error
  base="$(base_for "$framework")"
  payload="$(build_create_payload "$suffix")"
  result="$(http_request POST "${base}/api/tasks" "$payload" 0)"
  IFS='|' read -r status _ _ _ _ record_id error body_path <<<"$result"
  rm -f "${body_path:-}"
  if [[ "$status" != "200" && "$status" != "201" ]] || [[ -z "$record_id" ]]; then
    echo "Helper create failed for ${framework}: status=${status} id=${record_id:-none} err=${error}" >&2
    return 1
  fi
  track_id "$framework" "$record_id"
  printf '%s\n' "$record_id"
}

status_ok_for_method() {
  local method="$1"
  local status="$2"
  case "$method" in
    POST) [[ "$status" == "200" || "$status" == "201" ]] ;;
    PUT)  [[ "$status" == "200" ]] ;;
    DELETE) [[ "$status" == "200" || "$status" == "204" ]] ;;
    *) return 1 ;;
  esac
}

write_csv_row() {
  local framework="$1"
  local scenario="$2"
  local iteration="$3"
  local method="$4"
  local endpoint="$5"
  local status="$6"
  local response_time_ms="$7"
  local query_count="$8"
  local client_time_ms="$9"
  local response_size_bytes="${10}"
  local error="${11}"
  local record_id="${12}"
  local valid="${13}"

  error="${error//,/;}"
  printf '%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n' \
    "$framework" "$scenario" "$iteration" "$method" "$endpoint" \
    "$status" "$response_time_ms" "$query_count" "$client_time_ms" \
    "$response_size_bytes" "$error" "$record_id" "$valid" >>"$RAW_CSV"
}

check_availability() {
  local name="$1"
  local base="$2"
  local url="${base}/api/tasks?per_page=1"
  echo -n "Checking ${name} (${base})... "
  local code
  code="$(curl -sS -o /dev/null --max-time "$CURL_TIMEOUT_SEC" \
    -w '%{http_code}' -H "$HEADER_ACCEPT" "$url" 2>/dev/null || true)"
  if [[ "$code" != "200" ]]; then
    echo "FAIL (HTTP ${code:-unreachable})"
    echo "Error: ${name} is not available at ${base}" >&2
    exit 1
  fi
  echo "OK (HTTP ${code})"
}

check_project_exists() {
  local name="$1"
  local base="$2"
  local url="${base}/api/projects/${PROJECT_ID}"
  echo -n "Checking ${name} project_id=${PROJECT_ID}... "
  local code
  code="$(curl -sS -o /dev/null --max-time "$CURL_TIMEOUT_SEC" \
    -w '%{http_code}' -H "$HEADER_ACCEPT" "$url" 2>/dev/null || true)"
  if [[ "$code" != "200" ]]; then
    echo "FAIL (HTTP ${code:-unreachable})"
    echo "Error: project_id=${PROJECT_ID} not found on ${name}. Set PROJECT_ID to an existing project." >&2
    exit 1
  fi
  echo "OK"
}


run_create_task() {
  local framework="$1"
  local phase="$2" # warmup|measure
  local count="$3"
  local base endpoint i suffix result status rt qc client size rid error body_path
  local valid method="POST"
  local scenario="create_task"
  local created_ids=()

  base="$(base_for "$framework")"
  endpoint="/api/tasks"

  echo "  ${phase} ${framework}/${scenario} (${count})..."
  for ((i = 1; i <= count; i++)); do
    suffix="$(unique_suffix)-${framework}-${phase}-${i}"
    result="$(http_request POST "${base}${endpoint}" "$(build_create_payload "$suffix")" 1)"
    IFS='|' read -r status rt qc client size rid error body_path <<<"$result"
    rm -f "${body_path:-}"

    valid=1
    if ! status_ok_for_method POST "$status"; then
      valid=0
      error="${error:+$error;}unexpected_status_${status}"
    fi
    if [[ -z "$rt" || -z "$qc" ]]; then
      valid=0
      error="${error:+$error;}incomplete_metrics"
    fi
    if [[ -z "$rid" ]]; then
      valid=0
      error="${error:+$error;}missing_record_id"
    else
      track_id "$framework" "$rid"
      created_ids+=("$rid")
    fi

    if [[ "$phase" == "measure" ]]; then
      write_csv_row "$framework" "$scenario" "$i" "$method" "$endpoint" \
        "$status" "$rt" "$qc" "$client" "$size" "${error:-}" "${rid:-}" "$valid"
    fi

    if [[ "$valid" == "1" ]]; then
      printf '    [%2d/%d] OK  status=%s server=%s ms sql=%s client=%s ms id=%s\n' \
        "$i" "$count" "$status" "$rt" "$qc" "$client" "$rid"
    else
      printf '    [%2d/%d] FAIL status=%s err=%s\n' "$i" "$count" "${status:-?}" "${error:-unknown}"
      if [[ "$phase" == "warmup" ]]; then
        echo "  ABORT warm-up ${framework}/${scenario}"
        if [[ ${#created_ids[@]} -gt 0 ]]; then
          local id
          for id in "${created_ids[@]}"; do
            silent_delete "$framework" "$id"
          done
        fi
        return 1
      fi
    fi
  done

  if [[ ${#created_ids[@]} -gt 0 ]]; then
    echo "  Cleaning up ${#created_ids[@]} created task(s) for ${framework}/${scenario}..."
    local id
    for id in "${created_ids[@]}"; do
      silent_delete "$framework" "$id"
    done
  fi
  return 0
}


run_update_task() {
  local framework="$1"
  local phase="$2"
  local count="$3"
  local base endpoint i suffix helper_id result status rt qc client size rid error body_path
  local valid method="PUT"
  local scenario="update_task"

  base="$(base_for "$framework")"

  echo "  ${phase} ${framework}/${scenario} (${count})..."
  for ((i = 1; i <= count; i++)); do
    suffix="$(unique_suffix)-${framework}-${phase}-${i}"
    helper_id="$(helper_create "$framework" "upd-helper-${suffix}")" || {
      echo "  ABORT ${framework}/${scenario}: helper create failed"
      return 1
    }
    endpoint="/api/tasks/${helper_id}"

    result="$(http_request PUT "${base}${endpoint}" "$(build_update_payload "$suffix")" 1)"
    IFS='|' read -r status rt qc client size rid error body_path <<<"$result"
    rm -f "${body_path:-}"

    valid=1
    if ! status_ok_for_method PUT "$status"; then
      valid=0
      error="${error:+$error;}unexpected_status_${status}"
    fi
    if [[ -z "$rt" || -z "$qc" ]]; then
      valid=0
      error="${error:+$error;}incomplete_metrics"
    fi

    if [[ "$phase" == "measure" ]]; then
      write_csv_row "$framework" "$scenario" "$i" "$method" "$endpoint" \
        "$status" "$rt" "$qc" "$client" "$size" "${error:-}" "${helper_id}" "$valid"
    fi

    # Cleanup helper (not measured)
    silent_delete "$framework" "$helper_id"

    if [[ "$valid" == "1" ]]; then
      printf '    [%2d/%d] OK  status=%s server=%s ms sql=%s client=%s ms id=%s\n' \
        "$i" "$count" "$status" "$rt" "$qc" "$client" "$helper_id"
    else
      printf '    [%2d/%d] FAIL status=%s err=%s\n' "$i" "$count" "${status:-?}" "${error:-unknown}"
      if [[ "$phase" == "warmup" ]]; then
        echo "  ABORT warm-up ${framework}/${scenario}"
        return 1
      fi
    fi
  done
  return 0
}

run_delete_task() {
  local framework="$1"
  local phase="$2"
  local count="$3"
  local base endpoint i suffix helper_id result status rt qc client size rid error body_path
  local valid method="DELETE"
  local scenario="delete_task"

  base="$(base_for "$framework")"

  echo "  ${phase} ${framework}/${scenario} (${count})..."
  for ((i = 1; i <= count; i++)); do
    suffix="$(unique_suffix)-${framework}-${phase}-${i}"
    helper_id="$(helper_create "$framework" "del-helper-${suffix}")" || {
      echo "  ABORT ${framework}/${scenario}: helper create failed"
      return 1
    }
    endpoint="/api/tasks/${helper_id}"

    result="$(http_request DELETE "${base}${endpoint}" "" 1)"
    IFS='|' read -r status rt qc client size rid error body_path <<<"$result"
    rm -f "${body_path:-}"

    valid=1
    if ! status_ok_for_method DELETE "$status"; then
      valid=0
      error="${error:+$error;}unexpected_status_${status}"
    fi
    if [[ -z "$rt" || -z "$qc" ]]; then
      valid=0
      error="${error:+$error;}incomplete_metrics"
    fi

    if [[ "$phase" == "measure" ]]; then
      write_csv_row "$framework" "$scenario" "$i" "$method" "$endpoint" \
        "$status" "$rt" "$qc" "$client" "$size" "${error:-}" "${helper_id}" "$valid"
    fi

    if status_ok_for_method DELETE "$status"; then
      untrack_id "$framework" "$helper_id"
    else
      silent_delete "$framework" "$helper_id"
    fi

    if [[ "$valid" == "1" ]]; then
      printf '    [%2d/%d] OK  status=%s server=%s ms sql=%s client=%s ms id=%s\n' \
        "$i" "$count" "$status" "$rt" "$qc" "$client" "$helper_id"
    else
      printf '    [%2d/%d] FAIL status=%s err=%s\n' "$i" "$count" "${status:-?}" "${error:-unknown}"
      if [[ "$phase" == "warmup" ]]; then
        echo "  ABORT warm-up ${framework}/${scenario}"
        return 1
      fi
    fi
  done
  return 0
}

run_scenario_for_framework() {
  local scenario="$1"
  local framework="$2"

  case "$scenario" in
    create_task)
      run_create_task "$framework" warmup "$WARMUP_COUNT" || return 1
      run_create_task "$framework" measure "$MEASURE_COUNT" || return 1
      ;;
    update_task)
      run_update_task "$framework" warmup "$WARMUP_COUNT" || return 1
      run_update_task "$framework" measure "$MEASURE_COUNT" || return 1
      ;;
    delete_task)
      run_delete_task "$framework" warmup "$WARMUP_COUNT" || return 1
      run_delete_task "$framework" measure "$MEASURE_COUNT" || return 1
      ;;
    *)
      echo "Unknown scenario: $scenario" >&2
      return 1
      ;;
  esac
  return 0
}

SCENARIOS=(create_task update_task delete_task)

echo "=== Write operations benchmark ==="
echo "Laravel:  ${LARAVEL_BASE}"
echo "Symfony:  ${SYMFONY_BASE}"
echo "Warm-up:  ${WARMUP_COUNT}  |  Measurements: ${MEASURE_COUNT}"
echo "Project:  ${PROJECT_ID}"
echo "Timeout:  ${CURL_TIMEOUT_SEC}s"
echo

check_availability "Laravel" "$LARAVEL_BASE"
check_availability "Symfony" "$SYMFONY_BASE"
check_project_exists "Laravel" "$LARAVEL_BASE"
check_project_exists "Symfony" "$SYMFONY_BASE"
echo

STARTED_AT="$(date '+%Y-%m-%d %H:%M:%S %Z')"
printf 'started_at=%s\nlaravel_base=%s\nsymfony_base=%s\nwarmup=%s\nmeasure=%s\nproject_id=%s\ntimeout=%s\n' \
  "$STARTED_AT" "$LARAVEL_BASE" "$SYMFONY_BASE" "$WARMUP_COUNT" "$MEASURE_COUNT" \
  "$PROJECT_ID" "$CURL_TIMEOUT_SEC" >"$RUN_META"

printf 'framework,scenario,iteration,method,endpoint,status,response_time_ms,query_count,client_time_ms,response_size_bytes,error,record_id,valid\n' >"$RAW_CSV"

for scenario in "${SCENARIOS[@]}"; do
  echo "────────────────────────────────────────"
  echo "Scenario: ${scenario}"

  run_scenario_for_framework "$scenario" "laravel" || \
    echo "  Note: laravel/${scenario} finished with errors"
  sleep "$PAUSE_BETWEEN_FRAMEWORKS_SEC"

  run_scenario_for_framework "$scenario" "symfony" || \
    echo "  Note: symfony/${scenario} finished with errors"
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

SCENARIO_ORDER = ["create_task", "update_task", "delete_task"]
SCENARIO_DESC = {
    "create_task": "POST /api/tasks — tworzenie nowego zadania",
    "update_task": "PUT /api/tasks/{id} — aktualizacja istniejącego zadania",
    "delete_task": "DELETE /api/tasks/{id} — usuwanie zadania",
}

rows = list(csv.DictReader(open(raw_path, newline="", encoding="utf-8")))
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
    if to_float(r.get("response_time_ms")) is None:
        return False
    if to_float(r.get("query_count")) is None:
        return False
    return True


def percentile(sorted_vals, p):
    n = len(sorted_vals)
    if n == 0:
        return None
    if n == 1:
        return sorted_vals[0]
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
    if n == 0 or mean is None:
        return None
    if n < 2:
        return 0.0
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
    measure_rows = by.get((scenario, framework), [])
    ok = [r for r in measure_rows if is_valid_row(r)]
    times = [float(r["response_time_ms"]) for r in ok]
    queries = [float(r["query_count"]) for r in ok]
    sizes = [float(r["response_size_bytes"]) for r in ok if to_float(r.get("response_size_bytes")) is not None]
    times_s = sorted(times)
    mean_t = sum(times) / len(times) if times else None
    mean_q = sum(queries) / len(queries) if queries else None
    mean_s = sum(sizes) / len(sizes) if sizes else None
    return {
        "ok": len(ok),
        "err": len(measure_rows) - len(ok),
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


stats = {
    sc: {"laravel": compute(sc, "laravel"), "symfony": compute(sc, "symfony")}
    for sc in SCENARIO_ORDER
}


def compare(sc):
    L, S = stats[sc]["laravel"], stats[sc]["symfony"]
    la, sa = L["mean_t"], S["mean_t"]
    lq, sq = L["mean_q"], S["mean_q"]
    faster = diff_pct = ratio = sql_diff = None
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
        if sa:
            ratio = la / sa
    if lq is not None and sq is not None:
        sql_diff = lq - sq
    return {
        "faster": faster,
        "diff_pct": diff_pct,
        "ratio_l_over_s": ratio,
        "sql_diff_l_minus_s": sql_diff,
    }


comparisons = {sc: compare(sc) for sc in SCENARIO_ORDER}


def print_scenario_table(sc):
    L, S, C = stats[sc]["laravel"], stats[sc]["symfony"], comparisons[sc]
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
        print(f"  Faster avg: {C['faster']} ({fmt_pct(C['diff_pct'])} lower mean than the other)")
    elif C["faster"] == "tie":
        print("  Faster avg: tie")
    else:
        print("  Faster avg: n/a (insufficient valid data)")
    if C["sql_diff_l_minus_s"] is not None:
        print(f"  SQL avg difference (Laravel − Symfony): {fmt(C['sql_diff_l_minus_s'])}")
    if C["ratio_l_over_s"] is not None:
        print(f"  Mean time ratio (Laravel / Symfony): {fmt(C['ratio_l_over_s'])}")


for sc in SCENARIO_ORDER:
    print_scenario_table(sc)

print()
print("=== Summary (all scenarios) ===")
headers = [
    "Scenario", "L avg", "S avg", "L med", "S med", "L p95", "S p95",
    "L SQL", "S SQL", "Faster", "Diff %",
]
widths = [14, 8, 8, 8, 8, 8, 8, 7, 7, 8, 7]


def cell(s, w):
    s = str(s)
    if len(s) > w:
        return s[: w - 1] + "…"
    numlike = s.replace(".", "").replace("-", "").isdigit() or s.endswith("%") or s == "n/a"
    return s.rjust(w) if numlike else s.ljust(w)


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
        C["faster"] or "n/a",
        fmt_pct(C["diff_pct"]) if C["diff_pct"] is not None else "n/a",
    ]
    print(" | ".join(cell(v, w) for v, w in zip(vals, widths)))

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
                out[k] = (
                    f"{v:.4f}" if k == "difference_percent"
                    else f"{v:.6f}".rstrip("0").rstrip(".")
                )
            elif v is None:
                out[k] = ""
            else:
                out[k] = v
        w.writerow(out)

finished_at = datetime.now().astimezone().strftime("%Y-%m-%d %H:%M:%S %Z")


def md_table(headers, rows):
    lines = [
        "| " + " | ".join(headers) + " |",
        "| " + " | ".join("---" for _ in headers) + " |",
    ]
    for r in rows:
        lines.append("| " + " | ".join(str(c) for c in r) + " |")
    return "\n".join(lines)


md = []
md.append("# Write operations benchmark report")
md.append("")
md.append(f"- **Started:** {meta.get('started_at', 'n/a')}")
md.append(f"- **Finished:** {finished_at}")
md.append(f"- **Laravel:** `{meta.get('laravel_base', '')}`")
md.append(f"- **Symfony:** `{meta.get('symfony_base', '')}`")
md.append(f"- **Warm-up requests per framework/scenario:** {meta.get('warmup', '')}")
md.append(f"- **Measured requests per framework/scenario:** {meta.get('measure', '')}")
md.append(f"- **Project ID used for creates:** {meta.get('project_id', '1')}")
md.append(f"- **HTTP timeout (s):** {meta.get('timeout', '')}")
md.append("")
md.append("## Notes")
md.append("")
md.append(
    "- Status values used in payloads: `todo` (create), `done` (update) — matching API enums "
    "(`todo|in_progress|done|cancelled`)."
)
md.append(
    "- Update/delete iterations create a helper task first; only the PUT/DELETE is measured."
)
md.append(
    "- Tasks created during `create_task` are deleted after the scenario (outside measured time)."
)
md.append("")
md.append("## Scenarios")
md.append("")

for sc in SCENARIO_ORDER:
    L, S, C = stats[sc]["laravel"], stats[sc]["symfony"], comparisons[sc]
    md.append(f"### {sc}")
    md.append("")
    md.append(SCENARIO_DESC.get(sc, sc))
    md.append("")
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
    md.append(md_table(["Metric", "Laravel", "Symfony"], body))
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
    md.append("")

md.append("## Summary")
md.append("")
sum_headers = [
    "Scenario",
    "Laravel avg",
    "Symfony avg",
    "Laravel median",
    "Symfony median",
    "Laravel P95",
    "Symfony P95",
    "Laravel SQL",
    "Symfony SQL",
    "Faster",
    "Diff %",
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
            row["faster_framework"],
            fmt_pct(row["difference_percent"]) if row["difference_percent"] is not None else "n/a",
        ]
    )
md.append(md_table(sum_headers, sum_body))
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

echo
echo "Final cleanup of any remaining tracked tasks..."
cleanup_all_tracked

trap - EXIT
rm -f "$RUN_META" "$TRACKED_IDS" 2>/dev/null || true
echo "Done."
