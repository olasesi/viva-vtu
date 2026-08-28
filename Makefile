# ╔══════════════════════════════════════════════════════════════════╗
# ║                    VIVA VTU — Makefile                         ║
# ║      Full-stack monorepo: Next.js frontend (root) + api/        ║
# ╚══════════════════════════════════════════════════════════════════╝

.PHONY: help install dev build test lint logs clean migrate seed docs monitor

# ─── Default ──────────────────────────────────────────────────────
.DEFAULT_GOAL := help

# ─── Help ─────────────────────────────────────────────────────────
help: ## Show this help message
	@echo "╔══════════════════════════════════════════════════════════╗"
	@echo "║              VIVA VTU — Available Commands              ║"
	@echo "╚══════════════════════════════════════════════════════════╝"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ═══════════════════════════════════════════════════════════════════
#  DEVELOPMENT
# ═══════════════════════════════════════════════════════════════════
install: ## Install all dependencies (frontend + all microservices)
	@echo "📦 Installing Frontend dependencies..."
	npm install
	@echo "📦 Installing API Gateway dependencies..."
	cd api/api-gateway && npm install
	@echo "📦 Installing Auth Service dependencies..."
	cd api/auth-service && npm install
	@echo "📦 Installing Laravel Service dependencies..."
	cd api/laravel-service && composer install
	@echo "📦 Installing Django Service dependencies..."
	cd api/django-service && pip install -r requirements.txt
	@echo "✅ All dependencies installed!"

# ─── Frontend (root) ──────────────────────────────────────────────
dev: ## Start frontend dev server (Next.js) at http://localhost:3002
	npm run dev

dev-docker: ## Start ALL services via Docker Compose
	docker compose up
	@echo "🚀 All services starting at:"
	@echo "   Gateway:       http://localhost:3000"
	@echo "   Auth Service:  http://localhost:3001"
	@echo "   Billing:       http://localhost:8000"
	@echo "   Analytics:     http://localhost:8001"
	@echo "   Frontend:      http://localhost:3002"
	@echo "   MailHog:       http://localhost:8025"
	@echo "   Swagger:       http://localhost:8000/api-docs"
	@echo "   DRF Docs:      http://localhost:8001/api/v1/docs/"

build: ## Build all Docker containers
	docker compose build

start: ## Build & start Next.js production server (root)
	npm run build && npm run start

# ═══════════════════════════════════════════════════════════════════
#  DATABASE
# ═══════════════════════════════════════════════════════════════════
migrate: ## Run database migrations for all microservices
	@echo "🔄 Running Auth Service migrations..."
	cd api/auth-service && npx prisma migrate deploy
	@echo "🔄 Running Billing Service migrations..."
	cd api/laravel-service && php artisan migrate --force
	@echo "🔄 Running Analytics Service migrations..."
	cd api/django-service && python manage.py migrate
	@echo "✅ All migrations complete!"

seed: ## Seed database with default data
	@echo "🌱 Seeding Billing Service..."
	cd api/laravel-service && php artisan db:seed --force
	@echo "🌱 Seeding Django..."
	cd api/django-service && python manage.py seed
	@echo "✅ Seeding complete!"

# ═══════════════════════════════════════════════════════════════════
#  TESTING
# ═══════════════════════════════════════════════════════════════════
test: ## Run all test suites (frontend + all microservices)
	@echo "🧪 Testing Frontend..."
	npm run lint
	@echo "🧪 Testing Auth Service..."
	cd api/auth-service && npm test
	@echo "🧪 Testing Billing Service..."
	cd api/laravel-service && ./vendor/bin/pest
	@echo "🧪 Testing Django Service..."
	cd api/django-service && python manage.py test
	@echo "✅ All tests complete!"

test-frontend: ## Run frontend lint + type check
	npm run lint
	npm run type-check

test-auth: ## Run auth service tests
	cd api/auth-service && npm test

test-billing: ## Run billing service tests
	cd api/laravel-service && ./vendor/bin/pest

test-analytics: ## Run django analytics tests
	cd api/django-service && python manage.py test

# ═══════════════════════════════════════════════════════════════════
#  LINTING / FORMATTING / TYPECHECK
# ═══════════════════════════════════════════════════════════════════
lint: ## Lint ALL services + frontend
	@echo "🧹 Linting Frontend..."
	npm run lint
	@echo "🧹 Linting API Gateway..."
	cd api/api-gateway && npm run lint
	@echo "🧹 Linting Auth Service..."
	cd api/auth-service && npm run lint
	@echo "🧹 Linting Laravel Service..."
	cd api/laravel-service && ./vendor/bin/pint --test
	@echo "✅ Lint complete!"

format: ## Auto-format code across all services + frontend
	npx prettier --write "src/**/*.{ts,tsx,js,json,css,md}"
	cd api/api-gateway && npx prettier --write "src/**/*.{js,json}"
	cd api/auth-service && npx prettier --write "src/**/*.{js,json}"
	cd api/laravel-service && ./vendor/bin/pint
	cd api/django-service && python -m black analytics reports config

typecheck: ## Run TypeScript checks (frontend + node services)
	npm run type-check
	cd api/api-gateway && npx tsc --noEmit
	cd api/auth-service && npx tsc --noEmit

# ═══════════════════════════════════════════════════════════════════
#  LOGGING & MONITORING
# ═══════════════════════════════════════════════════════════════════
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

logs-frontend: ## Tail frontend logs
	docker compose logs -f frontend

monitor: ## Open monitoring stack (metrics endpoints)
	@echo "🔍 Monitoring endpoints:"
	@echo "   Gateway metrics:   http://localhost:3000/metrics"
	@echo "   Auth metrics:      http://localhost:3001/metrics"
	@echo "   Django admin:      http://localhost:8001/admin/"

# ═══════════════════════════════════════════════════════════════════
#  DOCUMENTATION
# ═══════════════════════════════════════════════════════════════════
docs: ## Generate all API documentation
	@echo "📚 Docs are served live at:"
	@echo "   API Gateway Swagger:  http://localhost:3000/api-docs"
	@echo "   Laravel Swagger:      http://localhost:8000/api-docs"
	@echo "   Django (DRF):         http://localhost:8001/api/v1/docs/"

apidocs: ## Regenerate API specs
	cd api/laravel-service && php artisan l5-swagger:generate
	cd api/django-service && python manage.py spectacular --file schema.yml

# ═══════════════════════════════════════════════════════════════════
#  DEPLOYMENT AN TOOLS
# ═══════════════════════════════════════════════════════════════════
artisan: ## Run Laravel artisan (usage: make artisan cmd=migrate)
	cd api/laravel-service && php artisan $(cmd)

manage: ## Run Django manage.py (usage: make manage cmd=createsuperuser)
	cd api/django-service && python manage.py $(cmd)

prisma: ## Run Prisma command (usage: make prisma cmd=generate)
	cd api/auth-service && npx prisma $(cmd)

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

# ═══════════════════════════════════════════════════════════════════
#  CLEANUP
# ═══════════════════════════════════════════════════════════════════
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
