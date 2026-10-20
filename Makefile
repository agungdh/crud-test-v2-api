SHELL := /bin/bash

.PHONY: up down reset

up:
	docker compose up -d

down:
	docker compose down

reset:
	./scripts/reset-local.sh
