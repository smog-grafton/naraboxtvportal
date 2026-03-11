# Rental Expiration Fix

## Problem
Rented movies and TV shows were not automatically expiring when their `expires_at` date passed. The system checked `expires_at > now()` but never updated the `is_active` field to `false` when rentals expired.

## Solution Implemented

### 1. Updated Expiration Command
**File**: `app/Console/Commands/ExpireSubscriptions.php`

The command now also expires rentals:
- Finds all active rentals where `expires_at < now()`
- Updates `is_active` to `false` for expired rentals
- Handles both `user_rentals` (new polymorphic table) and `rentals` (old table)

**Run manually**: `php artisan subscriptions:expire`

### 2. Model Observers for Real-time Expiration
**Files**: 
- `app/Models/UserRental.php`
- `app/Models/Rental.php`

Added `boot()` methods that:
- Automatically expire rentals when they're accessed from the database
- Update `is_active` to `false` if `expires_at` has passed
- Ensures expired rentals are immediately marked as inactive when queried

### 3. Enhanced isExpired() Method
Both Rental models now have an improved `isExpired()` method that:
- Checks if `expires_at` is in the past
- Automatically updates `is_active` to `false` if expired
- Returns the expiration status

## How It Works

### Rentals (30-day expiration)
- When a user rents a movie/TV show, `expires_at` is set to 30 days from `rented_at`
- The rental expires automatically when `expires_at` passes
- `is_active` is set to `false` when expired
- Access is denied once expired

### Purchases (Lifetime - Never Expire)
- Purchases have NO expiration date (only `purchased_at` timestamp)
- Purchases are stored in `user_purchases` table
- No `expires_at` field exists for purchases
- Access is granted permanently for purchased content

## Access Priority Order

The system checks access in this order:
1. **Subscription** - Active subscription grants access to premium content
2. **Purchase** - Lifetime access (never expires)
3. **Rental** - 30-day access (expires after `expires_at`)
4. **Pending Payment** - Temporary access while payment is processing

## Testing

To test rental expiration:
1. Create a test rental with `expires_at` in the past
2. Run: `php artisan subscriptions:expire`
3. Check: `SELECT * FROM user_rentals WHERE is_active = false AND expires_at < NOW();`

## Important Notes

1. **Purchases are lifetime** - They never expire and have no expiration date
2. **Rentals expire after 30 days** - Set when rental is created
3. **The scheduled task** runs every hour to expire rentals automatically
4. **The model observer** ensures immediate expiration when rentals are accessed
5. **Both old and new rental tables** are supported

## Database Structure

### Rentals
- `user_rentals` table (new, polymorphic):
  - `expires_at` (datetime) - 30 days from `rented_at`
  - `is_active` (boolean) - Set to `false` when expired
  
- `rentals` table (old):
  - `expires_at` (datetime) - 48 hours from `rented_at` (per migration comment)
  - `is_active` (boolean) - Set to `false` when expired

### Purchases
- `user_purchases` table:
  - `purchased_at` (datetime) - No expiration date
  - No `expires_at` field - Lifetime access
