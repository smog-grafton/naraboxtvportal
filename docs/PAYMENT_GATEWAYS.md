# Payment gateways and payment flow

This document describes how payments work in the NaraBox TV Portal: database structure, gateway types, and **code samples** for mobile apps and developers.

---

## Database structure

### payment_gateways

Stores every payment method the portal supports.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | varchar(255) | Internal name (e.g. "MTN Mobile Money") |
| slug | varchar(255) | Unique slug (e.g. "mtn", "flutterwave") |
| code | varchar(255) | Gateway code (often same as slug) |
| type | enum | **AUTOMATIC** (gateway API) or **MANUAL** (user pays then uploads proof) |
| display_name | varchar(255) | Name shown in UI (e.g. "Flutterwave") |
| logo_path | varchar(255) | Optional logo in storage |
| description | text | Optional description |
| helper_text | varchar(255) | Short hint for user |
| instructions | text | Step-by-step for MANUAL gateways |
| payment_details | longtext | JSON/text (e.g. account number, mobile number) |
| config | longtext | JSON (API keys, etc.; server-only) |
| is_active | tinyint(1) | 1 = shown in API |
| sort_order | int | Display order |
| created_at, updated_at | timestamp | |

**Current gateways (from DB):**

| id | name | slug | type | display_name |
|----|------|------|------|--------------|
| 1 | MTN Mobile Money | mtn | MANUAL | MTN Mobile Money |
| 2 | Airtel Money | airtel | MANUAL | Airtel Money |
| 3 | STRIPE | stripe | AUTOMATIC | Card Payment |
| 4 | PAYPAL | paypal | AUTOMATIC | PayPal Express |
| 5 | Bank Transfer | bank-transfer | MANUAL | Bank Transfer |
| 6 | Flutterwave | flutterwave | AUTOMATIC | Flutterwave |
| 7 | ioTec Pay | iotec | AUTOMATIC | Mobile Money |
| 8 | PawaPay | pawapay | AUTOMATIC | PawaPay (MTN/Airtel) |

---

### payment_transactions

One row per payment attempt (rent, buy, or subscription).

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Who is paying |
| payment_gateway_id | bigint | Which gateway |
| gateway_code | varchar(255) | e.g. "flutterwave" |
| type | enum | **RENT** \| **BUY** \| **SUBSCRIPTION** |
| subscription_plan_id | bigint | Required when type = SUBSCRIPTION |
| transaction_ref | varchar(255) | Unique ref (e.g. NBX-FLW-xxx); used in verify and callbacks |
| amount | decimal(10,2) | Amount charged |
| status | enum | **PENDING** \| **SUCCESS** \| **FAILED** \| **CANCELLED** |
| failure_reason | varchar(255) | When status = FAILED |
| gateway_transaction_id | varchar(255) | Provider’s transaction id |
| external_reference | varchar(255) | Provider reference |
| transactionable_type | varchar(255) | e.g. App\Models\Movie |
| transactionable_id | bigint | Movie/TV show id for RENT/BUY |
| meta | longtext | JSON (e.g. return_url) |
| raw_request, raw_response, raw_callback | longtext | Debug / audit |
| created_at, updated_at | timestamp | |

---

### payments (manual proof)

Used only for **MANUAL** gateways: user uploads a proof image/PDF after paying.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | |
| transaction_id | bigint | Links to payment_transactions.id |
| payment_gateway_id | bigint | |
| proof_path | varchar(255) | Stored file path (e.g. payment-proofs/xxx.jpg) |
| notes | text | User notes |
| status | enum | **PENDING** \| **APPROVED** \| **REJECTED** |
| admin_notes | text | Shown to user when rejected |
| approved_by, approved_at | | Set when admin approves/rejects |

---

## API flow overview

1. **List gateways** — `GET /api/v1/payment-gateways` (no auth).
2. **List plans** — `GET /api/v1/subscription-plans` (no auth).
3. **Initiate payment** — Either:
   - **Generic:** `POST /api/v1/payments/initiate` (auth, email verified) with `gateway_id`, `type`, and either `media_id`+`media_type` (RENT/BUY) or `subscription_plan_id` (SUBSCRIPTION).
   - **Gateway-specific:** Flutterwave `POST /api/v1/flutterwave/initiate`, ioTec `POST /api/v1/iotec/initiate`, PawaPay `POST /api/v1/payments/pawapay/deposit/initiate`.
4. **MANUAL only:** User pays offline, then `POST /api/v1/payments/upload-proof` with `transaction_ref` and `proof` file.
5. **Verify / poll:** `POST /api/v1/payments/verify` with `transaction_ref` (or gateway-specific verify/status endpoints).
6. **Backend:** On SUCCESS, `PaymentApprovalService::grantAccess()` creates/updates `user_subscriptions`, `user_rentals`, or `user_purchases`.

---

## Code samples

Base URL: `https://portal.naraboxtv.com` (or your backend). All endpoints under `/api/v1/`. Assume token from login/register: `Authorization: Bearer <token>`.

---

### 1. Get payment gateways (no auth)

**Request**

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/payment-gateways" \
  -H "Accept: application/json"
```

**JavaScript (fetch)**

```javascript
const res = await fetch('https://portal.naraboxtv.com/api/v1/payment-gateways', {
  headers: { Accept: 'application/json' },
});
const gateways = await res.json();
// gateways[].id, slug, code, displayName, type (AUTOMATIC|MANUAL), instructions, helperText
```

**Example response**

```json
[
  {
    "id": 6,
    "name": "Flutterwave",
    "slug": "flutterwave",
    "code": "flutterwave",
    "displayName": "Flutterwave",
    "type": "AUTOMATIC",
    "instructions": null,
    "helperText": null,
    "logoUrl": "https://..."
  },
  {
    "id": 1,
    "name": "MTN Mobile Money",
    "slug": "mtn",
    "code": "mtn",
    "displayName": "MTN Mobile Money",
    "type": "MANUAL",
    "instructions": "Pay to 0782... then upload proof.",
    "helperText": "Use your MTN number"
  }
]
```

---

### 2. Generic: initiate payment (auth required)

Use for any gateway when you want a single flow; for AUTOMATIC gateways the backend may return a generic “complete on device” message. For Flutterwave/ioTec/PawaPay, prefer their dedicated initiate endpoints (see below).

**Request (subscription)**

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/payments/initiate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "SUBSCRIPTION",
    "gateway_id": 1,
    "subscription_plan_id": 1
  }'
```

**Request (rent a movie)**

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/payments/initiate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "RENT",
    "gateway_id": 1,
    "media_id": 5,
    "media_type": "MOVIE"
  }'
```

**Request (buy a TV show)**

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/payments/initiate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "BUY",
    "gateway_id": 1,
    "media_id": 10,
    "media_type": "TV_SHOW"
  }'
```

**JavaScript (subscription)**

```javascript
const token = 'YOUR_TOKEN';
const res = await fetch('https://portal.naraboxtv.com/api/v1/payments/initiate', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify({
    type: 'SUBSCRIPTION',
    gateway_id: 1,
    subscription_plan_id: 1,
  }),
});
const data = await res.json();
// data.transaction_ref, data.amount, data.status, data.gateway_type
// If MANUAL: data.instructions, data.payment_details
```

**Example response (MANUAL gateway)**

```json
{
  "transaction_ref": "NBX-ABCD1234EFGH",
  "amount": 10000,
  "status": "PENDING",
  "gateway_type": "MANUAL",
  "instructions": "Send 10000 to 0782... Account: NaraBox.",
  "payment_details": { "account": "0782123456", "name": "NaraBox TV" },
  "message": "Please follow the instructions and upload proof of payment"
}
```

---

### 3. Upload payment proof (MANUAL gateways, auth required)

**Request (multipart)**

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/payments/upload-proof" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "transaction_ref=NBX-ABCD1234EFGH" \
  -F "proof=@/path/to/receipt.jpg" \
  -F "notes=Paid via MTN at 14:00"
```

**JavaScript (FormData)**

```javascript
const form = new FormData();
form.append('transaction_ref', 'NBX-ABCD1234EFGH');
form.append('proof', fileInput.files[0]); // jpeg, jpg, png, pdf; max 10MB
form.append('notes', 'Paid via MTN at 14:00');

const res = await fetch('https://portal.naraboxtv.com/api/v1/payments/upload-proof', {
  method: 'POST',
  headers: { 'Authorization': `Bearer ${token}` },
  body: form,
});
const data = await res.json();
// data.success, data.message, data.payment_id
```

**Example response**

```json
{
  "success": true,
  "message": "Payment proof uploaded. Waiting for admin approval.",
  "payment_id": 42
}
```

---

### 4. Verify payment status (auth required)

Use after upload (MANUAL) or after redirect/callback (AUTOMATIC). Poll until status is not PENDING if needed.

**Request**

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/payments/verify" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"transaction_ref": "NBX-ABCD1234EFGH"}'
```

**JavaScript**

```javascript
const res = await fetch('https://portal.naraboxtv.com/api/v1/payments/verify', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  body: JSON.stringify({ transaction_ref: 'NBX-ABCD1234EFGH' }),
});
const data = await res.json();
// data.success, data.status (APPROVED|PENDING|REJECTED), data.message
```

**Example (approved)**

```json
{
  "success": true,
  "status": "APPROVED",
  "message": "Payment approved. Access granted."
}
```

**Example (pending)**

```json
{
  "success": false,
  "status": "PENDING",
  "message": "Payment is pending admin approval"
}
```

---

### 5. Flutterwave (AUTOMATIC)

**Initiate** — Returns a link or payload to open Flutterwave checkout (e.g. mobile money Uganda).

**Request**

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/flutterwave/initiate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "SUBSCRIPTION",
    "subscription_plan_id": 1,
    "return_url": "myapp://payment/callback"
  }'
```

For RENT/BUY add `media_id` and `media_type`; omit `subscription_plan_id`.

**Response** — Typically includes a URL to redirect the user (e.g. `data.link` or similar). User completes payment in browser/WebView; Flutterwave sends a webhook to the backend; backend updates `payment_transactions.status` to SUCCESS and grants access.

**Verify** — After redirect back to your app with `tx_ref` in the URL:

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/flutterwave/verify" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"transaction_ref": "NBX-FLW-XXXX-1234567890"}'
```

Use the same `transaction_ref` you got from initiate (and that Flutterwave passes back).

---

### 6. ioTec Pay (AUTOMATIC, in-site prompt)

**Initiate** — In-site phone prompt flow.

**Request**

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/iotec/initiate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "SUBSCRIPTION",
    "subscription_plan_id": 1,
    "phone": "256780000000",
    "return_url": "/dashboard"
  }'
```

For RENT/BUY also send `media_id` and `media_type` (`MOVIE` or `TV_SHOW`).

**Status (poll)** — Check status with either `transaction_ref` or `payment_id` returned from initiate:

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/iotec/status" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "transaction_ref": "NBX-IOT-XXX"
  }'
```

Response (normalized):

```json
{ "status": "success", "redirect_url": "/dashboard" }
{ "status": "pending", "message": "Waiting for confirmation" }
{ "status": "failed", "message": "Payment failed" }
```

> Note: ioTec also calls a webhook on the backend; the server remains the source of truth, and status polling reflects the latest provider result.

---

### 7. PawaPay (AUTOMATIC, deposit)

**Initiate deposit** — Starts a mobile money deposit flow.

**Request**

```bash
curl -X POST "https://portal.naraboxtv.com/api/v1/payments/pawapay/deposit/initiate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "SUBSCRIPTION",
    "subscription_plan_id": 1,
    "phone": "0772123456",
    "provider": "MTN_MOMO_UGA",
    "currency": "UGX"
  }'
```

On success this returns a `deposit_id` and `transaction_ref`. Use `deposit_id` for status polling.

**Check deposit status**

```bash
curl -X GET "https://portal.naraboxtv.com/api/v1/payments/pawapay/deposit/DEPOSIT_ID/status" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Use the `deposit_id` returned from initiate. Backend also receives webhooks and updates the transaction.

Typical response:

```json
{ "success": true, "status": "PENDING", "message": null }
{ "success": true, "status": "COMPLETED", "message": null }
{ "success": true, "status": "FAILED", "message": "Insufficient funds" }
```

---

## Summary for mobile / AI

- **List gateways** → `GET /payment-gateways`. Use `type` (AUTOMATIC vs MANUAL) and `id`/`slug` for initiate.
- **Subscription:** `type: SUBSCRIPTION`, `subscription_plan_id`. **Rent/Buy:** `type: RENT` or `BUY`, `media_id`, `media_type: MOVIE` or `TV_SHOW`.
- **MANUAL:** Initiate → show `instructions`/`payment_details` → user pays → upload proof with `transaction_ref` → poll verify until APPROVED/REJECTED.
- **AUTOMATIC (Flutterwave/ioTec/PawaPay):** Use their initiate endpoints → redirect or poll status → verify with `transaction_ref` when backend has updated.
- Always send **Authorization: Bearer &lt;token&gt;** for initiate, upload-proof, and verify. User must be **email-verified** for payment endpoints (middleware).

For full endpoint list and more samples, see **API_REFERENCE_WITH_CODE_SAMPLES.md** and the Scribe docs at **/docs/api/v1**.
