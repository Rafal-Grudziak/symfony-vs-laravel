#!/usr/bin/env bash
set -euo pipefail

# Usage: ./check_api_responses.sh [path to .env]
ENV_FILE=${1:-$(dirname "$0")/env.example}
if [[ -f "$ENV_FILE" ]]; then
  set -o allexport
  source "$ENV_FILE"
  set +o allexport
else
  echo "Env file not found: $ENV_FILE" >&2
  exit 2
fi

TMPDIR=$(mktemp -d)
trap 'rm -rf "$TMPDIR"' EXIT

ENDPOINTS=(
  "/api/tasks"
  "/api/tasks?with=project,comments,tags"
  "/api/projects"
  "/api/reports/tasks-per-project"
  "/api/reports/top-projects"
  "/api/reports/complex-task-overview"
)

fetch_and_normalize(){
  local url="$1" out="$2"
  curl -sS -H "Accept: application/json" "$url" | \
    jq -c . > "$out.raw.json" || { echo "Invalid JSON from $url"; return 2; }

  # Convert to path:type lines
  jq -r 'paths(scalars) as $p | [$p|map(tostring)|join("."), (getpath($p)|type)] | @tsv' "$out.raw.json" | sort > "$out.paths.txt"
}

echo "Comparing API responses between $LARAVEL_HOST and $SYMFONY_HOST"
FAIL=0
for ep in "${ENDPOINTS[@]}"; do
  echo "- Endpoint: $ep"
  url_l="$LARAVEL_HOST$ep"
  url_s="$SYMFONY_HOST$ep"
  out_l="$TMPDIR/laravel$(echo "$ep"|sed 's/[^a-zA-Z0-9]/_/g')"
  out_s="$TMPDIR/symfony$(echo "$ep"|sed 's/[^a-zA-Z0-9]/_/g')"
  fetch_and_normalize "$url_l" "$out_l"
  fetch_and_normalize "$url_s" "$out_s"

  diff -u "$out_l.paths.txt" "$out_s.paths.txt" || {
    echo "  Differences found for $ep";
    FAIL=1
  }
done

if [[ $FAIL -ne 0 ]]; then
  echo "API response structural differences detected." >&2
  exit 3
fi
echo "API responses structures appear equivalent (path:type level)."
