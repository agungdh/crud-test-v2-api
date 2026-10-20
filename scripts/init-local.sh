#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
    echo "Missing .env file." >&2
    exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

docker compose exec -T minio mcli alias set local "$AWS_ENDPOINT" "$AWS_ACCESS_KEY_ID" "$AWS_SECRET_ACCESS_KEY"
docker compose exec -T minio mcli mb --ignore-existing "local/$AWS_BUCKET"
docker compose exec -T minio mcli mb --ignore-existing "local/$AWS_TEMP_BUCKET"
docker compose exec -T minio mcli anonymous set none "local/$AWS_BUCKET"
docker compose exec -T minio mcli anonymous set none "local/$AWS_TEMP_BUCKET"
docker compose exec -T minio mcli cors set "local/$AWS_BUCKET" - < docker/minio/cors.xml
docker compose exec -T minio mcli cors set "local/$AWS_TEMP_BUCKET" - < docker/minio/cors.xml

expiry_days="${AWS_TEMP_EXPIRY_DAYS:-1}"
docker compose exec -T minio mcli ilm rm --all --force "local/$AWS_TEMP_BUCKET" >/dev/null 2>&1 || true
docker compose exec -T minio mcli ilm add --expire-days "$expiry_days" "local/$AWS_TEMP_BUCKET"

if ! docker compose exec -T postgres psql -U "$DB_USERNAME" -d postgres -tAc "select 1 from pg_database where datname = 'crud_test'" | grep -q 1; then
    docker compose exec -T postgres createdb -U "$DB_USERNAME" crud_test
fi
