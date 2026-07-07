#!/usr/bin/env bash
set -euo pipefail

# Usage: ./check_benchmark_headers.sh [path to .env]
ENV_FILE=${1:-$(dirname "$0")/env.example}
if [[ -f "$ENV_FILE" ]]; then
  set -o allexport
  source "$ENV_FILE"
  set +o allexport
else
  echo "Env file not found: $ENV_FILE" >&2
  exit 2
fi

ENDPOINTS=("/api/tasks" "/api/tasks?with=project,comments,tags")

echo "Checking benchmark headers for Laravel ($LARAVEL_HOST) and Symfony ($SYMFONY_HOST)"
for ep in "${ENDPOINTS[@]}"; do
  echo "- $ep"
  for host in LARAVEL_HOST SYMFONY_HOST; do
    url="${!host}$ep"
    echo "  Requesting $url"
    # -i prints headers; use -sS to avoid progress
    headers=$(curl -sS -D - -H "X-Benchmark-Metrics: 1" -H "Accept: application/json" "$url" -o /dev/null || true)
    echo "$headers" | grep -i "X-Query-Count:" || echo "    MISSING X-Query-Count"
    echo "$headers" | grep -i "X-Response-Time-Ms:" || echo "    MISSING X-Response-Time-Ms"
  done
done
