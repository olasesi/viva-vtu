# VIVA VTU — Operations Guide

This document covers deployment, scheduled work, monitoring and the configuration for the
trust, support and retention features shipped in Phase 7.

## 1. Deployment checklist

1. Pull the release and install dependencies:
   - Frontend: `npm ci`
   - Billing service: `composer install --no-dev --optimize-autoloader`
   - Auth service: `npm ci`
2. Apply database migrations:
   ```bash
   php artisan migrate --force
   ```
3. Seed the starter knowledge base (safe to re-run, it is idempotent):
   ```bash
   php artisan db:seed --class=FaqSeeder --force
   ```
4. Rebuild caches:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. Build the frontend:
   ```bash
   npm run build
   ```
6. Restart queue workers so they load the new code:
   ```bash
   php artisan queue:restart
   ```

New tables added by this release: `faq_categories`, `faq_articles`, `recurring_purchases`,
plus the Spatie `activity_log` tables.

## 2. Required background processes

| Process | Command | Why |
|---------|---------|-----|
| Scheduler | `php artisan schedule:run` every minute (cron) or `php artisan schedule:work` | Runs `recurring:purchases:run` and queue maintenance |
| Queue worker | `php artisan queue:work --tries=3 --timeout=120` | Sends notifications, requeries pending transactions |

Cron entry example:

```cron
* * * * * cd /var/www/viva-vtu/api/laravel-service && php artisan schedule:run >> /dev/null 2>&1
```

Without the scheduler, recurring purchases never execute. Without the queue worker,
in-app notifications and transaction requeries are delayed until the queue is drained.

## 3. Recurring purchases

Users schedule repeat purchases from **Dashboard → Recurring Purchases**. The scheduler
executes due rows every minute in small batches.

Configuration (`api/laravel-service/config/recurring.php`):

| Env var | Default | Meaning |
|---------|---------|---------|
| `RECURRING_MAX_FAILURES` | `3` | Consecutive failures before the schedule is auto-paused |
| `RECURRING_BATCH_SIZE` | `50` | Maximum schedules processed per run |
| `RECURRING_LOCK_SECONDS` | `120` | Per-schedule lock TTL preventing double spends |
| `RECURRING_RETRY_DELAY_MINUTES` | `60` | Delay before retrying a failed run |

Operational notes:

- Each run is protected by a cache lock. Use a cache store that supports locks (Redis in
  production). A successful run advances `next_run_at` by the schedule; a failed run retries
  after the retry delay.
- After `RECURRING_MAX_FAILURES` consecutive failures the schedule is paused and the user is
  notified. Users can resume it from the dashboard.
- "Run now" is a manual action and requires the transaction PIN when one is configured.
- Manual check: `php artisan recurring:purchases:run`.

## 4. Transaction PIN

| Env var | Default | Meaning |
|---------|---------|---------|
| `TRANSACTION_PIN_THRESHOLD` | `0` | Minimum amount that requires a PIN. `0` means every money-moving action requires it once a PIN is set |

- A PIN is optional per user; when unset the middleware is a no-op.
- Five consecutive wrong attempts lock the PIN for 15 minutes (cache-backed).
- Enforced on wallet transfers, purchases and recurring "run now".

## 5. Two-factor authentication

- TOTP is handled by `api/auth-service`. Users enable it from **Dashboard → Security**.
- Recovery codes are stored as SHA-256 hashes and shown once at setup; each code is consumed
  on use.
- Login returns a short-lived challenge token (`purpose: "2fa"`) which must be exchanged at
  `POST /api/auth/2fa/verify`.
- Ensure server clocks are synchronised (NTP); TOTP verification tolerates one time step of
  drift.

## 6. Live chat

Configured by admins under **Admin → Settings → Support** (public settings group):

| Setting | Notes |
|---------|-------|
| `live_chat_enabled` | Turns the widget on or off |
| `live_chat_provider` | `tawkto` or `crisp` |
| `live_chat_property_id` | tawk.to property ID |
| `live_chat_widget_id` | tawk.to widget ID, or the Crisp website ID |

The widget validates IDs before injecting the provider script. No secret keys are exposed
to the browser. Settings changes apply on the next page load.

## 7. Monitoring and logging

- Health probe: `GET /up` (Laravel) and `GET /api/admin/health` (admin, aggregator health).
- Application logs: `storage/logs/laravel.log`. Recurring purchase exceptions are logged with
  the `recurring_purchase_id` context and are visible in `last_error` on the schedule.
- Audit trail: every admin mutating request (POST/PUT/PATCH/DELETE) is recorded in
  `activity_log` with sensitive fields redacted. Review it under **Admin → Audit Logs**.
- Notifications: failures are delivered to the user's in-app notification centre; delivery
  failures are logged by the queue worker.
- Suggested alerts:
  - Queue backlog or failed jobs (`queue:failed` table).
  - Repeated provider circuit-breaker trips (admin health dashboard / Slack alerting).
  - Spike in `recurring_purchases.last_status = failed`.
  - Disk usage for `storage/logs`.

## 8. Data retention

- `activity_log` grows with admin activity. Retain at least 90 days for audit purposes, then
  prune older rows with a scheduled job if required by policy.
- Cancelled recurring purchases are kept for history. They are excluded from execution.
- Knowledge base view counts are informational only.
