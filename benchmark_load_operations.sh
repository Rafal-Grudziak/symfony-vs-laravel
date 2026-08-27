#!/usr/bin/env bash

set -uo pipefail


LARAVEL_BASE="${LARAVEL_BASE:-http://localhost:8080}"
SYMFONY_BASE="${SYMFONY_BASE:-http://localhost:8081}"
VUS_LEVELS=(${VUS_LEVELS:-10 50 100})

DURATION="${DURATION:-30s}"
SLEEP_DURATION="${SLEEP_DURATION:-0.2}"
PAUSE_BETWEEN_TESTS_SEC="${PAUSE_BETWEEN_TESTS_SEC:-12}"
WARMUP_REQUESTS="${WARMUP_REQUESTS:-10}"

GRACEFUL_STOP="${GRACEFUL_STOP:-60s}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
K6_SCRIPT="${SCRIPT_DIR}/load_test.js"
RESULTS_DIR="${RESULTS_DIR:-${SCRIPT_DIR}/load_benchmark_results}"
SUMMARY_CSV="${RESULTS_DIR}/benchmark_summary.csv"
REPORT_MD="${RESULTS_DIR}/benchmark_report.md"
EXIT_LOG="${RESULTS_DIR}/exit_codes.txt"
ENDPOINT_PATH="/api/tasks"
ENDPOINT_QS="per_page=100&with=project%2Ccomments%2Ctags"

die() { echo "Error: $*" >&2; exit 1; }

resolve_k6() {
  local tools_k6="${SCRIPT_DIR}/.tools/k6"
  if [[ -x "$tools_k6" ]]; then
    K6_MODE="native"
    K6_BIN="$tools_k6"
    return 0
  fi
  if command -v k6 >/dev/null 2>&1; then
    K6_MODE="native"
    K6_BIN="$(command -v k6)"
    return 0
  fi
  if command -v docker >/dev/null 2>&1; then
    if docker image inspect grafana/k6:latest >/dev/null 2>&1 || \
       docker pull grafana/k6:latest >/dev/null 2>&1; then
      K6_MODE="docker"
      return 0
    fi
  fi
  die "k6 is not installed and grafana/k6 Docker image is unavailable.
Install k6 (https://k6.io/docs/get-started/installation/), place a binary at ${tools_k6},
or pull: docker pull grafana/k6"
}

run_k6() {
  local out_json="$1"
  shift

  if [[ "$K6_MODE" == "native" ]]; then
    "$K6_BIN" run --out "json=${out_json}" "$@" "$K6_SCRIPT"
    return $?
  fi

  local docker_base="${BASE_URL}"
  docker_base="${docker_base//localhost/host.docker.internal}"
  docker_base="${docker_base//127.0.0.1/host.docker.internal}"

  local raw_name
  raw_name="$(basename "$out_json")"

  docker run --rm \
    --add-host=host.docker.internal:host-gateway \
    -v "${SCRIPT_DIR}:/scripts:ro" \
    -v "${RESULTS_DIR}:/results" \
    -e BASE_URL="$docker_base" \
    -e FRAMEWORK="$FRAMEWORK" \
    -e VUS="$VUS" \
    -e LOAD_LEVEL="$LOAD_LEVEL" \
    -e DURATION="$DURATION" \
    -e SLEEP_DURATION="$SLEEP_DURATION" \
    -e GRACEFUL_STOP="$GRACEFUL_STOP" \
    -e RESULTS_DIR="/results" \
    -e RESULT_PREFIX="$RESULT_PREFIX" \
    grafana/k6:latest run \
      --out "json=/results/${raw_name}" \
      /scripts/load_test.js
  return $?
}

check_apps() {
  local name="$1"
  local base="$2"
  local url="${base}${ENDPOINT_PATH}?${ENDPOINT_QS}"
  echo -n "Checking ${name} endpoint... "
  local code size
  code="$(curl -sS -o /tmp/load_bench_probe_body.json -w '%{http_code}' \
    --max-time 30 -H 'Accept: application/json' "$url" 2>/dev/null || true)"
  size="$(wc -c < /tmp/load_bench_probe_body.json 2>/dev/null | tr -d ' ')"
  if [[ "$code" != "200" ]]; then
    echo "FAIL (HTTP ${code:-unreachable})"
    die "${name} endpoint did not return HTTP 200: ${url}"
  fi
  if ! python3 -c 'import json,sys; json.load(open("/tmp/load_bench_probe_body.json"))' 2>/dev/null; then
    echo "FAIL (invalid JSON)"
    die "${name} endpoint returned non-JSON body"
  fi
  echo "OK (HTTP 200, ${size} bytes)"
}

warmup() {
  local name="$1"
  local base="$2"
  local url="${base}${ENDPOINT_PATH}?${ENDPOINT_QS}"
  echo "  Warm-up ${name} (${WARMUP_REQUESTS} requests, outside k6)..."
  local i code
  for ((i = 1; i <= WARMUP_REQUESTS; i++)); do
    code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 60 \
      -H 'Accept: application/json' -H 'X-Benchmark-Metrics: 1' "$url" 2>/dev/null || true)"
    if [[ "$code" != "200" ]]; then
      echo "    warm-up request ${i} failed (HTTP ${code})"
    fi
  done
}


echo "=== Load / stress benchmark (k6) ==="
echo "Laravel:  ${LARAVEL_BASE}"
echo "Symfony:  ${SYMFONY_BASE}"
echo "VU levels: ${VUS_LEVELS[*]}"
echo "Executor: constant-vus"
echo "Duration: ${DURATION} (metrics from this window only)"
echo "Graceful: ${GRACEFUL_STOP} (finish in-flight iterations; not part of measured window)"
echo "Warm-up:  ${WARMUP_REQUESTS} HTTP requests outside k6"
echo "Sleep:    ${SLEEP_DURATION}s"
echo

[[ -f "$K6_SCRIPT" ]] || die "Missing k6 script: ${K6_SCRIPT}"
resolve_k6
echo "k6 mode:  ${K6_MODE}${K6_BIN:+ (${K6_BIN})}"
echo

mkdir -p "$RESULTS_DIR"
: >"$EXIT_LOG"

check_apps "Laravel" "$LARAVEL_BASE"
check_apps "Symfony" "$SYMFONY_BASE"
echo

STARTED_AT="$(date '+%Y-%m-%d %H:%M:%S %Z')"

for vus in "${VUS_LEVELS[@]}"; do
  for framework in laravel symfony; do
    if [[ "$framework" == "laravel" ]]; then
      base="$LARAVEL_BASE"
    else
      base="$SYMFONY_BASE"
    fi

    prefix="${framework}_${vus}"
    echo "────────────────────────────────────────"
    echo "Running: framework=${framework}  VUs=${vus}"
    echo "  BASE_URL=${base}"
    echo "  Results prefix: ${prefix}"

    warmup "$framework" "$base"

    export BASE_URL="$base"
    export FRAMEWORK="$framework"
    export VUS="$vus"
    export LOAD_LEVEL="$vus"
    export DURATION SLEEP_DURATION GRACEFUL_STOP
    export RESULTS_DIR
    export RESULT_PREFIX="$prefix"

    raw_json="${RESULTS_DIR}/${prefix}_raw.json"
    : >"$raw_json"

    set +e
    run_k6 "$raw_json"
    exit_code=$?
    set -uo pipefail

    echo "${prefix} exit_code=${exit_code}" | tee -a "$EXIT_LOG"
    if [[ "$exit_code" -ne 0 ]]; then
      echo "  WARNING: k6 exited with ${exit_code}. Continuing."
    fi

    for f in "${prefix}_summary.json" "${prefix}_report.txt" "${prefix}_metrics.csv"; do
      if [[ ! -f "${RESULTS_DIR}/${f}" ]]; then
        echo "  WARNING: missing artifact ${f}"
      fi
    done

    echo "  Pausing ${PAUSE_BETWEEN_TESTS_SEC}s before next scenario..."
    sleep "$PAUSE_BETWEEN_TESTS_SEC"
    echo
  done
done

echo "=== Merging reports ==="
python3 "${SCRIPT_DIR}/merge_load_benchmark_report.py" \
  --results-dir "$RESULTS_DIR" \
  --summary-csv "$SUMMARY_CSV" \
  --report-md "$REPORT_MD" \
  --started-at "$STARTED_AT" \
  --laravel-base "$LARAVEL_BASE" \
  --symfony-base "$SYMFONY_BASE" \
  --exit-log "$EXIT_LOG" \
  --vus-levels "${VUS_LEVELS[*]}" \
  --duration "$DURATION" \
  --sleep "$SLEEP_DURATION" \
  --warmup-requests "$WARMUP_REQUESTS"

echo
echo "Results directory: ${RESULTS_DIR}"
echo "Summary CSV:       ${SUMMARY_CSV}"
echo "Report Markdown:   ${REPORT_MD}"
echo "Done."
