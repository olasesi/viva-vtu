# Auth Service

Node.js/Express + Prisma (MySQL) microservice handling registration, login, JWT
authentication, email verification, and password recovery for VIVA VTU.

Canonical roles embedded in the JWT: `USER`, `ADMIN`, `AGENT`, `MERCHANT`,
`RESELLER`, `DISTRIBUTOR`, `SUB_RESELLER`. The Laravel gateway reads these roles
from the verified token payload.

## Endpoints

Interactive docs (Swagger UI) at `http://localhost:3001/api-docs`.

| Method | Path                        | Auth   | Description                               |
| ------ | --------------------------- | ------ | ----------------------------------------- |
| POST   | `/api/auth/register`        | —      | Create account (role `USER` by default)   |
| POST   | `/api/auth/login`           | —      | Exchange credentials for token pair       |
| POST   | `/api/auth/refresh`         | —      | Rotate refresh token for new token pair   |
| POST   | `/api/auth/logout`          | Bearer | Blacklist access token, revoke refresh    |
| POST   | `/api/auth/verify`          | Bearer | Verify JWT, return user (Laravel gateway) |
| POST   | `/api/auth/verify-email`    | —      | Confirm email with token                  |
| POST   | `/api/auth/forgot-password` | —      | Request password reset email              |
| POST   | `/api/auth/reset-password`  | —      | Set new password with reset token         |
| GET    | `/api/auth/profile`         | Bearer | Read own profile                          |
| PUT    | `/api/auth/profile`         | Bearer | Update own profile fields                 |
| GET    | `/health`                   | —      | Liveness probe                            |
| GET    | `/readyz`                   | —      | Readiness (DB + Redis)                    |
| GET    | `/metrics`                  | —      | Prometheus metrics                        |

## Prerequisites

- Node.js 20+
- MySQL 8+ (XAMPP/MariaDB works: MariaDB 10.4+)
- Redis 7 (optional for local dev; failures are non-fatal)

## Setup

```bash
npm install
cp .env.example .env   # edit values (see Env Reference below)
npx prisma generate
npx prisma migrate dev # or `npm run prisma:migrate:dev`
```

## Run

```bash
npm run dev        # nodemon
npm start          # plain node
```

Smoke check: `curl http://127.0.0.1:3001/health`.

## Test

Tests run against a **separate local MySQL database** so the dev DB stays clean.
Jest's `globalSetup` runs `prisma migrate deploy` on the test schema automatically.

```powershell
$env:DATABASE_URL="mysql://root:@127.0.0.1:3306/viva_vtu_auth_test"
npm test                 # all suites (61 tests)
npm run test:unit        # utils/controllers/middleware
npm run test:integration # /api/auth flows against real MySQL
npm run test:coverage    # coverage report (84%+ statement)
```

Redis is mocked with `ioredis-mock`; the Bull email queue is stubbed — no SMTP
or live Redis needed to run tests.

## Lint / Format

```bash
npm run lint         # eslint (src + tests)
npm run format       # prettier --write
npm run format:check # verify formatting
```

## Env Reference

| Variable                    | Default                    | Notes                                |
| --------------------------- | -------------------------- | ------------------------------------ |
| `PORT`                      | `3001`                     |                                      |
| `NODE_ENV`                  | `development`              | `production` enables Sentry          |
| `FRONTEND_URL`              | `http://localhost:3000`    | CORS origin                          |
| `DATABASE_URL`              | —                          | Prisma MySQL DSN                     |
| `JWT_ACCESS_SECRET`         | `change-me-...`            | Set strong secret in prod            |
| `JWT_ACCESS_EXPIRY`         | `15m`                      |                                      |
| `JWT_REFRESH_SECRET`        | `change-me-...`            | Set strong secret in prod            |
| `JWT_REFRESH_EXPIRY`        | `7d`                       |                                      |
| `REDIS_HOST` / `REDIS_PORT` | `127.0.0.1` / `6379`       | Blacklist + Bull queue               |
| `REDIS_PASSWORD`            | —                          |                                      |
| `SMTP_HOST` / `SMTP_PORT`   | `smtp.mailtrap.io` / `587` | Outbound email SMTP                  |
| `SMTP_USER` / `SMTP_PASS`   | —                          |                                      |
| `MAIL_FROM`                 | `noreply@vivavtu.com`      |                                      |
| `LOG_LEVEL` / `LOG_DIR`     | `info` / `logs`            | Winston                              |
| `SENTRY_DSN`                | —                          | Only read when `NODE_ENV=production` |
| `SENTRY_TRACES_SAMPLE_RATE` | `0.1`                      |                                      |

## Security Notes

- Access tokens (15m) are stateless JWTs; refresh tokens (7d) persist as SHA-256
  hashes in `refreshtoken` (never the raw JWT).
- Refresh tokens rotate on every call; a reused or revoked token returns `401`.
- Logout blacklists the access token in Redis until natural expiry.
- Passwords hashed with bcrypt (12 rounds). Tokens pinned to the user's current
  JWT secret — rotating secrets invalidates all sessions (intended).

## Deploy

Kubernetes manifests live in `k8s/auth-service/` — see
[`k8s/README.md`](../../k8s/README.md) for the full deploy + monitoring guide.

Docker: `docker build -f api/auth-service/Dockerfile -t ghcr.io/olasesi/viva-vtu/auth-service .`

## Architecture Notes

- Auth → API Gateway (`api-gateway`) verifies JWTs and injects
  `X-User-Id/X-User-Email/X-User-Role` headers for downstream services.
- The Laravel billing service calls `POST /api/auth/verify` through the gateway
  when it needs user identity/role without holding the JWT secret.
