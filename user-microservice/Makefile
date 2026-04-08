.PHONY: up down build logs logs-workers shell install migrate test seed health fresh consume-rmq consume-kafka help

DOCKER_COMPOSE = docker compose
PHP_CONTAINER  = user-service-php
EXEC_PHP       = $(DOCKER_COMPOSE) exec $(PHP_CONTAINER)

help: ## Show available commands
	@echo ""
	@echo "  Event-Driven Microservices — Developer Commands"
	@echo "  ================================================"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ""

up: ## Start all containers
	$(DOCKER_COMPOSE) up -d --build

down: ## Stop all containers
	$(DOCKER_COMPOSE) down

build: ## Rebuild Docker images (no cache)
	$(DOCKER_COMPOSE) build --no-cache

logs: ## Tail all service logs
	$(DOCKER_COMPOSE) logs -f

logs-workers: ## Tail worker logs only
	$(DOCKER_COMPOSE) logs -f notification-worker analytics-worker

logs-api: ## Tail API service logs
	$(DOCKER_COMPOSE) logs -f php nginx

shell: ## Open a shell in the PHP container
	$(EXEC_PHP) bash

install: ## Install Composer dependencies
	$(EXEC_PHP) composer install --no-interaction --optimize-autoloader

migrate: ## Create database and update schema
	$(EXEC_PHP) php bin/console doctrine:database:create --if-not-exists --no-interaction
	$(EXEC_PHP) php bin/console doctrine:schema:update --force --no-interaction

test: ## Run PHPUnit test suite
	$(EXEC_PHP) php bin/phpunit

seed: ## Seed sample user data via API
	@echo "\n  Seeding sample users...\n"
	@curl -s -X POST http://localhost/api/users \
		-H "Content-Type: application/json" \
		-d '{"email":"john.doe@example.com","firstName":"John","lastName":"Doe"}' | python3 -m json.tool
	@echo ""
	@curl -s -X POST http://localhost/api/users \
		-H "Content-Type: application/json" \
		-d '{"email":"jane.smith@example.com","firstName":"Jane","lastName":"Smith"}' | python3 -m json.tool
	@echo ""
	@curl -s -X POST http://localhost/api/users \
		-H "Content-Type: application/json" \
		-d '{"email":"bob.wilson@example.com","firstName":"Bob","lastName":"Wilson"}' | python3 -m json.tool
	@echo "\n  Done!\n"

health: ## Check service health
	@curl -s http://localhost/api/health | python3 -m json.tool

status: ## Show container status
	$(DOCKER_COMPOSE) ps

fresh: ## Full reset — rebuild everything from scratch
	@echo "\n  Tearing down existing containers and volumes..."
	$(DOCKER_COMPOSE) down -v
	@echo "\n  Building and starting all services..."
	$(DOCKER_COMPOSE) up -d --build
	@echo "\n  Waiting for infrastructure services to be ready..."
	@sleep 20
	@echo "\n  Installing Composer dependencies..."
	$(EXEC_PHP) composer install --no-interaction --optimize-autoloader
	@echo "\n  Running database migrations..."
	$(EXEC_PHP) php bin/console doctrine:database:create --if-not-exists --no-interaction
	$(EXEC_PHP) php bin/console doctrine:schema:update --force --no-interaction
	@echo "\n  All services are ready!"
	@echo "\n  API:          http://localhost"
	@echo "  RabbitMQ UI:  http://localhost:15672  (rmq_user / rmq_secret)"
	@echo "  Kafka UI:     http://localhost:8080"
	@echo "  PgAdmin:      http://localhost:5050   (admin@admin.com / admin)"
	@echo ""

consume-rmq: ## Start RabbitMQ consumer manually
	$(EXEC_PHP) php bin/console messenger:consume rabbitmq -vv

consume-kafka: ## Start Kafka consumer manually
	$(EXEC_PHP) php bin/console app:consume-kafka
