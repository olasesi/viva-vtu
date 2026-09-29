# VIVAVTU — Roadmap

Milestones mapped to the FEATURES.md differentiators. Ordering targets the #1 KPI (zero downtime, quick response) and the MVP (data purchase) first.

Legend: ✅ done · 🚧 in progress · ⏳ planned

## Phase 0 — Foundation (done)

- ✅ Microservices scaffold: api-gateway (Node), auth-service (Node), laravel-service (billing: VTPass/Paystack/Flutterwave/wallet), django-service (analytics)
- ✅ Docker Compose dev + prod, Makefile, Sentry-ready logging, Swagger, Jest/Pest/Django tests, lint-staged + commitlint

## Phase 1 — Reliability Engine (IN PROGRESS) → FEATURES A1, A2, A5

Goal: no single point of failure, silent failures die, refunds happen in seconds.

- [x] Provider abstraction (`ProviderContract`) + `VtpassProvider` adapter
- [x] `ProviderRouter` — health-aware, config-driven failover per service category
- [x] `TransactionService` — idempotent state machine + automatic reversal
- [x] `RequeryPendingTransaction` job + scheduled reconciliation command
- [x] Wire second aggregator adapter (Recharge.com.ng)
- [x] Circuit-breaker stress test (trip, cooldown recovery, refund integrity)
- [x] Status-polling endpoint consumed by frontend
- [x] Webhook/SMS receipt emission on state changes (email receipts shipped; SMS awaits Termii) ✅
- [x] Instant email receipt on settle (success/refund/funding/transfer) via SMTP settings

## Phase 2 — Data Purchase MVP (live) → FEATURES E, A3, A4

Goal: boringly reliable data buying for all Nigerians.

- [x] SME/Corporate gifting data pricing engine (wholesale cache + margin rules) 🚧 partial
- [x] Network prefix pre-validation (backend) + phone contact picker (frontend pending)
- [x] Meter/smartcard pre-validation before debit (runtime-toggleable, best-effort)
- [ ] Data plans catalogue UI (frontend) with transparent pre-priced checkout
- [x] Instant on-success email receipt (printable/SMS pending frontend + Termii)

## Phase 3 — Payments & Funding → FEATURES B

Goal: fund a wallet and top utilities in seconds, any channel.

- [ ] Virtual account numbers (Moniepoint/Kuda/Wema) for instant wallet credit
- [ ] OPay micropayment gateway
- [ ] Wallet funding via Paystack/Flutterwave (wallet auto-credit from webhook — already scaffolded)

## Phase 4 — Growth Engine → FEATURES C

- [x] Referral + commission engine (auto referral codes, commission on referred spends)
- [ ] Reseller hierarchy + shared margin engine (layer on commission engine)
- [x] KYC tiers (BVN/NIN, hashed storage, masked display)
- [x] Internal wallet transfer

## Phase 5 — Reseller API & Automation → FEATURES C11, F20-25

- [x] Public reseller API (API keys, idempotency keys, purchase + status/requery)
- [ ] Reseller webhooks (subscribe to order confirmations) — next candidate
- [ ] Admin god-view + correction tool (django-service)
- [x] Health dashboards (admin aggregator-health report) + Slack alerting on breaker trip
- [ ] Price-monitoring bot
- [ ] WhatsApp ordering bot

## Phase 6 — Full Coverage & Scale → FEATURES E, F

- [x] All 12 DISCOs, cable, education pins (provider endpoints configured; full catalogue pending seeding)
- [x] ePIN/recharge card printing (receipt engine; branded printable pending frontend)
- [x] Airtime-to-cash swap (service request endpoint shipped)
- [ ] Asset delivery + auto-scaling (K8s)
- [ ] Optional betting-funding module (off by default)

## MVP definition (stakeholder-aligned)

- Deliverable: purchase of **data** end-to-end with wallet flow, receipts, and auto-reversed failures.
- Acceptance: zero silent failures, refunds in < 60s, wallet funding via at least Paystack + virtual account.
- Non-negotiable: stays inside utilities + payments; brand voice Safe/Corporate.
