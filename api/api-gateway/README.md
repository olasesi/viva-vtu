# Viva VTU API Gateway

Single entry point for the Viva VTU microservices. Express + `http-proxy-middleware` reverse proxy with JWT-aware authentication, rate limiting, structured logging and OpenAPI documentation.

## Architecture

```
Client (Frontend / Mobile / POS / 3rd-party)
        │
        ▼
┌─────────────────────────────┐
│  API Gateway (:3000)        │
│  helmet · cors · gzip       │
│  rateLimit · requestLogger  │
│  /health · /api-docs        │
│  optionalAuth (JWT) ──► sets X-User-* headers + X-Auth-Hash for Laravel
└────────────┬────────────────┘
             │ (proxy)
   ┌─────────┼──────────┬─────────────┐
   ▼         ▼          ▼             ▼
  Auth      Billing    Analytics    (webhooks)
 :3001      :8000       :8001
 (Postgres) (MySQL)    (Django)
```

The gateway is **stateless**: verified JWTs are forwarded as `X-User-Id`, `X-User-Email`, `X-User-Role` and `X-Forwarded-User`. Laravel also accepts the caller's JWT via an `X-Auth-Hash`/`X-User-Id` pair it re-verifies against the Auth service.

## Routing table

| Incoming path             | Target            | Forwarded path               |
| ------------------------- | ----------------- | ---------------------------- |
| `/api/auth/*`             | Auth              | `/{path}` (prefix stripped)  |
| `/api/analytics/*`        | Analytics         | `/{path}` (prefix stripped)  |
| `/api/billing/*`          | Billing (Laravel) | `/api/{path}` (legacy alias) |
| `/api/wallet/*`           | Billing (Laravel) | unchanged                    |
| `/api/purchase/*`         | Billing (Laravel) | unchanged                    |
| `/api/services/*`         | Billing (Laravel) | unchanged                    |
| `/api/transactions/*`     | Billing (Laravel) | unchanged                    |
| `/api/service-requests/*` | Billing (Laravel) | unchanged                    |
| `/api/settings/*`         | Billing (Laravel) | unchanged                    |
| `/api/admin/*`            | Billing (Laravel) | unchanged                    |
| `/api/webhook/*`          | Billing (Laravel) | unchanged, no auth           |

`/api/wallet/*`, `/api/purchase/*`, `/api/services/*`, `/api/transactions/*`, `/api/service-requests/*`, `/api/settings/*` and `/api/admin/*` are forwarded **unchanged** because Laravel is mounted at `/api`. `pathRewrite` restores `req.originalUrl` after Express strips the mount segment. Query strings are preserved.

## Configuration

| Env var                 | Default                   | Purpose                       |
| ----------------------- | ------------------------- | ----------------------------- |
| `PORT`                  | `3000`                    | Gateway listen port           |
| `NODE_ENV`              | `development`             | Runtime mode                  |
| `JWT_SECRET`            | `change-me-in-production` | Verify incoming bearer tokens |
| `AUTH_SERVICE_URL`      | `http://auth:3001`        | Auth (Node/Prisma) target     |
| `BILLING_SERVICE_URL`   | `http://billing:8000`     | Billing (Laravel) target      |
| `ANALYTICS_SERVICE_URL` | `http://analytics:8001`   | Analytics (Django) target     |
| `RATE_LIMIT_WINDOW_MS`  | `900000`                  | Rate-limit window             |
| `RATE_LIMIT_MAX`        | `100`                     | Max requests per window       |
| `CORS_ORIGIN`           | `*`                       | Allowed CORS origin           |
| `LOG_LEVEL`             | `info`                    | Winston level                 |
| `LOG_DIR`               | `logs`                    | Rotated log directory         |

## Running locally

```bash
npm install
npm start          # http://localhost:3000
npm run dev        # nodemon
```

Verify:

```bash
curl http://localhost:3000/health
```

Open the interactive docs at `http://localhost:3000/api-docs`.

## Testing

```bash
npm test           # jest + supertest, stub target servers, proxy.js 100% stmts
npm run lint       # eslint (src/)
npm run format:check
```

Tests boot real `http.createServer` stubs for each downstream service and assert
path mapping, the legacy `/api/billing` rewrite, query-string preservation,
`X-User-*` header injection and 502 fallback when a target is unreachable.

## Deploy

- Build: `docker build -t viva-vtu-gateway .`
- Run with `docker-compose` in the platform stack (see `../docker-compose.yml`).
- Kubernetes manifests: see `k8s/` (matching the other services).
- The gateway is a thin stateless layer: deploy it **before** the billing service
  is reachable (it returns 502 and logs until targets come up).

## Scale

- Horizontally scalible: run N replicas behind the load balancer; no shared state.
- Caveat: the rate limiter (`express-rate-limit`) is **in-memory per instance**.
  At scale, either pin clients to an instance, raise `RATE_LIMIT_MAX`, or swap to
  a shared store (Redis) — see `src/middleware/rateLimiter.js`.
- 30s connect/proxy timeouts keep dead targets from piling up requests.

## Monitoring

- `/health` — liveness (status, timestamp, uptime) for LB and K8s probes.
- Structured JSON logs via Winston (daily rotation in `LOG_DIR`): proxied route,
  target, response code, and full 502 diagnostics on proxy errors.
- Request logger emits per-request `duration` for latency tracking.
- Add Sentry/APM by wrapping `logger.error` in `src/config/logger.js`.
