# VIVA VTU — Full-Stack Microservices Platform

A complete VTU (Virtual Top-Up) platform for Nigerians to buy **data, airtime, electricity, and cable TV subscriptions** online.

## 🏗️ Architecture

```
viva-vtu/                     ←  Frontend lives at the root (Next.js)
├── src/                      ←  Next.js App Router source
├── public/                   ←  Next.js static assets
├── api/                      ←  Backend microservices
│   ├── api-gateway/          ←  Node.js/Express — reverse proxy, rate limiting, JWT verification
│   ├── auth-service/         ←  Node.js/Express + Prisma — auth, email, realtime
│   ├── laravel-service/      ←  Laravel 10 — wallet, VTPass, Paystack/Flutterwave
│   ├── django-service/       ←  Django 4.2 + DRF — analytics, reports
│   └── shared/               ←  Shared infra (docker, mysql init)
├── docker-compose.yml        ←  Orchestrates all services
└── Makefile                  ←  Developer toolkit
```

## 🧩 Services

| Service | Tech | Port | Role |
|---------|------|------|------|
| **Frontend** | Next.js 14 | 3002 | All user-facing pages |
| **API Gateway** | Node/Express | 3000 | Routing, rate limiting, auth forwarding |
| **Auth Service** | Node/Express + Prisma | 3001 | Registration, JWT, email verification |
| **Billing Service** | Laravel 10 | 8000 | Wallet, VTPass, Paystack, Flutterwave |
| **Analytics Service** | Django + DRF | 8001 | Reports, analytics, admin data |
| **MySQL** | MySQL 8.0 | 3306 | Shared database (per-service schemas) |
| **Redis** | Redis 7 | 6379 | Cache, sessions, queues |
| **MailHog** | MailHog | 8025/8025 | Email dev capture UI |

## 🚀 Quick Start

```bash
# 1. Configure environment
cp .env.example .env
#    → add your VTPass, Paystack, Flutterwave keys

# 2. Install dependencies
make install

# 3. Start everything (Docker)
make dev-docker

# 4. Run migrations once
make migrate
make seed
```

Frontend: http://localhost:3002 · Gateway: http://localhost:3000 · Swagger docs at `/api-docs`.

## 🧰 Developer Toolkit (`make help`)

- **Development**: `make dev`, `make dev-docker`, `make build`, `make start`
- **Testing**: `make test`, `make test-auth`, `make test-billing`, `make test-analytics`
- **Linting / Formatting**: `make lint`, `make format`, `make typecheck`
- **Logging**: `make logs`, `make logs-gateway`, `make logs-auth`, `make logs-billing`, `make logs-analytics`
- **Monitoring**: `make monitor`, `npm run metrics`
- **Documentation**: `make docs`, `make apidocs`, `npm run docs`
- **Database**: `make migrate`, `make seed`
- **Deployment**: `docker compose -f docker-compose.prod.yml up -d`

## 🛠️ Package Landscape

Each layer ships batteries-included:

- **Development**: nodemon, husky, lint-staged, commitlint, Docker Compose
- **Testing**: Jest (Node + frontend), Pest (Laravel), Django test runner
- **Logging**: Winston (Node), Monolog (Laravel), Django logging — all with rotation
- **Monitoring**: Prometheus-compatible `/metrics`, sentry-sdk, structured logs
- **Linting**: ESLint + Prettier (JS/TS), Laravel Pint, Black (Python)
- **Documentation**: Swagger/OpenAPI on every service, TypeDoc for frontend
- **Deployment**: Multi-stage Dockerfiles, production compose, PM2-style processes

## 🔐 Security

- JWT access/refresh tokens, Redis token blacklist
- Passwords hashed (bcrypt/argon)
- CORS + Helmet headers
- Rate limiting on gateway
