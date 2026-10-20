#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

docker compose down -v --remove-orphans
docker compose up -d --wait --remove-orphans
./scripts/init-local.sh

php artisan config:clear
php artisan migrate --force
php artisan db:seed --force
