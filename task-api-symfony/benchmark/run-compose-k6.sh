#!/usr/bin/env sh
# Run k6 via Docker Compose with correct permissions for benchmark/results.
# Prerequisites: stack is up (`docker compose up -d`), thesis fixtures loaded.
#
# Usage:
#   ./benchmark/run-compose-k6.sh 01-crud.js
#   COMPOSE_PROFILES=benchmark K6_BASE_URL=http://host.docker.internal:8081 VUS=50 DURATION=30s ./benchmark/run-compose-k6.sh 03-eager-loading.js
set -eu
cd "$(dirname "$0")/.."
mkdir -p benchmark/results
SCRIPT="${1:?pass script name e.g. 01-crud.js}"
shift
# Compose substitutes K6_BASE_URL into the k6 service BASE_URL; allow BASE_URL alias for convenience.
if [ -n "${BASE_URL:-}" ] && [ -z "${K6_BASE_URL:-}" ]; then
  export K6_BASE_URL="$BASE_URL"
fi
exec docker compose -f docker-compose.yml --profile benchmark run --rm \
  --user "$(id -u):$(id -g)" \
  k6 run "$@" "/var/benchmark/k6/${SCRIPT##*/}"
