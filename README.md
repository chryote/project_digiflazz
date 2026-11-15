# Digiflazz PLN Backend _(project_digiflazz)_

[![standard-readme compliant](https://img.shields.io/badge/readme%20style-standard-brightgreen.svg?style=flat-square)](https://github.com/RichardLitt/standard-readme)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.3-777bb4.svg?style=flat-square)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E13.17-ff2d20.svg?style=flat-square)](https://laravel.com/)

Production-ready Laravel backend implementing the Digiflazz PLN API specification with Prometheus observability.

This project delivers a clean-architecture backend implementing the **Digiflazz PLN API Specification** for prepaid token inquiries, postpaid bill inquiries, payment transactions, idempotency handling, and asynchronous webhook callbacks. It includes built-in protection against race conditions and double spending, a built-in offline mock simulation engine for all 8 official Digiflazz test cases, a performance benchmark suite, and a full observability stack using Prometheus and Grafana.

> **Note on Naming:** The repository and directory are named `project_digiflazz`, while the package manager identifier in `composer.json` is `laravel/laravel` because the project is scaffolded from the Laravel framework base.

> **API Registration:** To obtain live credentials for production or development testing with Digiflazz, apply for a merchant account at the [Digiflazz Developer Portal](https://developer.digiflazz.com/api/buyer/persiapan/).

## Table of Contents

- [Security](#security)
- [Background](#background)
- [Install](#install)
- [Usage](#usage)
- [Architecture](#architecture)
- [Concurrency and Data Integrity](#concurrency-and-data-integrity)
- [Monitoring and Metrics](#monitoring-and-metrics)
- [Testing and Benchmarks](#testing-and-benchmarks)
- [API Collections](#api-collections)
- [API](#api)
- [Maintainers](#maintainers)
- [Contributing](#contributing)
- [License](#license)

## Security

This application applies several defensive measures to protect financial operations and merchant resources:

- **Token Authentication:** API authentication uses Laravel Sanctum personal access bearer tokens.
- **Brute-Force Rate Limiting:** The `/api/v1/auth/login` endpoint is rate-limited to 10 attempts per minute per IP to mitigate credential stuffing.
- **Cryptographic Signature Verification:** Digiflazz requests compute and verify an MD5 digital signature (`md5(username + apiKey + customer_no/ref_id)`).
- **Pessimistic Row Locking:** Payment transactions acquire exclusive database row locks (`SELECT ... FOR UPDATE`) inside atomic database transactions to eliminate race conditions and balance discrepancies.
- **Strict Environment Isolation:** API keys, webhook secrets, and credentials reside strictly in the `.env` file and are never committed to version control.

## Background

[Digiflazz](https://digiflazz.com) is a major B2B digital goods and PPOB (Payment Point Online Bank) provider in Indonesia, offering services including PLN (State Electricity Company) prepaid token validation and postpaid bill settlement.

Integrating digital goods at scale requires addressing high-concurrency balance updates, network latency, duplicate webhook notifications, and external provider downtime. This repository serves as a reference implementation combining:
- Complete coverage of the Digiflazz PLN API specification.
- Clean architecture with Data Transfer Objects (DTOs), dedicated services, and thin controllers.
- An offline mock testing mode that matches all official test cases without requiring active merchant balances.
- Real-time performance monitoring and metrics collection out of the box.

## Install

### Prerequisites

- [Docker](https://www.docker.com/) and Docker Compose (recommended), **or**
- [PHP](https://www.php.net/) >= 8.3 with BCMath, PDO MySQL, and cURL extensions
- [Composer](https://getcomposer.org/)
- [MySQL](https://www.mysql.com/) 8.0+

### Option A: Docker Setup (Recommended)

1. Clone the repository:
   ```bash
   git clone git@github.com:chryote/project_digiflazz.git
   cd project_digiflazz
   ```

2. Copy the environment configuration:
   ```bash
   cp .env.example .env
   ```

3. Build and start containers:
   ```bash
   docker compose up -d --build
   ```

4. Run migrations and database seeders:
   ```bash
   docker compose exec app php artisan migrate --seed
   ```

### Option B: Local Host Setup

1. Install PHP dependencies:
   ```bash
   composer install
   ```

2. Configure environment file:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. Update database credentials in `.env` to match your local MySQL server.

4. Run database migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```

### Environment Configuration

Configure your `.env` file with appropriate Digiflazz settings:

```dotenv
DIGIFLAZZ_BASE_URL=https://api.digiflazz.com/v1
DIGIFLAZZ_MODE=live
DIGIFLAZZ_USERNAME=your_username
DIGIFLAZZ_DEVELOPMENT_KEY=your_dev_api_key
DIGIFLAZZ_PRODUCTION_KEY=your_prod_api_key
DIGIFLAZZ_WEBHOOK_SECRET=your_webhook_secret
```

#### Operating Modes

- **`DIGIFLAZZ_MODE=live`**: Dispatches requests directly to Digiflazz cloud endpoints (`https://api.digiflazz.com/v1`).
- **`DIGIFLAZZ_MODE=mock`**: Processes inquiries and payments locally against seeded test customer data, ideal for offline development, local tests, and CI/CD pipelines.

### Default Seeded Merchant Account

Running `php artisan migrate --seed` creates a default merchant account:
- **Username:** Sourced from `DIGIFLAZZ_USERNAME` in `.env`
- **Email:** `merchant@example.com`
- **Password:** `password123`
- **API Key:** Sourced from `DIGIFLAZZ_DEVELOPMENT_KEY` in `.env`
- **Initial Balance:** `100,000`

## Usage

### Starting the Server

If running via Docker, the application is available at `http://localhost:8080`.

If running locally without Docker:
```bash
php artisan serve
```

### Authenticating and Obtaining a Bearer Token

Issue a login request to obtain a personal access token:

```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "your_username",
    "password": "password123",
    "device_name": "API Client"
  }'
```

Response:
```json
{
  "success": true,
  "message": "Login berhasil.",
  "data": {
    "user": {
      "id": 1,
      "username": "your_username",
      "email": "merchant@example.com",
      "balance": 100000
    },
    "token": {
      "access_token": "1|ABCdef123...",
      "token_type": "Bearer"
    }
  }
}
```

### Accessing Merchant Profile

```bash
curl -X GET http://localhost:8080/api/v1/auth/me \
  -H "Authorization: Bearer <access_token>"
```

### Programmatic Usage in PHP

```php
use App\Constants\DigiflazzConstant;
use App\Services\Digiflazz\PlnInquiryService;
use App\DTOs\Digiflazz\InquiryPlnDTO;

$username = DigiflazzConstant::username();
$apiKey   = DigiflazzConstant::developmentKey();

$dto = new InquiryPlnDTO(
    username: $username,
    customerNo: '1234554321',
    sign: md5($username . $apiKey . '1234554321'),
    refId: 'ORD-001'
);

$inquiryService = app(PlnInquiryService::class);
$result = $inquiryService->inquire($dto);
```

## Architecture

The project adheres to the Single Responsibility Principle (SRP) with distinct domain-oriented layers:

```
app/
├── Constants/                          # Domain constants (DigiflazzConstant)
├── DTOs/                               # Immutable, strongly typed data transfer objects
│   ├── Auth/LoginDTO.php
│   └── Digiflazz/
│       ├── InquiryPlnDTO.php
│       └── TransactionPlnDTO.php
├── Enums/                              # Domain enumerations (Response codes, Statuses, Behaviors)
│   ├── CustomerBehavior.php
│   ├── DigiflazzResponseCode.php
│   └── DigiflazzStatus.php
├── Http/
│   ├── Controllers/Api/V1/             # Thin HTTP controllers (transport layer only)
│   │   ├── AuthController.php
│   │   ├── MockWebhookController.php
│   │   ├── PlnInquiryController.php
│   │   └── PlnTransactionController.php
│   ├── Middleware/
│   │   └── PrometheusApiMetricsMiddleware.php # Latency, count, and status metrics
│   └── Requests/                       # Dedicated form requests and validation logic
│       ├── Auth/LoginRequest.php
│       └── Digiflazz/
│           ├── InquiryPlnRequest.php
│           └── TransactionPlnRequest.php
├── Models/                             # Eloquent models (User, PlnCustomer, Transaction)
└── Services/                           # Business logic layer
    ├── Auth/AuthService.php
    ├── Digiflazz/
    │   ├── DigiflazzApiClient.php        # Live external HTTP client
    │   ├── DigiflazzSignatureService.php # MD5 signature calculation and validation
    │   ├── PlnInquiryService.php         # Inquiry evaluation and upstream dispatch
    │   ├── PlnPaymentService.php         # Balance validation, locking, and settlement
    │   └── WebhookDispatchService.php    # Webhook callback dispatcher
    └── Monitoring/
        └── ApiMetricsCollector.php     # Real-time metrics collector
```

## Concurrency and Data Integrity

All mutation endpoints enforce strict ACID guarantees to prevent financial loss, double debits, and ghost transactions under high concurrent load:

| Method & Endpoint | Primary Concern | Concurrency Defense | ACID Guarantee |
| :--- | :--- | :--- | :--- |
| `POST /api/v1/transaction` (and alias `/payment-pln`) | Double spending, duplicate debits, race conditions | Pessimistic row locking (`User::lockForUpdate()`), compound database unique index `(user_id, ref_id)`, and `UniqueConstraintViolationException` fallback | **Atomicity:** `DB::transaction(..., attempts: 3)` bundles balance debit and transaction creation.<br>**Consistency:** Invariants prevent negative merchant balances.<br>**Isolation:** Exclusive locks serialize concurrent debits per user.<br>**Durability:** InnoDB write-ahead logs with automated deadlock retries. |
| `POST /api/v1/inquiry-pln` | High-frequency read spikes | Read-only state isolation; atomic cache operations for metrics | **Isolated:** No mutation of financial or customer data. |
| `POST /api/v1/auth/login` | Brute force and token flooding | Laravel rate limiter (`10 req/min`); atomic token table insertion | **Atomic:** Token creation is isolated per login session. |
| `POST /api/v1/auth/logout` | Token revocation race conditions | Atomic deletion on active access token ID | **Idempotent:** Safe for repeated invocations. |
| `POST /api/v1/auth/regenerate-api-key` | Concurrent API key updates | Serialized inside database transaction with exclusive lock (`User::lockForUpdate()`) | **Atomic & Isolated:** Consistent state guaranteed across parallel requests. |

## Monitoring and Metrics

### Prometheus Metrics

Metrics are exposed at `/metrics` and scraped every 5 seconds:

- `laravel_db_status`: Primary database connection state (1 = UP, 0 = DOWN)
- `laravel_api_requests_total`: Total requests across all API endpoints
- `laravel_api_latency_ms`: Rolling average response latency in milliseconds
- `laravel_transactions_total`: Total transaction count in database
- `laravel_transactions_success_count`: Total successful transactions
- `laravel_transactions_pending_count`: Total pending transactions
- `laravel_transactions_failed_count`: Total failed transactions
- `laravel_inquiry_success_total` & `laravel_inquiry_failed_total`: Inquiry metrics
- `laravel_auth_logins_success_total` & `laravel_auth_logins_failed_total`: Login metrics
- `laravel_memory_usage_bytes` & `laravel_memory_peak_usage_bytes`: Memory consumption

### Pre-provisioned Dashboards

- **Grafana:** [http://localhost:3000](http://localhost:3000) (Default credentials: `admin` / `admin`)
- **Prometheus:** [http://localhost:9090](http://localhost:9090)
- **Included Dashboards:**
  1. **Digiflazz & Laravel Operations Overview:** Real-time throughput (RPS), average latency, transaction status breakdown, and memory usage.
  2. **MySQL Performance Overview:** Query rate, active connections, slow queries, and InnoDB buffer pool utilization.

## Testing and Benchmarks

### Running Unit and Feature Tests

```bash
# Inside Docker container
docker compose exec app php artisan test

# On local host
php artisan test

# Filter live integration tests against Digiflazz cloud
php artisan test --filter LiveDigiflazzApiTest
```

The test suite contains 43 test methods and over 300 assertions covering signatures, inquiry flows, payment idempotency, webhook dispatching, concurrency locks, and mock behaviors.

### Running Performance Benchmarks

A dedicated Artisan command benchmarks endpoint throughput and latency:

```bash
# Standard benchmark (30 requests)
php artisan digiflazz:benchmark --requests=30

# High-concurrency benchmark (100 requests)
php artisan digiflazz:benchmark --requests=100
```

## API Collections

Ready-to-use API collections are provided in the `docs/` folder:

| Tool | File / Folder | Signature Handling |
| :--- | :--- | :--- |
| **Postman** | [`docs/digiflazz-collection.postman_collection.json`](docs/digiflazz-collection.postman_collection.json) | Pre-request MD5 hashing via `CryptoJS` |
| **Bruno** | [`docs/bruno/`](docs/bruno) | Pre-request MD5 hashing via Node `crypto` |
| **Hoppscotch** | [`docs/digiflazz-hoppscotch.json`](docs/digiflazz-hoppscotch.json) | Environment variable binding |

### Postman Setup

1. Import `docs/digiflazz-collection.postman_collection.json`.
2. Open collection variables and configure:
   - `base_url`: `http://localhost:8080`
   - `username`: Your merchant username from `.env`
   - `api_key`: Your development key from `.env`
3. Execute `1. Authentication -> Login & Issue Bearer Token`. The post-response script automatically saves `{{token}}`.
4. Execute any inquiry or payment request; signatures and unique `ref_id` values are calculated automatically.

### Bruno Setup

1. Launch Bruno and click **Open Collection**.
2. Select the `docs/bruno` folder.
3. Select the **Local** environment and fill in `username` and `api_key`.
4. Run requests in `1-auth`, `2-inquiry`, or `3-payment`.

### Hoppscotch Setup

1. Open Hoppscotch and import `docs/digiflazz-collection.postman_collection.json` or `docs/digiflazz-hoppscotch.json`.
2. Configure environment variables (`base_url`, `username`, `api_key`).
3. Run authentication to obtain a token and execute requests.

## API

### Digital Signature Formula

Digiflazz requires an MD5 digest computed over concatenated credentials:

$$\text{sign} = \text{md5}(\text{username} + \text{apiKey} + \text{customer\_no})$$

For payment endpoints, `ref_id` can also be used as the signature suffix:

$$\text{sign} = \text{md5}(\text{username} + \text{apiKey} + \text{ref\_id})$$

---

### 1. Authentication Endpoints

#### `POST /api/v1/auth/login`
Authenticates merchant credentials and issues a Sanctum token. Rate-limited to 10 requests per minute.

```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "your_username",
    "password": "password123",
    "device_name": "Terminal Client"
  }'
```

#### `GET /api/v1/auth/me`
Retrieves merchant profile, current balance, and webhook settings. Requires `Bearer <token>`.

#### `POST /api/v1/auth/regenerate-api-key`
Regenerates the merchant API key inside a serialized database transaction. Requires `Bearer <token>`.

#### `POST /api/v1/auth/logout`
Revokes the current access token. Requires `Bearer <token>`.

---

### 2. PLN Inquiry Endpoints

#### `POST /api/v1/inquiry-pln`
Validates customer identity for PLN prepaid meter numbers or calculates outstanding bills for PLN postpaid accounts.

#### Case 1: Prepaid ID Validation
- Request:
  ```json
  {
    "username": "your_username",
    "customer_no": "1234554321",
    "sign": "calculated_md5_hash"
  }
  ```
- Response (`rc: "00"`):
  ```json
  {
    "data": {
      "message": "Transaksi Sukses",
      "status": "Sukses",
      "rc": "00",
      "customer_no": "1234554321",
      "meter_no": "1234554321",
      "subscriber_id": "523300817840",
      "name": "DAVID",
      "segment_power": "R1 /000001300"
    }
  }
  ```

#### Case 2: Postpaid Inquiry (1 Tagihan)
- Request:
  ```json
  {
    "username": "your_username",
    "customer_no": "530000000001",
    "sign": "calculated_md5_hash",
    "ref_id": "ORD-001"
  }
  ```
- Response (`rc: "00"`, `lembar_tagihan: 1`):
  ```json
  {
    "data": {
      "ref_id": "ORD-001",
      "customer_no": "530000000001",
      "customer_name": "Nama Pelanggan Pertama",
      "buyer_sku_code": "pln",
      "admin": 2500,
      "message": "Transaksi Sukses",
      "status": "Sukses",
      "rc": "00",
      "buyer_last_saldo": 100000,
      "price": 10000,
      "selling_price": 11000,
      "desc": {
        "tarif": "R1",
        "daya": 1300,
        "lembar_tagihan": 1,
        "detail": [
          {
            "periode": "201901",
            "nilai_tagihan": "8000",
            "admin": "2500",
            "denda": "500"
          }
        ]
      }
    }
  }
  ```

#### Case 3: Postpaid Inquiry (2 Tagihan)
- `customer_no`: `530000000002` returns two bill periods in `desc.detail`.

#### Case 4: Inquiry Gagal
- `customer_no`: `530000000003` returns response code `rc: "40"` ("Inquiry Gagal").

---

### 3. PLN Payment and Transaction Endpoints

#### `POST /api/v1/transaction` (Alias: `/api/v1/payment-pln`)
Executes a payment for PLN postpaid bills or queries status for existing reference IDs.

#### Case 5: Payment Sukses (1 Tagihan)
- Request:
  ```json
  {
    "commands": "pay-pasca",
    "username": "your_username",
    "buyer_sku_code": "pln",
    "customer_no": "530000000001",
    "ref_id": "PAY-101",
    "sign": "calculated_md5_hash",
    "testing": true
  }
  ```
- Response (`rc: "00"`):
  ```json
  {
    "data": {
      "ref_id": "PAY-101",
      "customer_no": "530000000001",
      "customer_name": "Nama Pelanggan Pertama",
      "buyer_sku_code": "pln",
      "admin": 2500,
      "message": "Transaksi Sukses",
      "status": "Sukses",
      "rc": "00",
      "sn": "S1234554321N",
      "buyer_last_saldo": 90000.0,
      "price": 10000,
      "selling_price": 11000
    }
  }
  ```

#### Case 6: Payment Gagal
- `customer_no`: `530000000006` returns response code `rc: "41"` ("Pembayaran Gagal") without deducting merchant balance.

#### Case 7: Pending Transaction with Webhook Callback
- `customer_no`: `630000000001` returns `status: "Pending"` (`rc: "03"`) initially, followed by an asynchronous HTTP POST webhook callback to `callback_url`.

#### Case 8: Status Check / Idempotent Replay
Sending another request with an already-settled `ref_id` retrieves the stored transaction result immediately without double-debiting the balance.

---

### 4. Webhook Receiver Endpoint

#### `POST /api/v1/mock-webhook`
A mock webhook endpoint that logs received payloads for callback verification.

## Maintainers

[@chryote](https://github.com/chryote)

## Contributing

Contributions, bug reports, and improvements are welcome!

1. Check existing issues or open a new issue on GitHub to discuss proposed changes.
2. Fork the repository and create your feature branch:
   ```bash
   git checkout -b feature/my-feature
   ```
3. Ensure all tests and linting pass:
   ```bash
   php artisan test
   ```
4. Submit a Pull Request with a clear summary of your changes.

> When contributing to the documentation, please adhere to the [standard-readme](https://github.com/RichardLitt/standard-readme) specification.

## License

[MIT](LICENSE) © 2026 chryote
