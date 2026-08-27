#!/bin/bash

set -u


LARAVEL_URL="${LARAVEL_URL:-http://localhost:8080/api/tasks}"
SYMFONY_URL="${SYMFONY_URL:-http://localhost:8081/api/tasks}"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
OUT_JSON="${SCRIPT_DIR}/validation_results.json"
TMP_DIR="$(mktemp -d -t valtest.XXXXXX)"

CMP_LINES=""

_LAST_HTTP_STATUS="000"

need_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Error: required tool '$1' was not found in PATH." >&2
    if [ "$1" = "jq" ]; then
      echo "Install on macOS with: brew install jq" >&2
    fi
    exit 1
  fi
}

need_cmd curl
need_cmd jq

cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

make_long_title() {
  local n="${1-300}"
  local out=""
  local chunk="XXXXXXXXXX"
  local i=0
  while [ "$i" -lt "$n" ]; do
    out="${out}${chunk}"
    i=$((i + 10))
  done
  printf '%s' "$out" | cut -c "1-${n}"
}

build_framework_result() {
  local body_file="$1"
  local http_status="$2"
  local response_time_ms="$3"

  if [ ! -f "$body_file" ]; then
    printf '%s\n' '{}' >"$body_file"
  fi

  jq -n \
    --arg st "$http_status" \
    --arg rt "$response_time_ms" \
    --slurpfile body "$body_file" '
    def body_raw:
      if ($body | length) == 0 then null
      else $body[0]
      end;

    def valid_json:
      body_raw != null;

    # Laravel-style: {"errors":{"title":["..."],"status":["..."]}}
    # Count every message string, not just the number of keys.
    def err_count_from_errors:
      if ((body_raw // {}) | .errors | type) == "object" then
        ([ (body_raw.errors)[]
           | if type == "array" then (.[] | tostring | select(length > 0))
             elif type == "string" then .
             else empty end
         ] | length)
      elif ((body_raw // {}) | .violations | type) == "array" then
        (body_raw.violations | length)
      else
        0
      end;

    # Symfony denormalization often returns only "message" with several
    # sentences separated by newlines — each non-empty line = one error.
    # Never treat a multi-line "message" as a single error.
    def err_count_from_message:
      if ((body_raw // {}) | .message | type) == "string"
         and ((body_raw.message | length) > 0) then
        ([ body_raw.message
           | gsub("\r\n"; "\n")
           | split("\n")[]
           | gsub("^\\s+|\\s+$"; "")
           | select(length > 0)
         ] | length)
      else
        0
      end;

    def err_count:
      (err_count_from_errors) as $c
      | if $c > 0 then $c else err_count_from_message end;

    def err_fields:
      if ((body_raw // {}) | .errors | type) == "object" then
        [ body_raw.errors | keys[] ]
      elif ((body_raw // {}) | .violations | type) == "array" then
        [ body_raw.violations[] | (.propertyPath // .property // empty) ]
          | map(select(length > 0)) | unique
      else
        []
      end;

    def msg:
      if ((body_raw // {}) | .message | type) == "string" then body_raw.message
      elif ((body_raw // {}) | .detail | type) == "string" then body_raw.detail
      elif ((body_raw // {}) | .title | type) == "string" then body_raw.title
      else null
      end;

    def stnum: (try ($st | tonumber) catch 0);

    def has_val:
      (err_count > 0) or (stnum == 422) or (stnum == 400);

    {
      http_status: (try ($st | tonumber) catch $st),
      response_time_ms: (try ($rt | tonumber) catch $rt),
      valid_json: valid_json,
      has_validation_errors: has_val,
      error_count: err_count,
      error_fields: err_fields,
      message: msg,
      body: (if valid_json then body_raw else ($body[0] | tostring) end)
    }
  ' 2>/dev/null || jq -n \
      --arg st "$http_status" \
      --arg rt "$response_time_ms" \
      --rawfile raw "$body_file" '
    {
      http_status: (try ($st | tonumber) catch $st),
      response_time_ms: (try ($rt | tonumber) catch $rt),
      valid_json: false,
      has_validation_errors: false,
      error_count: 0,
      error_fields: [],
      message: "curl request failed or invalid JSON",
      body: $raw
    }'
}

do_request() {
  local url="$1"
  local body_file="$2"
  local payload="$3"
  local is_raw="${4-0}"

  local curl_out curl_ec=0
  if [ "$is_raw" = "1" ]; then
    curl_out=$(curl -sS -o "$body_file" \
      --max-time 30 \
      -X POST "$url" \
      -H "Accept: application/json" \
      -H "Content-Type: application/json" \
      --data-binary "$payload" \
      -w '%{http_code} %{time_total}' \
      2>/dev/null) || curl_ec=$?
  else
    curl_out=$(curl -sS -o "$body_file" \
      --max-time 30 \
      -X POST "$url" \
      -H "Accept: application/json" \
      -H "Content-Type: application/json" \
      -d "$payload" \
      -w '%{http_code} %{time_total}' \
      2>/dev/null) || curl_ec=$?
  fi

  if [ "$curl_ec" -ne 0 ]; then
    _LAST_HTTP_STATUS="000"
    _LAST_TIME_MS="0.00"
    if [ ! -s "$body_file" ]; then
      printf '{"_client_error":"curl_failed","curl_exit":%s}\n' "$curl_ec" >"$body_file"
    fi
  else
    _LAST_HTTP_STATUS=$(printf '%s' "$curl_out" | awk '{print $1}')
    _LAST_TIME_MS=$(awk -v t="$(printf '%s' "$curl_out" | awk '{print $2}')" \
      'BEGIN { printf "%.2f", (t + 0) * 1000 }')
  fi
}

run_pair() {
  local scenario="$1"
  local payload="$2"
  local is_raw="${3-0}"

  local lar_body sym_body
  lar_body="${TMP_DIR}/laravel_${scenario}.json"
  sym_body="${TMP_DIR}/symfony_${scenario}.json"

  echo "────────────────────────────────────────"
  echo "Scenario: ${scenario}"

  do_request "$LARAVEL_URL" "$lar_body" "$payload" "$is_raw"
  local lar_st lar_ms
  lar_st="$_LAST_HTTP_STATUS"
  lar_ms="$_LAST_TIME_MS"
  local lar_obj
  lar_obj=$(build_framework_result "$lar_body" "$lar_st" "$lar_ms")
  local lar_errs
  lar_errs=$(printf '%s' "$lar_obj" | jq -r '.error_count')

  printf '%-7s | %-24s | HTTP %-3s | %8s ms | errors: %s\n' \
    "Laravel" "$scenario" "$lar_st" "$lar_ms" "$lar_errs"

  do_request "$SYMFONY_URL" "$sym_body" "$payload" "$is_raw"
  local sym_st sym_ms
  sym_st="$_LAST_HTTP_STATUS"
  sym_ms="$_LAST_TIME_MS"
  local sym_obj
  sym_obj=$(build_framework_result "$sym_body" "$sym_st" "$sym_ms")
  local sym_errs
  sym_errs=$(printf '%s' "$sym_obj" | jq -r '.error_count')

  printf '%-7s | %-24s | HTTP %-3s | %8s ms | errors: %s\n' \
    "Symfony" "$scenario" "$sym_st" "$sym_ms" "$sym_errs"

  CMP_LINES="${CMP_LINES}${scenario}|${lar_st}|${sym_st}"$'\n'

  local entry
  entry=$(jq -n \
    --arg scenario "$scenario" \
    --arg payload "$payload" \
    --argjson laravel "$lar_obj" \
    --argjson symfony "$sym_obj" '
    {
      scenario: $scenario,
      request_payload: (try ($payload | fromjson) catch $payload),
      laravel: $laravel,
      symfony: $symfony
    }
  ')

  printf '%s\n' "$entry" >>"${TMP_DIR}/tests.ndjson"
}


LONG_TITLE="$(make_long_title 300)"
STARTED_AT="$(date '+%Y-%m-%d %H:%M:%S %Z')"

echo "=== Validation comparison: Laravel vs Symfony ==="
echo "Laravel:  ${LARAVEL_URL}"
echo "Symfony:  ${SYMFONY_URL}"
echo "Output:   ${OUT_JSON}"
echo

: >"${TMP_DIR}/tests.ndjson"

run_pair empty_payload '{}'

run_pair missing_title \
  '{"description":"Test description","status":"todo","project_id":1}'

run_pair empty_title \
  '{"title":"","description":"Test description","status":"todo","project_id":1}'

run_pair title_too_long \
  "$(printf '{"title":"%s","description":"Test description","status":"todo","project_id":1}' "$LONG_TITLE")"

run_pair invalid_status \
  '{"title":"Test task","description":"Test description","status":"not_a_valid_status","project_id":1}'

run_pair invalid_project_id_type \
  '{"title":"Test task","description":"Test description","status":"todo","project_id":"abc"}'

run_pair nonexistent_project \
  '{"title":"Test task","description":"Test description","status":"todo","project_id":999999}'


run_pair multiple_errors \
  '{"project_id":1,"title":"","status":"invalid","priority":"invalid"}'

run_pair malformed_json '{"title":"Test"' 1

FINISHED_AT="$(date '+%Y-%m-%d %H:%M:%S %Z')"

jq -n \
  --arg started "$STARTED_AT" \
  --arg finished "$FINISHED_AT" \
  --arg laravel_url "$LARAVEL_URL" \
  --arg symfony_url "$SYMFONY_URL" \
  --slurpfile tests "${TMP_DIR}/tests.ndjson" '
  {
    meta: {
      started_at: $started,
      finished_at: $finished,
      laravel_url: $laravel_url,
      symfony_url: $symfony_url,
      method: "POST",
      endpoint: "/api/tasks"
    },
    tests: $tests
  }
' >"$OUT_JSON"

echo
echo "=== HTTP status comparison ==="
printf '%-24s | %-10s | %-10s\n' "scenario" "Laravel" "Symfony"
printf '%-24s-+-%-10s-+-%-10s\n' "------------------------" "----------" "----------"

OLDIFS=$IFS
IFS='
'
for line in $CMP_LINES; do
  [ -z "$line" ] && continue
  sc=$(printf '%s' "$line" | awk -F'|' '{print $1}')
  la=$(printf '%s' "$line" | awk -F'|' '{print $2}')
  sy=$(printf '%s' "$line" | awk -F'|' '{print $3}')
  printf '%-24s | %-10s | %-10s\n' "$sc" "$la" "$sy"
done
IFS=$OLDIFS

echo
echo "All results written to: ${OUT_JSON}"
echo "Done."
