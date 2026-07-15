# Creator Payments and Withdrawals — Implementation Summary

## Overview

The Creator Financial Engine has been implemented as specified in the plan. This document summarizes what was built and how to use it.

## Database

### Migrations (7 new)

1. **`2026_03_15_002001_create_financial_settings_table`** — Singleton config: commission_rate, creator_hold_days, min_withdrawal_amount, auto_payout_enabled, unverified_creator_earns, iotec_disbursement_enabled, pawapay_disbursement_enabled

2. **`2026_03_15_002002_create_creator_payout_methods_table`** — Creator payout methods: user_id, method_type (mobile_money|bank), provider, phone_number, account_name, account_number, bank_name, bank_code, is_default, is_verified, metadata

3. **`2026_03_15_002003_create_creator_earnings_table`** — Per-transaction earnings: user_id, transaction_id, earnable (morph to Movie/TVShow), gross_amount, commission_rate, platform_amount, creator_amount, status (pending|available|withdrawn|reversed), available_at, notes

4. **`2026_03_15_002004_create_creator_withdrawal_requests_table`** — Withdrawal requests: user_id, payout_method_id, amount, status, reference, requested_at, approved_by, approved_at, processed_at, failure_reason, admin_notes, gateway_used, gateway_reference, meta

5. **`2026_03_15_002005_create_creator_payout_attempts_table`** — Gateway disbursement log: withdrawal_request_id, gateway, gateway_request, gateway_response, status, external_id, attempted_at, notes

6. **`2026_03_15_002006_seed_financial_settings_default`** — Inserts default financial settings row

7. **`2026_03_15_002007_create_creator_withdrawal_allocations_table`** — Links withdrawals to earnings for partial allocation: withdrawal_request_id, creator_earning_id, amount

## Models

- **FinancialSetting** — `current()` returns singleton; fillable for all config columns
- **CreatorPayoutMethod** — BelongsTo User; scopes `default()`, `forUser()`; attributes `masked_phone`, `masked_account`
- **CreatorEarning** — BelongsTo User, PaymentTransaction; morph earnable (Movie/TVShow); scopes `available()`, `pending()`, `forUser()`
- **CreatorWithdrawalRequest** — BelongsTo User, CreatorPayoutMethod; HasMany CreatorPayoutAttempt, CreatorWithdrawalAllocation; status constants
- **CreatorPayoutAttempt** — BelongsTo CreatorWithdrawalRequest
- **CreatorWithdrawalAllocation** — BelongsTo CreatorWithdrawalRequest, CreatorEarning

## Services

### CreatorEarningsService

- `allocateFromTransaction(PaymentTransaction)` — Resolves creator from movie->vj->user or movie->mediaLibrary->user; computes commission split; creates CreatorEarning
- `getBalance(User)` — Returns {pending, available, withdrawn_total, total_earned}; available subtracts in-flight withdrawal allocations
- `markAvailable()` — Moves pending earnings to available when available_at has passed (called by scheduler)

### WithdrawalService

- `requestWithdrawal(User, PayoutMethod, amount)` — Validates balance, min threshold, no duplicate pending; creates CreatorWithdrawalRequest
- `approve(WithdrawalRequest, adminUser)` — Sets status approved
- `process(WithdrawalRequest)` — Allocates earnings, sets processing, dispatches to gateway (ioTec mobile/bank or PawaPay stub)
- `processViaIoTec(WithdrawalRequest)` — Mobile money via ioTec `POST /api/disbursements/disburse`
- `processViaIoTecBank(WithdrawalRequest)` — Bank account via ioTec `POST /api/disbursements/bank-disburse`
- `markFailed(WithdrawalRequest, reason)` — Rollback allocation, set status failed
- `reject(WithdrawalRequest, reason)` — Rollback allocation, set status rejected
- `cancel(WithdrawalRequest)` — Rollback allocation, set status cancelled

### IoTeCService (extended)

- `disburse(externalId, amount, payeeMsisdn, payeeName?, payeeNote?)` — Mobile money payout
- `bankDisburse(externalId, amount, accountName, accountNumber, bankId?, bankIdentificationCode?)` — Bank payout
- `getDisbursementStatus(transactionId)` — Status check via `GET /api/disbursements/status/{id}`

## Integration

- **PaymentApprovalService** — After granting access for RENT/BUY, calls `CreatorEarningsService::allocateFromTransaction()`
- **Scheduler** — `creator:mark-earnings-available` runs daily via `routes/console.php`

## API Routes (`/api/v1/creator/`)

All require `auth:sanctum` and creator verification (isCreator or isAdmin):

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/finance/summary` | Balance and min_withdrawal_amount |
| GET | `/finance/earnings` | Paginated earnings history |
| GET | `/payout-methods` | List payout methods |
| POST | `/payout-methods` | Create payout method |
| PUT | `/payout-methods/{id}` | Update payout method |
| DELETE | `/payout-methods/{id}` | Delete payout method |
| POST | `/payout-methods/{id}/set-default` | Set default payout method |
| GET | `/withdrawals` | List withdrawals |
| POST | `/withdrawals` | Request withdrawal |
| DELETE | `/withdrawals/{id}` | Cancel withdrawal (if pending) |

## Filament Admin

- **FinancialSettingsPage** (`/admin/financial-settings`) — Edit commission rate, hold days, min withdrawal, gateway toggles
- **CreatorEarningResource** — Read-only earnings table; filters by status
- **CreatorWithdrawalRequestResource** — Table with Approve, Process, Reject actions; View page with payout attempts
- **CreatorPayoutMethodResource** — Read-only payout methods table

Navigation group: **Creator Finance**

## Next.js Frontend

- `/creator/finance` — Balance cards, recent earnings, recent withdrawals
- `/creator/finance/earnings` — Full earnings history table
- `/creator/finance/withdraw` — Request withdrawal form; past withdrawals list
- `/creator/finance/payout-methods` — Add/edit payout methods (mobile money, bank)

## Commission Logic

- **Platform fee** = gross × commission_rate (from financial_settings, default 30%)
- **Creator share** = gross × (1 - commission_rate) if verified, else 0 (unless unverified_creator_earns)
- **VJ creators** — Treated as verified when linked to user
- **MediaLibrary creators** — Use media_library.is_verified
- Only RENT and BUY transactions generate creator earnings; SUBSCRIPTION does not

## Design Rules Enforced

- Earnings stay `pending` for `creator_hold_days` before `available`
- One in-flight withdrawal (pending/under_review/approved/processing) per creator
- Minimum withdrawal enforced from financial_settings
- Insufficient balance prevented
- Failed payout: withdrawal status = failed, allocations rolled back; earnings remain available
- All disbursement attempts logged in creator_payout_attempts with gateway_request + gateway_response
- PawaPay disbursement stubbed (returns not_configured)

## Running Migrations

```bash
cd /path/to/naraboxt-lara
php artisan migrate
```

## Artisan Commands

```bash
php artisan creator:mark-earnings-available  # Move pending → available
```
