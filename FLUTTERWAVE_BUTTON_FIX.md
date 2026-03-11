# Flutterwave "Proceed" Button Not Working - Fix Guide

## Issue
The Flutterwave checkout page loads, but clicking the "Proceed" button does nothing.

## Root Causes

### 1. Redirect URL Not Whitelisted (MOST COMMON)
Flutterwave requires redirect URLs to be whitelisted in your dashboard.

**Fix:**
1. Log in to https://dashboard.flutterwave.com
2. Go to **Settings** → **API Keys & Webhooks**
3. Scroll to **Redirect URL** or **Whitelisted URLs** section
4. Add these URLs:
   - `http://localhost:3000/payment/callback` (for local development)
   - `https://naraboxtv.com/payment/callback` (for production)
   - `http://127.0.0.1:3000/payment/callback` (if using IP)
5. Click **Save**

### 2. Phone Number Format
Mobile Money Uganda requires phone numbers in format: `256XXXXXXXXX`

**Check:**
- User phone number should be in format: `256XXXXXXXXX`
- If user has `0755123456`, it should be converted to `256755123456`

**Fix Applied:**
- Backend now automatically formats phone numbers to `256XXXXXXXXX` format

### 3. Browser Security/CORS
Some browsers block cross-origin requests.

**Fix:**
- Try in incognito/private window
- Disable browser extensions temporarily
- Check browser console for CORS errors

### 4. Flutterwave Account Settings
Check your Flutterwave account status.

**Check:**
1. Go to https://dashboard.flutterwave.com
2. Check if account is fully activated
3. Verify API keys are correct (test vs live)
4. Check if there are any account restrictions

### 5. Payment Options Configuration
Ensure payment options are correctly configured.

**Current Configuration:**
- `payment_options: 'card,mobilemoneyuganda'`

**Verify:**
- Mobile Money Uganda is enabled in your Flutterwave account
- Card payments are enabled
- No restrictions on payment methods

## Testing Steps

1. **Check Redirect URL:**
   ```bash
   # In Laravel .env file
   FRONTEND_URL=http://localhost:3000
   ```

2. **Verify Phone Number:**
   ```sql
   SELECT id, email, phone FROM users WHERE email='lubowabh@gmail.com';
   ```
   Phone should be in format: `256XXXXXXXXX`

3. **Test Payment:**
   - Initiate a payment
   - Check browser console for errors
   - Check Flutterwave dashboard for transaction logs

4. **Check Laravel Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Common Error Messages

### "Invalid redirect URL"
- **Fix:** Whitelist the redirect URL in Flutterwave dashboard

### "Phone number format invalid"
- **Fix:** Ensure phone is in `256XXXXXXXXX` format

### "Payment method not available"
- **Fix:** Enable Mobile Money Uganda in Flutterwave dashboard

## Additional Resources

- Flutterwave Documentation: https://developer.flutterwave.com/docs
- Flutterwave Support: support@flutterwave.com
- Dashboard: https://dashboard.flutterwave.com

## Code Changes Applied

1. **Phone Number Formatting:**
   - Automatically converts phone numbers to `256XXXXXXXXX` format
   - Handles various input formats (0XXXXXXXXX, +256XXXXXXXXX, etc.)

2. **Redirect URL Encoding:**
   - Properly encodes transaction reference in redirect URL
   - Ensures URL is properly formatted

3. **Error Logging:**
   - Enhanced error logging for debugging

## Still Not Working?

If the issue persists after following these steps:

1. Check Flutterwave dashboard transaction logs
2. Review browser console for JavaScript errors
3. Check Laravel logs for API errors
4. Contact Flutterwave support with:
   - Transaction reference
   - Error messages
   - Screenshots of the issue
