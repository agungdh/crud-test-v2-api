#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

docker compose down -v --remove-orphans

docker compose up -d --wait postgres valkey minio
docker compose up -d adminer redis-commander

until curl -fsS http://127.0.0.1:9000/minio/health/live >/dev/null 2>&1; do
    sleep 1
done

docker compose run --rm --no-deps minio-init

if ! docker compose exec -T postgres psql -U admin -d postgres -tAc "select 1 from pg_database where datname = 'crud_test'" | grep -q 1; then
    docker compose exec -T postgres createdb -U admin crud_test
fi

php artisan config:clear
php artisan migrate --force
php artisan db:seed --force
