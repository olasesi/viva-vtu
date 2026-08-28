# Laravel Service - Billing/VTU Microservice

Billing and VTU microservice for VivaVTU platform.

## Features

- **Wallet Management** - Fund, balance check, transfers
- **Airtime Purchase** - All Nigerian networks (MTN, Glo, Airtel, 9mobile)
- **Data Purchase** - Various data plans
- **Electricity** - Prepaid/Postpaid meter token purchase
- **Cable TV** - DSTV, GOtv, Startimes subscription
- **Payment Gateway** - Paystack & Flutterwave integration
- **Webhook Processing** - Automated payment confirmation

## Requirements

- PHP 8.1+
- MySQL 8.0+
- Redis
- Composer

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

## Docker

```bash
docker-compose up -d
```

## API Endpoints

### Public
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/webhook/paystack | Paystack webhook |
| POST | /api/webhook/flutterwave | Flutterwave webhook |

### Protected (JWT)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/wallet/balance | Get wallet balance |
| POST | /api/wallet/fund | Initialize wallet funding |
| GET | /api/wallet/history | Transaction history |
| POST | /api/purchase/airtime | Buy airtime |
| POST | /api/purchase/data | Buy data |
| POST | /api/purchase/electricity | Buy electricity |
| POST | /api/purchase/cable | Buy cable TV |
| GET | /api/services | List VTPass services |
| GET | /api/services/{id}/products | List service products |
| GET | /api/transactions/{id} | Transaction detail |
| GET | /api/transactions | Filtered transactions |

## Environment Variables

See `.env.example` for all required configuration.

## Testing

```bash
php artisan test
```
