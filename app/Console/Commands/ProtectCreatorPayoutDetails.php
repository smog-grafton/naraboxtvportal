<?php

namespace App\Console\Commands;

use App\Models\CreatorPayoutMethod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProtectCreatorPayoutDetails extends Command
{
    protected $signature = 'creator:protect-payout-details {--dry-run}';

    protected $description = 'Encrypt legacy payout destinations and clear their old plaintext columns.';

    public function handle(): int
    {
        $query = CreatorPayoutMethod::query()->where(function ($builder): void {
            $builder->whereNotNull('phone_number')
                ->orWhereNotNull('account_number')
                ->orWhereNotNull('account_name');
        });
        $count = (clone $query)->count();
        if ($this->option('dry-run')) {
            $this->info("{$count} payout method(s) require protected migration.");

            return self::SUCCESS;
        }

        $query->orderBy('id')->chunkById(100, function ($methods): void {
            foreach ($methods as $method) {
                DB::transaction(function () use ($method): void {
                    $details = array_filter([
                        'phone_number' => $method->phone_number,
                        'account_name' => $method->account_name,
                        'account_number' => $method->account_number,
                    ], fn ($value) => filled($value));
                    $fingerprint = hash('sha256', implode('|', [
                        $method->method_type,
                        $method->provider,
                        $details['phone_number'] ?? '',
                        $details['account_number'] ?? '',
                    ]));
                    $method->forceFill([
                        'protected_details' => array_merge($method->protected_details ?? [], $details),
                        'details_fingerprint' => $fingerprint,
                        'phone_number' => null,
                        'account_name' => null,
                        'account_number' => null,
                    ])->save();
                });
            }
        });

        $this->info("Protected {$count} legacy payout method(s).");

        return self::SUCCESS;
    }
}
