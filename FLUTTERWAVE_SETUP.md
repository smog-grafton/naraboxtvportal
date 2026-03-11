# Flutterwave Integration Setup Guide

## ✅ Integration Complete

Flutterwave v3 has been successfully integrated into the NaraBox platform.

## 🔐 Environment Variables

Add these to your `.env` file in `/Applications/XAMPP/xamppfiles/htdocs/naraboxt-lara/.env`:

```env
# Flutterwave Configuration
FLW_PUBLIC_KEY=FLWPUBK-5f999063dffb2c9a9bde9d51e9fbd151-X
FLW_SECRET_KEY=FLWSECK-684fe6433aaf89bc83f246bb3fddaa52-19b78b572d5vt-X
FLW_ENCRYPTION_KEY=684fe6433aaf74392998267f
FLW_ENV=live
FLW_CURRENCY=UGX

# Frontend URL (already set)
FRONTEND_URL=http://localhost:3000
```

## 🗄️ Database Setup

Run the seeder to add Flutterwave gateway:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/naraboxt-lara
php artisan db:seed --class=PaymentGatewaySeeder
```

Or run all seeders:

```bash
php artisan db:seed
```

## 📁 Files Created/Modified

### Backend (Laravel)

1. **Service Class**: `app/Services/FlutterwaveService.php`
   - Handles Flutterwave API interactions
   - Methods: `initiatePayment()`, `verifyTransaction()`, `verifyByTxRef()`

2. **Controller**: `app/Http/Controllers/Api/FlutterwaveController.php`
   - Endpoints: `initiate()`, `verify()`, `webhook()`
   - Handles payment initiation, verification, and webhook callbacks

3. **Routes**: `routes/api.php`
   - Added `/api/v1/flutterwave/initiate`
   - Added `/api/v1/flutterwave/verify`
   - Added `/api/v1/flutterwave/webhook`

4. **Filament Resource**: `app/Filament/Resources/PaymentGatewayResource.php`
   - Updated to show Flutterwave-specific config fields
   - Public key, secret key, encryption key, environment, currency

5. **Seeder**: `database/seeders/PaymentGatewaySeeder.php`
   - Seeds Flutterwave gateway with config from .env

6. **Config**: `config/services.php`
   - Added Flutterwave service configuration

### Frontend (Next.js)

1. **API Client**: `lib/api.ts`
   - Added `initiateFlutterwavePayment()`
   - Added `verifyFlutterwavePayment()`

2. **Payment Modal**: `components/PaymentModal.tsx`
   - Added Flutterwave payment handling
   - Opens Flutterwave checkout in popup window
   - Verifies payment after completion

3. **Callback Page**: `app/payment/callback/page.tsx`
   - Handles Flutterwave redirect after payment
   - Verifies payment and redirects to dashboard

## 🎯 Features

### Supported Payment Types
- ✅ Rent (30-day access)
- ✅ Buy (permanent access)
- ✅ Subscription (plan-based access)

### Payment Methods
- Card payments
- Mobile Money (Uganda)

### Currency
- UGX (Uganda Shilling)

## 🔄 Payment Flow

1. User selects Flutterwave as payment gateway
2. Backend creates transaction and calls Flutterwave API
3. Flutterwave checkout opens in popup window
4. User completes payment on Flutterwave
5. Flutterwave redirects to callback URL
6. Backend verifies payment via Flutterwave API
7. Access is granted automatically upon verification

## 🧪 Testing

### Test Scenarios

1. **Rent Payment**
   - Select movie → Rent → Flutterwave → Complete payment
   - Verify 30-day access is granted

2. **Buy Payment**
   - Select movie → Buy → Flutterwave → Complete payment
   - Verify permanent access is granted

3. **Subscription Payment**
   - Select plan → Flutterwave → Complete payment
   - Verify subscription is activated

### Test Cards (if using test mode)

Switch `FLW_ENV=test` in .env and use Flutterwave test cards.

## 🔒 Security

- All API keys stored in `.env` (never in code)
- Server-side verification required before access grant
- Transaction references are unique and non-repeating
- Webhook support for real-time payment updates

## 📝 Admin Panel

Access Flutterwave configuration via Filament:
- Navigate to: Payment Management → Payment Gateways
- Edit Flutterwave gateway
- Configure keys, environment, currency
- Enable/disable gateway

## 🐛 Troubleshooting

### Payment Not Verifying
- Check Flutterwave dashboard for transaction status
- Verify API keys are correct
- Check server logs: `storage/logs/laravel.log`

### Checkout Not Opening
- Check browser popup blocker
- Verify `FRONTEND_URL` is correct in .env
- Check Flutterwave public key is valid

### Access Not Granted
- Verify payment status in Flutterwave dashboard
- Check transaction record in database
- Review server logs for errors

## 📚 Documentation

- Flutterwave API Docs: https://developer.flutterwave.com/v3.0.0/docs/getting-started
- Flutterwave Uganda: https://developer.flutterwave.com/v3.0.0/docs/uganda

## ✅ Next Steps

1. Add Flutterwave keys to `.env`
2. Run seeder: `php artisan db:seed --class=PaymentGatewaySeeder`
3. Test payment flow
4. Configure webhook URL in Flutterwave dashboard (optional)
5. Monitor transactions in Flutterwave dashboard

