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
- [ ] Wire second aggregator adapter (Recharge.com.ng, then others) + test circuit breaker
- [ ] Status-polling endpoint consumed by frontend
- [ ] Webhook/SMS receipt emission on state changes (needs Termii)

## Phase 2 — Data Purchase MVP (live) → FEATURES E, A3, A4
Goal: boringly reliable data buying for all Nigerians.
- [ ] SME/Corporate gifting data pricing engine (wholesale cache + margin rules)
- [ ] Network prefix pre-validation + phone contact picker
- [ ] Data plans catalogue UI (frontend) with transparent pre-priced checkout
- [ ] Instant on-success SMS/push + printable receipt

## Phase 3 — Payments & Funding → FEATURES B
Goal: fund a wallet and top utilities in seconds, any channel.
- [ ] Virtual account numbers (Moniepoint/Kuda/Wema) for instant wallet credit
- [ ] OPay micropayment gateway
- [ ] Wallet funding via Paystack/Flutterwave (wallet auto-credit from webhook — already scaffolded)

## Phase 4 — Growth Engine → FEATURES C
- [ ] Referral + commission engine
- [ ] Reseller hierarchy + margin engine
- [ ] KYC tiers (BVN/NIN)
- [ ] Internal wallet transfer

## Phase 5 — Reseller API & Automation → FEATURES C11, F20-25
- [ ] Public reseller API + webhooks + idempotency keys
- [ ] Admin god-view + correction tool (django-service)
- [ ] Health dashboards + alerting
- [ ] Price-monitoring bot
- [ ] WhatsApp ordering bot

## Phase 6 — Full Coverage & Scale → FEATURES E, F
- [ ] All 12 DISCOs, cable, education pins, ePIN/recharge card printing
- [ ] Airtime-to-cash swap
- [ ] Asset delivery + auto-scaling (K8s)
- [ ] Optional betting-funding module (off by default)

## MVP definition (stakeholder-aligned)
- Deliverable: purchase of **data** end-to-end with wallet flow, receipts, and auto-reversed failures.
- Acceptance: zero silent failures, refunds in < 60s, wallet funding via at least Paystack + virtual account.
- Non-negotiable: stays inside utilities + payments; brand voice Safe/Corporate.