#!/usr/bin/env bash

set -euo pipefail

LARAVEL_URL="${LARAVEL_URL:-http://localhost:8080/api/tasks?per_page=15}"
SYMFONY_URL="${SYMFONY_URL:-http://localhost:8081/api/tasks?per_page=15}"
WARMUP_COUNT=5
MEASURE_COUNT=30
CSV_FILE="${CSV_FILE:-benchmark_tasks_list_results.csv}"
HEADER="X-Benchmark-Metrics: 1"
ACCEPT="Accept: application/json"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CSV_PATH="${SCRIPT_DIR}/${CSV_FILE}"

if ! command -v curl >/dev/null 2>&1; then
  echo "Error: curl is required." >&2
  exit 1
fi

if ! command -v python3 >/dev/null 2>&1; then
  echo "Error: python3 is required for statistics." >&2
  exit 1
fi

header_value() {
  local headers="$1"
  local name="$2"
  printf '%s' "$headers" | awk -v key="$(printf '%s' "$name" | tr '[:upper:]' '[:lower:]')" '
    BEGIN { FS = ":"; IGNORECASE = 1 }
    {
      h = $1
      gsub(/\r/, "", h)
      gsub(/^[[:space:]]+|[[:space:]]+$/, "", h)
      if (tolower(h) == key) {
        val = substr($0, index($0, ":") + 1)
        gsub(/\r/, "", val)
        gsub(/^[[:space:]]+|[[:space:]]+$/, "", val)
        print val
        exit
      }
    }
  '
}

do_request() {
  local url="$1"
  local tmp_body
  tmp_body="$(mktemp)"
  local headers
  local status

  headers="$(curl -sS -D - -o "$tmp_body" \
    -H "$HEADER" \
    -H "$ACCEPT" \
    "$url" 2>/dev/null || true)"

  status="$(printf '%s' "$headers" | awk 'NR==1 { print $2; exit }')"
  local rt qc body_ok
  rt="$(header_value "$headers" "X-Response-Time-Ms")"
  qc="$(header_value "$headers" "X-Query-Count")"

  if [[ -s "$tmp_body" ]]; then
    body_ok=1
  else
    body_ok=0
  fi
  rm -f "$tmp_body"

  printf '%s|%s|%s|%s\n' "${status:-000}" "${rt}" "${qc}" "${body_ok}"
}

warmup() {
  local name="$1"
  local url="$2"
  echo "Warm-up: ${name} (${WARMUP_COUNT} requests)..."
  local i
  for ((i = 1; i <= WARMUP_COUNT; i++)); do
    do_request "$url" >/dev/null || true
  done
}

measure() {
  local framework="$1"
  local url="$2"
  local i result status rt qc body_ok valid

  echo "Measuring: ${framework} (${MEASURE_COUNT} requests)..."
  for ((i = 1; i <= MEASURE_COUNT; i++)); do
    result="$(do_request "$url")"
    IFS='|' read -r status rt qc body_ok <<<"$result"

    valid=1
    if [[ "$status" != "200" ]]; then
      valid=0
    fi
    if [[ -z "$rt" || -z "$qc" ]]; then
      valid=0
    fi
    if [[ "$body_ok" != "1" ]]; then
      valid=0
    fi

    if [[ "$valid" -eq 1 ]]; then
      printf '%s,%d,%s,%s,%s\n' "$framework" "$i" "$status" "$rt" "$qc" >>"$CSV_PATH"
      printf '  [%2d/%d] OK  status=%s  time=%s ms  queries=%s\n' "$i" "$MEASURE_COUNT" "$status" "$rt" "$qc"
    else
      # Keep a CSV row so iteration count is visible; mark failed metrics as empty
      printf '%s,%d,%s,%s,%s\n' "$framework" "$i" "${status:-error}" "${rt}" "${qc}" >>"$CSV_PATH"
      printf '  [%2d/%d] FAIL status=%s  time=%s  queries=%s  body_ok=%s\n' \
        "$i" "$MEASURE_COUNT" "${status:-?}" "${rt:-(missing)}" "${qc:-(missing)}" "$body_ok"
    fi
  done
}

echo "=== Benchmark: GET /api/tasks?per_page=15 ==="
echo "Laravel:  ${LARAVEL_URL}"
echo "Symfony:  ${SYMFONY_URL}"
echo "Warm-up:  ${WARMUP_COUNT}  |  Measurements: ${MEASURE_COUNT}"
echo "CSV:      ${CSV_PATH}"
echo

printf 'framework,iteration,status,response_time_ms,query_count\n' >"$CSV_PATH"

warmup "Laravel" "$LARAVEL_URL"
warmup "Symfony" "$SYMFONY_URL"
echo

measure "laravel" "$LARAVEL_URL"
echo
measure "symfony" "$SYMFONY_URL"
echo

echo "=== Comparison ==="
python3 - "$CSV_PATH" <<'PY'
import csv
import math
import sys
from collections import defaultdict

path = sys.argv[1]

rows_by_fw = defaultdict(list)
with open(path, newline="") as f:
    reader = csv.DictReader(f)
    for row in reader:
        rows_by_fw[row["framework"]].append(row)

def is_valid(row):
    if row.get("status") != "200":
        return False
    try:
        float(row["response_time_ms"])
        float(row["query_count"])
    except (TypeError, ValueError):
        return False
    if row["response_time_ms"] == "" or row["query_count"] == "":
        return False
    return True

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
    if n < 2:
        return 0.0
    return math.sqrt(sum((x - mean) ** 2 for x in vals) / (n - 1))

def fmt(v, digits=3):
    if v is None:
        return "n/a"
    if isinstance(v, float):
        return f"{v:.{digits}f}"
    return str(v)

stats = {}
order = ["laravel", "symfony"]
for fw in order:
    rows = rows_by_fw.get(fw, [])
    ok = [r for r in rows if is_valid(r)]
    err = len(rows) - len(ok)
    times = [float(r["response_time_ms"]) for r in ok]
    queries = [float(r["query_count"]) for r in ok]
    times_sorted = sorted(times)
    mean_t = sum(times) / len(times) if times else None
    mean_q = sum(queries) / len(queries) if queries else None
    stats[fw] = {
        "ok": len(ok),
        "err": err,
        "mean_t": mean_t,
        "median_t": median(times_sorted),
        "min_t": min(times) if times else None,
        "max_t": max(times) if times else None,
        "std_t": stddev(times, mean_t) if mean_t is not None else None,
        "mean_q": mean_q,
        "min_q": min(queries) if queries else None,
        "max_q": max(queries) if queries else None,
    }

metrics = [
    ("Valid measurements", "ok", False),
    ("Errors", "err", False),
    ("Avg response time (ms)", "mean_t", True),
    ("Median response time (ms)", "median_t", True),
    ("Min response time (ms)", "min_t", True),
    ("Max response time (ms)", "max_t", True),
    ("Stddev response time (ms)", "std_t", True),
    ("Avg SQL query count", "mean_q", True),
    ("Min SQL query count", "min_q", True),
    ("Max SQL query count", "max_q", True),
]

col1 = 28
coln = 16
line = "+" + "-" * (col1 + 2) + "+" + "-" * (coln + 2) + "+" + "-" * (coln + 2) + "+"
print(line)
print(f"| {'Metric':<{col1}} | {'Laravel':>{coln}} | {'Symfony':>{coln}} |")
print(line)
for label, key, is_float in metrics:
    lv = stats["laravel"][key]
    sv = stats["symfony"][key]
    if is_float:
        ls, ss = fmt(lv), fmt(sv)
    else:
        ls, ss = str(lv), str(sv)
    print(f"| {label:<{col1}} | {ls:>{coln}} | {ss:>{coln}} |")
print(line)

# Winner by mean response time among frameworks with valid samples
candidates = [(fw, stats[fw]["mean_t"]) for fw in order if stats[fw]["mean_t"] is not None]
if len(candidates) == 2:
    winner = min(candidates, key=lambda x: x[1])
    loser = max(candidates, key=lambda x: x[1])
    if winner[1] > 0:
        pct = (loser[1] - winner[1]) / loser[1] * 100.0
        print(f"Faster mean response: {winner[0]} ({pct:.1f}% lower than {loser[0]})")
elif len(candidates) == 1:
    print(f"Only {candidates[0][0]} produced valid measurements.")
else:
    print("No valid measurements for either framework.")

print(f"\nRaw results: {path}")
PY

echo
echo "Done."
