<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire subscriptions and rentals that have passed their expiration date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $totalExpired = 0;
        
        // Find all active subscriptions that have expired (case-insensitive)
        // Status can be 'active', 'ACTIVE', etc.
        $expiredCount = \DB::table('subscriptions')
            ->whereRaw("(UPPER(status) = 'ACTIVE' OR status = 'active' OR status = 'ACTIVE')")
            ->whereNotNull('end_date')
            ->where('end_date', '<', $now)
            ->update([
                'status' => 'EXPIRED',
                'updated_at' => $now,
            ]);

        $totalExpired += $expiredCount;
        if ($expiredCount > 0) {
            $this->info("Expired {$expiredCount} subscription(s) from subscriptions table.");
        }

        // Also check user_subscriptions table if it exists
        if (\Schema::hasTable('user_subscriptions')) {
            $expiredUserSubs = \DB::table('user_subscriptions')
                ->whereRaw("UPPER(status) = 'ACTIVE'")
                ->where('expires_at', '<', $now)
                ->update([
                    'status' => 'EXPIRED',
                    'updated_at' => $now,
                ]);

            $totalExpired += $expiredUserSubs;
            if ($expiredUserSubs > 0) {
                $this->info("Expired {$expiredUserSubs} user subscription(s) from user_subscriptions table.");
            }
        }

        // Expire rentals that have passed their expiration date
        $expiredRentals = 0;
        if (\Schema::hasTable('user_rentals')) {
            $expiredRentals = \DB::table('user_rentals')
                ->where('is_active', true)
                ->where('expires_at', '<', $now)
                ->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);

            if ($expiredRentals > 0) {
                $this->info("Expired {$expiredRentals} rental(s) from user_rentals table.");
            }
        }

        // Also check old rentals table if it exists
        if (\Schema::hasTable('rentals')) {
            $expiredOldRentals = \DB::table('rentals')
                ->where('is_active', true)
                ->where('expires_at', '<', $now)
                ->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);

            if ($expiredOldRentals > 0) {
                $this->info("Expired {$expiredOldRentals} rental(s) from rentals table.");
            }
        }

        if ($totalExpired === 0 && $expiredRentals === 0) {
            $this->info("No subscriptions or rentals to expire.");
        }

        return Command::SUCCESS;
    }
}
