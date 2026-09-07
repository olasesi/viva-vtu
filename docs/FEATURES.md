# VIVAVTU — Feature Set & Competitive Analysis

Source of truth for *what* we build and *why*. Research into the Nigerian VTU landscape (2026) plus the project stakeholder interview.

## Why people switch to us (the "switch moment")

Users leave a VTU platform the moment they hit a **downtime** or a **bad service experience** (failed delivery, silent failure, days-long manual refunds, no support). Our KPI is therefore:

> **Quick response + zero downtime** — everything else serves that goal.

Market failures we exploit:

1. Failed transactions with no **auto-reversal** (we build the CBN/NCC 30-second refund rule as a feature, not a requirement).
2. Downtime caused by **single-aggregator dependency**.
3. Slow, unhelpful customer support with no human escalation.
4. Delayed wallet funding due to **manual transfer confirmation**.
5. Silent "where is my data?" anxiety — no delivery receipts.
6. Wrong number/meter/smartcard topped up — no pre-validation.
7. Hidden/surge pricing destroying trust.
8. Inconsistent quality across services.
9. No searchable history / PDF receipts for individuals & businesses.

## Differentiating features (priority-ordered)

### A. Core engine — zero-downtime reliability
| # | Feature | Service | Solves |
|---|---------|---------|--------|
| 1 | Multi-aggregator auto-failover (VTpass + Recharge.com.ng + Buzzpay + VTU.ng), circuit-breakers, health-aware routing | laravel-service | Downtime, single API dependency |
| 2 | Idempotent transaction state machine + auto-reversal engine (pending → processing → successful / failed / reversed) with sub-minute refund SLA | laravel-service | Failed transactions, refund delays |
| 3 | Real-time webhook + push + SMS receipts (Termii / AFricasTalking) on success AND failure, with reason codes | auth-service / gateway | Delivery anxiety, silent failures |
| 4 | Pre-purchase validation — network prefix check, meter/DISCO match, smartcard verify before debit | laravel-service | Wrong-number losses |
| 5 | Smart retry + status requery, auto-reverse confirmed failures instantly | laravel-service | Timed-out transactions |

### B. Payments — instant wallet crediting
| # | Feature | Service | Why |
|---|---------|---------|-----|
| 6 | Virtual account funding (Moniepoint / Kuda / Wema) — instant credit, zero manual confirmation | laravel-service | Slow wallet funding |
| 7 | Paystack + Flutterwave cards/bank/USSD | laravel-service | Main rails (already wired) |
| 8 | OPay micropayments for small amounts | laravel-service | Stakeholder requirement |

### C. Retention & growth
| # | Feature | Service | Why |
|---|---------|---------|-----|
| 9 | Referral program with commission engine (₦ tiers) | laravel-service | Funding is our top risk → free growth |
| 10 | Reseller hierarchy (distributor → reseller → sub-reseller) with wallet, margin %, API keys | laravel-service | Agent network scales us |
| 11 | Public reseller API + webhooks (token auth, idempotency keys, webhooks) | gateway | Devs become sales channel |
| 12 | KYC tiers (email ₦50k/day → BVN ₦500k/day → ID unlimited) | auth-service | Compliance + business limits |
| 13 | Cashback / reward points | laravel-service | Retention engine |
| 14 | Auto top-up & scheduled data subscriptions | laravel-service | Recurring revenue |
| 15 | Internal wallet transfers | laravel-service | Network effect |

### D. Trust & compliance (Safe/Corporate brand)
| # | Feature | Service |
|---|---------|---------|
| 16 | Searchable audit trail + downloadable PDF/image receipts | laravel-service + django-service |
| 17 | State-change notifications incl. reversals | auth-service |
| 18 | CAC-registered branding, transparent support, T&C/privacy | frontend |
| 19 | BVN/NIN verification, fraud monitoring | auth-service |

### E. Service coverage (full one-stop shop)
- Data (MTN/Airtel/Glo/9mobile + SME/Corporate gifting) — **MVP focus**
- Airtime (VTU + ePIN / recharge card printing)
- Electricity — all 12 DISCOs (prepaid + postpaid)
- Cable TV — DStv, GOtv, Startimes, Showmax
- Education pins — WAEC, NECO, NABTEB, JAMB
- Betting funding (Bet9ja, SportyBet, 1xBet) — ***optional, off by default*** (respects the "red line")

### F. Operational excellence
| # | Feature | Service |
|---|---------|---------|
| 20 | Real-time aggregator/service health dashboard | django-service |
| 21 | Alerting (Sentry + Slack/Telegram) on failure spikes | all |
| 22 | Load testing + auto-scaling | infra (K8s/Docker Swarm) |
| 23 | Admin god-view (filter by service/network/aggregator/status) + correction tool | django-service |
| 24 | Price monitoring bot (keep us cheapest) | django-service |
| 25 | WhatsApp ordering bot | gateway |

## Red lines
- Only utilities + payments. No crypto, no marketplace.
- Betting funding ships as an optional module, disabled by default.
- Every feature must serve: **speed, zero downtime, fast customer support**.