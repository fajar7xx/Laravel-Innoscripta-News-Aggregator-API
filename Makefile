APP_CONTAINER = app

## —— Docker ————————————————————————————————————————
up: ## Start all containers in the background
	docker compose up -d

down: ## Stop and remove all containers
	docker compose down

ps: ## List all containers
	docker compose ps

restart: ## Restart all containers
	docker compose restart

build: ## Build or rebuild images
	docker compose build

logs: ## Tail logs from all containers
	docker compose logs -f

shell: ## Open a bash shell inside the app container
	docker compose exec $(APP_CONTAINER) bash

## —— Laravel ————————————————————————————————————————
artisan: ## Run an Artisan command: make artisan [cmd]
	docker compose exec $(APP_CONTAINER) php artisan $(filter-out $@,$(MAKECMDGOALS))

composer: ## Run a Composer command: make composer [cmd]
	docker compose exec $(APP_CONTAINER) composer $(filter-out $@,$(MAKECMDGOALS))

## —— Common shortcuts ————————————————————————————————
migrate: ## Run database migrations
	docker compose exec $(APP_CONTAINER) php artisan migrate

fresh: ## Drop all tables and re-run all migrations with seeders
	docker compose exec $(APP_CONTAINER) php artisan migrate:fresh --seed

test: ## Run the test suite
	docker compose exec $(APP_CONTAINER) php artisan test --compact

pint: ## Run Laravel Pint code formatter on dirty files
	docker compose exec $(APP_CONTAINER) vendor/bin/pint --dirty --format agent

## —— Help ————————————————————————————————————————————
help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

.DEFAULT_GOAL := help
.PHONY: up down restart build logs shell artisan composer migrate fresh test pint help

%:
	@:
