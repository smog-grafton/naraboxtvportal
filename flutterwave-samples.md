You are a principal full-stack engineer integrating Flutterwave v3 (Live API) into an existing Narabox streaming platform.

Stack:

Backend: Laravel 10 + Filament

Frontend: Next.js (TypeScript, App Router)

Payments already supported: Manual gateways

Core business logic already exists: rent / buy / subscription access

This integration must extend, not replace, the current payment architecture.

🧠 Existing System Knowledge (DO NOT IGNORE)
Payment Types Already Implemented

Manual payments

Admin approval workflow

payment_gateways table exists

transactions and payments logic exists

Access control already implemented for:

Rent (30 days)

Buy (lifetime)

Subscription (time-based)

Flutterwave must plug into this system cleanly.

🎯 Objective

Integrate Flutterwave as an automatic payment gateway that works seamlessly with:

Renting movies / TV shows

Buying movies / TV shows

Subscribing to plans

Existing UI & backend flows

UGX currency (Uganda-focused)

📘 Reference (MANDATORY)

Study and follow Flutterwave official docs:
👉 https://developer.flutterwave.com/v3.0.0/docs/getting-started

🔐 Flutterwave Live Keys

Use LIVE V3 API keys.

Add to Laravel .env:

FLW_PUBLIC_KEY=FLWPUBK-5f999063dffb2c9a9bde9d51e9fbd151-X
FLW_SECRET_KEY=FLWSECK-684fe6433aaf89bc83f246bb3fddaa52-19b78b572d5vt-X
FLW_ENCRYPTION_KEY=684fe6433aaf74392998267f
FLW_ENV=live
FLW_CURRENCY=UGX


Also seed these keys into the payment_gateways table so Flutterwave behaves like other gateways.

🗄 Database & Models (IMPORTANT)
payment_gateways

Flutterwave must be stored as:

type = flutterwave

is_automatic = true

Configurable via Filament

Admin must be able to:

Enable / disable Flutterwave

Switch live/test (future-proofing)

View keys (masked)

🧩 Backend Implementation (Laravel)
1️⃣ PaymentGateway Filament Resource

Extend:

app/Filament/Resources/PaymentGatewayResource


Flutterwave-specific fields:

public_key

secret_key

encryption_key

supported_currencies (UGX)

active toggle

2️⃣ API Endpoints (Critical)
POST /api/payments/flutterwave/initiate

Triggered from:

Movie rent modal

Movie buy modal

Subscription checkout

Responsibilities:

Create a transaction record (status = pending)

Generate unique tx_ref

Call Flutterwave /v3/payments

Return checkout link OR payload for frontend modal

Payload MUST include:

amount

currency: UGX

customer (email, phone, name)

tx_ref

redirect_url

meta:

user_id

payment_type (rent | buy | subscription)

movie_id / show_id / plan_id

GET /api/payments/flutterwave/verify

Triggered after redirect or frontend callback.

Responsibilities:

Verify transaction via:

GET /v3/transactions/{id}/verify


Confirm:

status === successful

amount & currency match

Mark transaction approved

Trigger access logic:

Rent → 30-day access

Buy → permanent access

Subscription → activate plan

❗ NO ACCESS IS GRANTED WITHOUT SERVER VERIFICATION

🎨 Frontend Integration (Next.js)
🔹 Payment Modal

File:

@narabox-next/components/PaymentModal.tsx


Enhancements:

Display Flutterwave alongside manual gateways

When Flutterwave selected:

Call /api/payments/flutterwave/initiate

Launch Flutterwave checkout (inline or redirect)

On success:

Call verify endpoint

Update UI state (pending → active)

Flutterwave must work for:

Rent

Buy

🔹 Subscription Pages

Files:

app/subscriptions/page.tsx
app/subscriptions/SubscriptionPlansClient.tsx


Flow:

User selects plan

Selects Flutterwave

Checkout opens

Backend verification

Subscription activates automatically

🧭 Dashboard & Navbar Sync
Dashboard

File:

app/dashboard/DashboardClient.tsx


Must reflect:

Active rentals (with expiry)

Purchases

Subscription status

Pending payments (if any)

Navbar

File:

@Navbar.tsx (324–339)


Show:

Active subscription

Pending Flutterwave payment (if verification not complete)

Expiry dates

🔄 Transaction Handling Rules

Flutterwave payments are auto-approved

Manual payments still require admin approval

Flutterwave failures must not unlock content

All payment logic must be enforced server-side

🧪 Test Scenarios (MANDATORY)

Rent movie using Flutterwave (UGX)

Buy movie using Flutterwave

Subscribe using Flutterwave

Failed payment → no access

Duplicate tx_ref prevention

Disabled Flutterwave gateway fallback

🧱 Constraints

Do NOT break existing manual payment flow

Do NOT hardcode keys in frontend

Follow existing Narabox architecture

Keep logic reusable and scalable

🏁 Definition of Done

Flutterwave works end-to-end

Uses UGX

Fully integrated with rent, buy & subscription

Admin can manage via Filament

UI reflects real payment state

Secure, verified, production-ready

If you want, next I can:

✅ Generate Laravel controllers & services

✅ Write Flutterwave service class

✅ Generate Next.js PaymentModal code

✅ Draw payment flow diagrams

✅ Write seeders & migrations