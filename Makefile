# ╔══════════════════════════════════════════════════════════════════╗
# ║                    VIVA VTU — Makefile                         ║
# ╚══════════════════════════════════════════════════════════════════╝

.PHONY: help install dev build test lint logs clean migrate seed

# ─── Default ──────────────────────────────────────────────────────
.DEFAULT_GOAL := help

# ─── Setup ────────────────────────────────────────────────────────
help: ## Show this help message
	@echo "╔══════════════════════════════════════════════════════════╗"
	@echo "║              VIVA VTU — Available Commands              ║"
	@echo "╚══════════════════════════════════════════════════════════╝"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Install all dependencies
	@echo "📦 Installing API Gateway dependencies..."
	cd backend/api-gateway && npm install
	@echo "📦 Installing Auth Service dependencies..."
	cd backend/auth-service && npm install
	@echo "📦 Installing Laravel Service dependencies..."
	cd backend/laravel-service && composer install
	@echo "📦 Installing Django Service dependencies..."
	cd backend/django-service && pip install -r requirements.txt
	@echo "📦 Installing Frontend dependencies..."
	cd frontend && npm install
	@echo "✅ All dependencies installed!"

# ─── Development ──────────────────────────────────────────────────
dev: ## Start all services in development mode (Docker)
	docker compose up
	@echo "🚀 All services starting..."
	@echo "   Gateway:       http://localhost:3000"
	@echo "   Auth Service:  http://localhost:3001"
	@echo "   Billing:       http://localhost:8000"
	@echo "   Analytics:     http://localhost:8001"
	@echo "   Frontend:      http://localhost:3002"
	@echo "   MailHog:       http://localhost:8025"
	@echo "   Swagger:       http://localhost:8000/api-docs"
	@echo "   DRF Docs:      http://localhost:8001/api/v1/docs/"

dev-d: ## Start all services in detached mode
	docker compose up -d

build: ## Build all Docker containers
	docker compose build

# ─── Database ─────────────────────────────────────────────────────
migrate: ## Run database migrations for all services
	@echo "🔄 Running Auth Service migrations..."
	cd backend/auth-service && npx prisma migrate deploy
	@echo "🔄 Running Billing Service migrations..."
	cd backend/laravel-service && php artisan migrate --force
	@echo "🔄 Running Analytics Service migrations..."
	cd backend/django-service && python manage.py migrate
	@echo "✅ All migrations complete!"

seed: ## Seed database
	@echo "🌱 Seeding Billing Service..."
	cd backend/laravel-service && php artisan db:seed --force
	@echo "✅ Seeding complete!"

# ─── Testing ──────────────────────────────────────────────────────
test: ## Run all test suites
	@echo "🧪 Testing Auth Service..."
	cd backend/auth-service && npm test
	@echo "🧪 Testing Billing Service..."
	cd backend/laravel-service && ./vendor/bin/pest
	@echo "🧪 Testing Django Service..."
	cd backend/django-service && python manage.py test
	@echo "🧪 Testing Frontend..."
	cd frontend && npm run lint
	@echo "✅ All tests complete!"

test-auth: ## Run auth service tests
	cd backend/auth-service && npm test

test-billing: ## Run billing service tests
	cd backend/laravel-service && ./vendor/bin/pest

test-analytics: ## Run django analytics tests
	cd backend/django-service && python manage.py test

# ─── Code Quality ─────────────────────────────────────────────────
lint: ## Lint all services
	cd backend/api-gateway && npm run lint
	cd backend/auth-service && npm run lint
	cd backend/laravel-service && ./vendor/bin/pint --test
	cd frontend && npm run lint

format: ## Format code across all services
	cd backend/api-gateway && npx prettier --write "src/**/*.{js,json}"
	cd backend/auth-service && npx prettier --write "src/**/*.{js,json}"
	cd frontend && npx prettier --write "src/**/*.{ts,tsx,json}"

typecheck: ## Run TypeScript checks
	cd backend/api-gateway && npx tsc --noEmit
	cd backend/auth-service && npx tsc --noEmit
	cd frontend && npm run type-check

# ─── Logging ──────────────────────────────────────────────────────
logs: ## Tail all service logs
	docker compose logs -f

logs-gateway: ## Tail API gateway logs
	docker compose logs -f api-gateway

logs-auth: ## Tail auth service logs
	docker compose logs -f auth-service

logs-billing: ## Tail billing service logs
	docker compose logs -f billing-service

logs-analytics: ## Tail analytics service logs
	docker compose logs -f analytics-service

# ─── Utilities ────────────────────────────────────────────────────
artisan: ## Run Laravel artisan command (usage: make artisan cmd=migrate)
	cd backend/laravel-service && php artisan $(cmd)

manage: ## Run Django manage.py command (usage: make manage cmd=createsuperuser)
	cd backend/django-service && python manage.py $(cmd)

prisma: ## Run Prisma command (usage: make prisma cmd=generate)
	cd backend/auth-service && npx prisma $(cmd)

shell-auth: ## Open auth service container shell
	docker compose exec auth-service sh

shell-billing: ## Open billing service container shell
	docker compose exec billing-service sh

shell-analytics: ## Open analytics service container shell
	docker compose exec analytics-service sh

shell-mysql: ## Open MySQL shell
	docker compose exec mysql mysql -u root -p

redis-cli: ## Open Redis CLI
	docker compose exec redis redis-cli -a $(REDIS_PASSWORD:-secret)

# ─── Cleanup ──────────────────────────────────────────────────────
down: ## Stop all services
	docker compose down

clean: ## Stop all services and remove volumes
	docker compose down -v --remove-orphans
	@echo "🧹 Cleaned up all containers and volumes"

clean-all: ## Clean everything including images
	docker compose down -v --rmi all --remove-orphans
	@echo "🧹 Deep clean complete"

prune: ## Remove unused Docker resources
	docker system prune -f
	docker volume prune -f
