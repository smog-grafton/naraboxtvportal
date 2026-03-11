# Subscription Expiration Fix

## Problem
Subscriptions were not automatically expiring when their `end_date` passed. Users with expired subscriptions (e.g., purchased on Jan 2, 2026, still showing as active on Jan 8, 2026) were still able to access premium content.

## Root Cause
1. The system checked `end_date < now()` but never automatically updated the `status` field from 'ACTIVE' to 'EXPIRED'
2. Status field was stored as lowercase 'active' in some cases, making case-sensitive queries fail
3. No scheduled task was running to check and expire subscriptions

## Solution Implemented

### 1. Created Expiration Command
**File**: `app/Console/Commands/ExpireSubscriptions.php`

This command:
- Finds all active subscriptions where `end_date < now()`
- Updates their status to 'EXPIRED'
- Handles both `subscriptions` and `user_subscriptions` tables
- Works with case-insensitive status values ('active', 'ACTIVE', etc.)

**Run manually**: `php artisan subscriptions:expire`

### 2. Scheduled Automatic Execution
**File**: `routes/console.php`

The command is scheduled to run **every hour** automatically:
```php
Schedule::command('subscriptions:expire')->hourly();
```

**Note**: For this to work in production, you need to set up a cron job:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Model Observer for Real-time Expiration
**File**: `app/Models/Subscription.php`

Added a `boot()` method that:
- Automatically expires subscriptions when they're accessed from the database
- Updates status to 'EXPIRED' if `end_date` has passed
- Ensures expired subscriptions are immediately marked as expired when queried

### 4. Updated Controllers
**Files**: 
- `app/Http/Controllers/Api/DashboardController.php`
- `app/Http/Controllers/Api/AuthController.php`

Updated to check both old `subscriptions` table and new `user_subscriptions` table, and handle case-insensitive status checks.

## Testing

To test the expiration:
1. Run the command: `php artisan subscriptions:expire`
2. Check expired subscriptions: 
   ```sql
   SELECT * FROM subscriptions WHERE status = 'EXPIRED' AND end_date < NOW();
   ```

## Environment Variables

### Next.js (.env)
Created `.env` file in `/Applications/XAMPP/xamppfiles/htdocs/narabox-next/`:
```
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1
NEXT_PUBLIC_SITE_URL=https://naraboxtv.com
```

**For Vercel deployment**, set these environment variables in the Vercel dashboard.

## Important Notes

1. **The command was already run** and expired 4 subscriptions that were past their end_date
2. **The scheduled task** will run automatically every hour once cron is set up
3. **The model observer** ensures immediate expiration when subscriptions are accessed
4. **Status is case-insensitive** - handles 'active', 'ACTIVE', etc.

## Next Steps

1. Set up cron job in production to run `php artisan schedule:run` every minute
2. Monitor the logs to ensure subscriptions are expiring correctly
3. Test with a new subscription to verify expiration works
