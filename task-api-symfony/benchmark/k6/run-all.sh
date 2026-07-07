#!/usr/bin/env sh
# Run every scenario sequentially (host k6). Example:
#   cd benchmark/k6 && VUS=10 DURATION=15s ./run-all.sh
set -eu
cd "$(dirname "$0")"
mkdir -p ../results
for f in 01-crud.js 02-pagination.js 03-eager-loading.js 04-lazy-loading.js 05-reports.js 06-bulk-inserts.js; do
  echo "========== $f =========="
  k6 run "$f" || echo "WARN: $f exited non-zero"
done
