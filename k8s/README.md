# Deploying Viva VTU Auth Service

## Prerequisites

- Kubernetes cluster (managed or self-hosted)
- `kubectl` configured for the target cluster
- Ingress controller (nginx recommended)
- Prometheus + Grafana stack (with Prometheus Operator CRDs for PrometheusRule/ServiceMonitor)
- Container registry access to `ghcr.io/olasesi/viva-vtu/auth-service`
- Secrets provisioned (see below)

## 1. Prepare secrets

`k8s/auth-service/secret.yaml` ships with `REPLACE_ME_*` placeholders. Provision real
secrets before applying — never commit real secrets to git:

```bash
kubectl create namespace viva-vtu

kubectl create secret generic auth-service-secrets \
  --namespace viva-vtu \
  --from-literal=DATABASE_URL="mysql://viva:PASSWORD@mysql:3306/viva_vtu_auth" \
  --from-literal=JWT_ACCESS_SECRET="$(openssl rand -hex 32)" \
  --from-literal=JWT_REFRESH_SECRET="$(openssl rand -hex 32)" \
  --from-literal=REDIS_PASSWORD="$(openssl rand -hex 24)" \
  --from-literal=SMTP_USER="..." \
  --from-literal=SMTP_PASS="..." \
  --from-literal=SENTRY_DSN=""
```

Alternatively, use sealed-secrets / External Secrets Operator and delete the
placeholder `secret.yaml` from the kustomization.

## 2. Apply manifests

With kustomize (recommended):

```bash
kubectl apply -k k8s/auth-service
```

Or file-by-file (namespace first, then secret/configmap, then workload):

```bash
kubectl apply -f k8s/auth-service/namespace.yaml
kubectl apply -f k8s/auth-service/secret.yaml
kubectl apply -f k8s/auth-service/configmap.yaml
kubectl apply -f k8s/auth-service/deployment.yaml
kubectl apply -f k8s/auth-service/service.yaml
kubectl apply -f k8s/auth-service/hpa.yaml
kubectl apply -f k8s/auth-service/ingress.yaml
kubectl apply -f k8s/auth-service/migration-job.yaml
kubectl apply -f k8s/auth-service/servicemonitor.yaml   # requires Prometheus Operator
```

## 3. Verify

```bash
kubectl -n viva-vtu get pods -l app=auth-service
kubectl -n viva-vtu port-forward svc/auth-service 3001:3001
curl http://127.0.0.1:3001/health    # -> {"status":"ok",...}
curl http://127.0.0.1:3001/readyz    # -> 200 when DB + Redis healthy, otherwise 503
curl http://127.0.0.1:3001/metrics   # -> Prometheus exposition format
```

## 4. Monitoring

- `/metrics` is scraped by the `auth-service` ServiceMonitor (30s interval).
- Alert rules: `k8s/monitoring/prometheus-rules.yaml` covers downtime, 5xx ratio,
  p95 latency, restart loops, and token-verification spikes.
- Grafana dashboard (auto-provisioned): `k8s/monitoring/grafana-dashboard.yaml`.
- Sentry: set `SENTRY_DSN` secret; the SDK self-initializes only when
  `NODE_ENV=production` and DSN is non-empty.

## 5. Scaling

The HPA scales 2 → 10 replicas on CPU (70%) / memory (80%). The service is
stateless (JWT + MySQL + Redis), so rolling scaling is safe. Ensure:

- MySQL supports the connection pool (increase `connection_limit` if needed).
- Redis is shared and reachable (token blacklist + Bull queue + rate limiting).

## 6. Migrations

Run migrations as a one-off Job (idempotent):

```bash
kubectl -n viva-vtu delete job auth-service-migrate --ignore-not-found
kubectl apply -f k8s/auth-service/migration-job.yaml
kubectl -n viva-vtu wait --for=condition=complete job/auth-service-migrate --timeout=120s
```

> Note: the container image also runs `prisma migrate deploy` at startup as a
> fallback; the Job is the recommended path to avoid N-replica races.

## Rollback

```bash
kubectl -n viva-vtu rollout undo deployment/auth-service
```
