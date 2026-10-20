#!/bin/sh
set -eu

mcli alias set local http://minio:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"

mcli mb --ignore-existing "local/$AWS_BUCKET"
mcli mb --ignore-existing "local/$AWS_TEMP_BUCKET"

mcli anonymous set none "local/$AWS_BUCKET"
mcli anonymous set none "local/$AWS_TEMP_BUCKET"

mcli cors set "local/$AWS_BUCKET" /config/cors.xml
mcli cors set "local/$AWS_TEMP_BUCKET" /config/cors.xml
