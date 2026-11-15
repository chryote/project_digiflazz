```dotenv
You need to apply for a Digiflazz account to obtain your API credentials. 
Check out https://developer.digiflazz.com/api/buyer/persiapan/ 
```

# Digiflazz PLN Backend & Monitoring Stack

A production-ready, clean-architecture backend implementing the **Digiflazz PLN API Specification** with:
- **Production-Ready API Authentication** (Laravel Sanctum Bearer tokens, brute-force rate limiting, API Key management)
- **Environment-Based Credentials** (Configured strictly via `.env` file)
- **Digiflazz PLN APIs** (Prepaid/Postpaid Inquiries, Payments, Idempotency, and Webhook Callbacks)
- **Separation of Concerns** (Controllers, DTOs, Services, Constants, Enums, Form Requests)
- **Unit & Feature Testing Suite** (100% passing test coverage)
- **Performance & Latency Benchmark Suite** (`php artisan digiflazz:benchmark`)
- **Full Observability Stack** (Prometheus scraping real-time metrics + pre-provisioned Grafana dashboards)

---

## 🔑 Environment Configuration

Credentials and live integration settings must be configured exclusively in your `.env` file (never hardcoded in application code):

```dotenv
DIGIFLAZZ_BASE_URL=https://api.digiflazz.com/v1
DIGIFLAZZ_MODE=live
```

* **`DIGIFLAZZ_MODE=live`**: Forwards all inquiry and payment transactions directly to the live Digiflazz cloud endpoints (`https://api.digiflazz.com/v1/transaction` and `https://api.digiflazz.com/v1/inquiry-pln`).
* **`DIGIFLAZZ_MODE=mock`**: Processes requests locally against the seeded testcase database for instant offline testing and CI/CD pipelines.

> [!TIP]
> **Testing Without a Live Digiflazz Account:**
> If you don't have a live Digiflazz merchant account or API key, you can test 100% of all features locally in **Mock Mode**:
> 1. Set `DIGIFLAZZ_MODE=mock` in your `.env`.
> 2. Put any arbitrary dummy values for credentials in `.env` (e.g., `DIGIFLAZZ_USERNAME=demo_user` and `DIGIFLAZZ_DEVELOPMENT_KEY=demo_key_123`).
> 3. Run `php artisan migrate --seed`.
> 4. All 8 official Digiflazz test cases (prepaid inquiries, postpaid inquiries with 1 & 2 bills, failure cases, asynchronous webhooks, and idempotent status checks) will execute completely offline with full ACID compliance and Prometheus monitoring!

Access in PHP code:
```php
use App\Constants\DigiflazzConstant;

$username = DigiflazzConstant::username();
$apiKey   = DigiflazzConstant::developmentKey();
$baseUrl  = config('digiflazz.base_url');
$mode     = config('digiflazz.mode');
```

---

## Architecture & Separation of Concerns

The codebase follows the Single Responsibility Principle (SRP) and domain-driven separation without god classes:

```
app/
├── Constants/                          # Global domain constants
│   └── DigiflazzConstant.php           # Digiflazz username & development key helpers
├── DTOs/                               # Data Transfer Objects (Immutable, strictly typed)
│   ├── Auth/LoginDTO.php
│   └── Digiflazz/
│       ├── InquiryPlnDTO.php
│       └── TransactionPlnDTO.php
├── Enums/                              # Standardized enumerations
│   ├── DigiflazzResponseCode.php       # Response codes ('00', '03', '40', '41', etc.)
│   ├── DigiflazzStatus.php             # Statuses ('Sukses', 'Pending', 'Gagal')
│   └── CustomerBehavior.php            # Testcase behavior catalog
├── Http/
│   ├── Controllers/Api/V1/             # Thin HTTP Controllers (transport layer only)
│   │   ├── AuthController.php
│   │   ├── PlnInquiryController.php
│   │   ├── PlnTransactionController.php
│   │   └── MockWebhookController.php
│   ├── Middleware/
│   │   └── PrometheusApiMetricsMiddleware.php  # Automated latency & status metrics
│   └── Requests/                       # Dedicated validation & request sanitization
│       ├── Auth/LoginRequest.php
│       └── Digiflazz/
│           ├── InquiryPlnRequest.php
│           └── TransactionPlnRequest.php
├── Models/                             # Eloquent models (User, PlnCustomer, Transaction)
└── Services/                           # Business logic layer
    ├── Auth/AuthService.php            # Token creation, hashing, session management
    ├── Digiflazz/
    │   ├── DigiflazzApiClient.php        # Live HTTP client for https://api.digiflazz.com/v1
    │   ├── DigiflazzSignatureService.php # MD5 signature calculation & verification
    │   ├── PlnInquiryService.php         # Inquiry evaluation & live forwarding
    │   ├── PlnPaymentService.php         # Balance handling, live transactions & idempotency
    │   └── WebhookDispatchService.php    # Asynchronous webhook dispatcher
    └── Monitoring/
        └── ApiMetricsCollector.php     # Real-time metrics aggregator
```

---

## Quick Start (Docker Environment)

### 1. Launch Docker Services
```bash
docker compose up -d --build
```

### 2. Run Database Migrations & Seeders
Inside the Docker container:
```bash
docker compose exec app php artisan migrate --seed
```
*(Or on host machine: `php artisan migrate --seed`)*

### 3. Default Seeded Merchant Account
- **Username:** Sourced from your `.env` file
- **Email:** `merchant@example.com`
- **Password:** `password123`
- **API Key:** Sourced from your `.env` file
- **Initial Balance:** `100,000`

---

## 1. Production-Ready API Login Flow

### 1.1 Login & Obtain Bearer Token
Rate-limited to **10 requests/minute** to protect against brute-force attacks:
```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "your_username",
    "password": "password123",
    "device_name": "Terminal Client"
  }'
```
**Response (200 OK):**
```json
{
  "success": true,
  "message": "Login berhasil.",
  "data": {
    "user": {
      "id": 1,
      "name": "Digiflazz Merchant",
      "email": "merchant@example.com",
      "username": "your_username",
      "api_key": "your_api_key",
      "balance": 100000,
      "webhook_url": "http://localhost:8080/api/v1/mock-webhook"
    },
    "token": {
      "access_token": "1|ABCdef123...",
      "token_type": "Bearer",
      "expires_at": "2026-12-15T00:00:00+00:00"
    }
  }
}
```

### 1.2 Access Protected Profile
```bash
curl -X GET http://localhost:8080/api/v1/auth/me \
  -H "Authorization: Bearer <access_token>"
```

### 1.3 Logout & Revoke Token
```bash
curl -X POST http://localhost:8080/api/v1/auth/logout \
  -H "Authorization: Bearer <access_token>"
```

### 1.4 Regenerate API Key
```bash
curl -X POST http://localhost:8080/api/v1/auth/regenerate-api-key \
  -H "Authorization: Bearer <access_token>"
```

---

## 2. Digiflazz PLN API Specification & Test Cases

### Signature Formula
$$\text{sign} = \text{md5}(\text{username} + \text{apiKey} + \text{customer\_no})$$
*(For payments, either `customer_no` or `ref_id` can be used as the signature suffix: `md5(username + apiKey + ref_id)`).*

---

### 2.1 Testcase: Inquiry PLN (Prepaid ID Validation - Section 1)
```bash
curl -X POST http://localhost:8080/api/v1/inquiry-pln \
  -H "Content-Type: application/json" \
  -d '{
    "username": "your_username",
    "customer_no": "1234554321",
    "sign": "your_calculated_md5_sign"
  }'
```
**Response:**
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

---

### 2.2 Testcase: Inquiry Sukses (1 Tagihan)
```bash
curl -X POST http://localhost:8080/api/v1/inquiry-pln \
  -H "Content-Type: application/json" \
  -d '{
    "username": "your_username",
    "customer_no": "530000000001",
    "sign": "your_calculated_md5_sign",
    "ref_id": "ORD-001"
  }'
```
**Response:**
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

---

### 2.3 Testcase: Inquiry Sukses (2 Tagihan)
```bash
curl -X POST http://localhost:8080/api/v1/inquiry-pln \
  -H "Content-Type: application/json" \
  -d '{
    "username": "your_username",
    "customer_no": "530000000002",
    "sign": "your_calculated_md5_sign",
    "ref_id": "ORD-002"
  }'
```

---

### 2.4 Testcase: Inquiry Gagal
```bash
curl -X POST http://localhost:8080/api/v1/inquiry-pln \
  -H "Content-Type: application/json" \
  -d '{
    "username": "your_username",
    "customer_no": "530000000003",
    "sign": "your_calculated_md5_sign"
  }'
```
**Response:**
```json
{
  "data": {
    "message": "Inquiry Gagal",
    "status": "Gagal",
    "rc": "40",
    "customer_no": "530000000003"
  }
}
```

---

### 2.5 Testcase: Payment Sukses (1 Tagihan) - `api_transaction_spec.txt`
```bash
curl -X POST http://localhost:8080/api/v1/transaction \
  -H "Content-Type: application/json" \
  -d '{
    "commands": "pay-pasca",
    "username": "your_username",
    "buyer_sku_code": "pln",
    "customer_no": "530000000001",
    "ref_id": "PAY-101",
    "sign": "your_calculated_md5_sign",
    "testing": true
  }'
```
**Response:**
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
    "periode": "201901",
    "sn": "S1234554321N",
    "buyer_last_saldo": 90000.0,
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
          "denda": "500",
          "meter_awal": "00080000",
          "meter_akhir": "00090000"
        }
      ]
    }
  }
}
```

> **Cek Status:** Calling `/api/v1/transaction` with the same `ref_id` retrieves the existing transaction status without double-charging balance.

---

### 2.6 Testcase: Pembayaran Gagal
```bash
curl -X POST http://localhost:8080/api/v1/transaction \
  -H "Content-Type: application/json" \
  -d '{
    "commands": "pay-pasca",
    "username": "your_username",
    "buyer_sku_code": "pln",
    "customer_no": "530000000006",
    "ref_id": "PAY-106",
    "sign": "your_calculated_md5_sign"
  }'
```
**Response:**
```json
{
  "data": {
    "ref_id": "PAY-106",
    "customer_no": "530000000006",
    "customer_name": "Nama Pelanggan Keenam",
    "buyer_sku_code": "pln",
    "admin": 2500,
    "message": "Pembayaran Gagal",
    "status": "Gagal",
    "rc": "41",
    "sn": null,
    "buyer_last_saldo": 100000,
    "price": 10000,
    "selling_price": 11000,
    "desc": { ... }
  }
}
```

---

### 2.7 Testcase: Pending, lalu Callback Sukses / Gagal
- `customer_no`: `630000000001` (Pending -> Callback Sukses 1 Tagihan)
- `customer_no`: `630000000002` (Pending -> Callback Sukses 2 Tagihan)
- `customer_no`: `630000000006` (Pending -> Callback Gagal)

```bash
curl -X POST http://localhost:8080/api/v1/transaction \
  -H "Content-Type: application/json" \
  -d '{
    "commands": "pay-pasca",
    "username": "your_username",
    "buyer_sku_code": "pln",
    "customer_no": "630000000001",
    "ref_id": "PAY-631",
    "sign": "your_calculated_md5_sign",
    "callback_url": "http://localhost:8080/api/v1/mock-webhook"
  }'
```
**Immediate Response:**
```json
{
  "data": {
    "ref_id": "PAY-631",
    "customer_no": "630000000001",
    "message": "Transaksi Pending",
    "status": "Pending",
    "rc": "03"
  }
}
```
*The webhook callback is automatically triggered to the specified `callback_url` or configured merchant webhook.*

---

## 3. Unit Tests & Benchmark Suite

### Running Unit & Feature Tests
```bash
# Inside Docker container
docker compose exec app php artisan test

# Or directly on host
php artisan test

# Run live Digiflazz cloud integration tests
php artisan test --filter LiveDigiflazzApiTest
```
*Runs 43 test methods (308 assertions) across Unit, Feature, and Live API suites verifying live Digiflazz connectivity, signatures, login flows, token revocations, transaction spec, test cases, ACID guarantees, idempotency, and concurrency protection.*

---

## 4. ACID, Race-Condition & Concurrency Architecture

All `POST` endpoints in the system are architected to guarantee data integrity under high-concurrency environments:

| Method & Endpoint | Core Concern | Concurrency & Race-Condition Defense | ACID Implementation |
| :--- | :--- | :--- | :--- |
| `POST /api/v1/transaction` (and alias `/payment-pln`) | Double-spending, balance discrepancy, duplicate submissions | **Pessimistic Row Locking** (`SELECT ... FOR UPDATE` via `User::lockForUpdate()`), Database Unique Index `(user_id, ref_id)`, and `UniqueConstraintViolationException` fallback | **Atomicity:** `DB::transaction(..., attempts: 3)` bundles balance debit + transaction record.<br>**Consistency:** Invariants prevent negative balance.<br>**Isolation:** Exclusive row locks serialize concurrent requests per user.<br>**Durability:** InnoDB WAL with auto-retry on deadlock. |
| `POST /api/v1/inquiry-pln` | High-frequency read bursts | Read-only state isolation; atomic cache operations (`Cache::increment`) for metrics tracking | **Isolated:** No mutation of financial or customer data. |
| `POST /api/v1/auth/login` | Concurrent credential stuffing & brute-force token flooding | Laravel Rate Limiter (`throttle:10,1` per minute); atomic `INSERT` on `personal_access_tokens` | **Atomic:** Token creation is isolated per login session. |
| `POST /api/v1/auth/logout` | Token revocation race | Atomic `DELETE` on active access token ID (`$token->delete()`) | **Idempotent:** Safe for concurrent invocations. |
| `POST /api/v1/auth/regenerate-api-key` | Concurrent API key updates | Serialized under database transaction with exclusive lock (`User::lockForUpdate()`) | **Atomic & Isolated:** Consistent state guaranteed across parallel sessions. |

---

### Running API Benchmark
Run the dedicated benchmark command:
```bash
# Default benchmark (30 requests per test)
php artisan digiflazz:benchmark --requests=30

# High-concurrency benchmark (100 requests)
php artisan digiflazz:benchmark --requests=100
```

---

## 5. Prometheus & Grafana Monitoring

### Metrics Exposed at `/metrics`
Scraped automatically by Prometheus every 5 seconds:
- `laravel_db_status`: Primary database connection status (1 = UP, 0 = DOWN)
- `laravel_api_requests_total`: Total requests served across all API endpoints
- `laravel_api_latency_ms`: Rolling average latency in milliseconds
- `laravel_transactions_total`: Total count of transactions stored in database
- `laravel_transactions_success_count`: Total successful payments
- `laravel_transactions_pending_count`: Total pending payments
- `laravel_transactions_failed_count`: Total failed payments
- `laravel_inquiry_success_total` & `laravel_inquiry_failed_total`
- `laravel_auth_logins_success_total` & `laravel_auth_logins_failed_total`
- `laravel_memory_usage_bytes` & `laravel_memory_peak_usage_bytes`

### Dashboards
- **Grafana URL:** [http://localhost:3000](http://localhost:3000) (Credentials: `admin` / `admin`)
- **Prometheus URL:** [http://localhost:9090](http://localhost:9090)
- **Pre-provisioned Dashboards:**
  1. **Digiflazz & Laravel Operations Overview:** Real-time throughput (RPS), avg latency, transaction distribution (Sukses / Pending / Gagal), and memory consumption.
  2. **MySQL Performance Overview:** Query rate, active connections, slow queries, and InnoDB buffer pool.

---

## 6. API Collections (Postman, Bruno, Hoppscotch)

The repository provides API collections for **Postman**, **Bruno**, and **Hoppscotch** located in the `docs/` folder:

| Client | Supported File / Folder | Signature Handling |
| :--- | :--- | :--- |
| **Postman** | [`docs/digiflazz-collection.postman_collection.json`](file:///d:/project_digiflazz/docs/digiflazz-collection.postman_collection.json) | Automatic Pre-request MD5 hashing via `CryptoJS` |
| **Bruno** | [`docs/bruno/`](file:///d:/project_digiflazz/docs/bruno) | Automatic Pre-request MD5 hashing via Node `crypto` |
| **Hoppscotch** | [`docs/digiflazz-hoppscotch.json`](file:///d:/project_digiflazz/docs/digiflazz-hoppscotch.json) or Postman JSON | Environment variable binding |

> [!IMPORTANT]
> Always configure `username` and `api_key` in your client's environment variables using the exact values from your `.env` file. Never hardcode credentials into version control.

---

### 6.1 Using with Postman
1. **Import Collection:**
   - In Postman, click **Import** (top left).
   - Select or drag and drop [`docs/digiflazz-collection.postman_collection.json`](file:///d:/project_digiflazz/docs/digiflazz-collection.postman_collection.json).
2. **Configure Variables:**
   - Click on the imported collection **Digiflazz PLN API** -> **Variables** tab.
   - Set `base_url`: `http://localhost:8080` (or `http://localhost:8000` if running `artisan serve`).
   - Set `username`: Enter your merchant username from your `.env` file.
   - Set `api_key`: Enter your development key from your `.env` file.
   - Click **Save**.
3. **Execute Requests:**
   - **Login First:** Send `1. Authentication -> Login & Issue Bearer Token`. The Post-response test script automatically parses `access_token` and saves it to the `{{token}}` variable.
   - **Protected Endpoints:** Requests in `1. Authentication` (`Get Merchant Profile`, `Regenerate API Key`, `Logout`) automatically inject `Bearer {{token}}`.
   - **Inquiry & Payments:** Run any request in `2. PLN Inquiry` or `3. PLN Payment & Transactions`. Pre-request scripts automatically compute the MD5 signature (`md5(username + apiKey + ref_id/customer_no)`) and generate dynamic unique `ref_id`s.
   - **Cek Status:** Run `Cek Status / Idempotent Replay` to test idempotency using the previous request's `ref_id`.

---

### 6.2 Using with Bruno
1. **Open Collection:**
   - Launch [Bruno](https://www.usebruno.com/).
   - Click **Open Collection** -> navigate to and select the [`docs/bruno`](file:///d:/project_digiflazz/docs/bruno) directory in this repository.
2. **Configure Environment:**
   - In the top right environment dropdown, select **Local**.
   - Click the gear/eye icon or open `environments/Local.bru`.
   - Fill in `username` and `api_key` matching your `.env` values.
3. **Execute Requests:**
   - Run `1-auth/login`. Bruno's response script sets `{{token}}` automatically.
   - Run any inquiry (`2-inquiry/*`) or payment (`3-payment/*`). Pre-request scripts use Bruno's built-in Node `crypto` engine to compute dynamic MD5 signatures seamlessly.

---

### 6.3 Using with Hoppscotch
1. **Import Collection:**
   - Open [Hoppscotch](https://hoppscotch.io) or your local Hoppscotch desktop app.
   - Go to the **Collections** panel on the left sidebar.
   - Click **Import / Export** -> select **From Postman Collection** (choose `docs/digiflazz-collection.postman_collection.json`) or **From Hoppscotch** (choose `docs/digiflazz-hoppscotch.json`).
2. **Configure Environment:**
   - In the Environments tab, create an environment with variables:
     - `base_url`: `http://localhost:8080`
     - `username`: Your merchant username from `.env`
     - `api_key`: Your development key from `.env`
3. **Send Requests:**
   - Run `Login & Issue Bearer Token` and copy the token into your `<<token>>` environment variable.
   - Execute inquiries and payment test cases.
