#!/usr/bin/env bash
set -euo pipefail

# Usage: ./check_dataset_counts.sh [path to .env]
ENV_FILE=${1:-$(dirname "$0")/env.example}
if [[ -f "$ENV_FILE" ]]; then
  set -o allexport
  source "$ENV_FILE"
  set +o allexport
else
  echo "Env file not found: $ENV_FILE" >&2
  exit 2
fi

MYSQL=${MYSQL_CMD:-mysql}

echo "Counting Laravel database ($LARAVEL_DB_NAME) rows"
for t in users projects tags tasks comments task_tag; do
  cnt=$($MYSQL -h "$LARAVEL_DB_HOST" -P "$LARAVEL_DB_PORT" -u "$LARAVEL_DB_USER" -p"$LARAVEL_DB_PASS" -N -e "SELECT COUNT(*) FROM $LARAVEL_DB_NAME.$t;" 2>/dev/null || echo "-" )
  echo "laravel.$t: $cnt"
done

echo "Counting Symfony database ($SYMFONY_DB_NAME) rows"
for t in users projects tags tasks comments task_tag; do
  cnt=$($MYSQL -h "$SYMFONY_DB_HOST" -P "$SYMFONY_DB_PORT" -u "$SYMFONY_DB_USER" -p"$SYMFONY_DB_PASS" -N -e "SELECT COUNT(*) FROM $SYMFONY_DB_NAME.$t;" 2>/dev/null || echo "-" )
  echo "symfony.$t: $cnt"
done

echo "Validation rules (expected scale): users~100, projects~50, tags~100, tasks~10000, comments~50000"
