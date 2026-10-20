SHELL := /bin/bash

.PHONY: up down init reset

up:
	docker compose up -d --wait --remove-orphans

down:
	docker compose down

init: up
	./scripts/init-local.sh

reset:
	./scripts/reset-local.sh
