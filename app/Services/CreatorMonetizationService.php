<?php

namespace App\Services;

use App\Models\CreatorMonetizationSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatorMonetizationService
{
    public function __construct(private readonly CreatorAccessService $access)
    {
    }

    public function configure(Model $content, User $user, array $data): CreatorMonetizationSetting
    {
        if (! $this->access->canMonetize($user)) {
            throw ValidationException::withMessages([
                'monetization' => ['Identity approval and monetization permission are required.'],
            ]);
        }

        $rentEnabled = (bool) ($data['rent_enabled'] ?? false);
        $purchaseEnabled = (bool) ($data['purchase_enabled'] ?? false);
        $subscriptionEnabled = (bool) ($data['subscription_enabled'] ?? false);
        $rentPrice = $rentEnabled ? (int) ($data['rent_price_minor'] ?? 0) : null;
        $purchasePrice = $purchaseEnabled ? (int) ($data['purchase_price_minor'] ?? 0) : null;

        $this->validatePrice('rent_price_minor', $rentPrice, (array) config('creator.rent'), $rentEnabled);
        $this->validatePrice('purchase_price_minor', $purchasePrice, (array) config('creator.purchase'), $purchaseEnabled);

        $planIds = array_values(array_unique(array_map('intval', $data['subscription_plan_ids'] ?? [])));
        if ($subscriptionEnabled) {
            $activeCount = SubscriptionPlan::whereIn('id', $planIds)->where('is_active', true)->count();
            if ($activeCount !== count($planIds) || $planIds === []) {
                throw ValidationException::withMessages([
                    'subscription_plan_ids' => ['Select at least one active subscription plan.'],
                ]);
            }
        }

        $setting = CreatorMonetizationSetting::updateOrCreate(
            ['content_type' => $content::class, 'content_id' => $content->getKey()],
            [
                'user_id' => $user->id,
                'subscription_enabled' => $subscriptionEnabled,
                'rent_enabled' => $rentEnabled,
                'purchase_enabled' => $purchaseEnabled,
                'rent_price_minor' => $rentPrice,
                'purchase_price_minor' => $purchasePrice,
                'rental_duration_hours' => $data['rental_duration_hours'] ?? config('creator.rent.default_duration_hours'),
                'subscription_plan_ids' => $subscriptionEnabled ? $planIds : [],
                'currency' => 'UGX',
                'status' => 'pending_review',
            ]
        );

        $content->forceFill([
            'monetization_status' => 'pending_review',
            'is_free' => ! ($subscriptionEnabled || $rentEnabled || $purchaseEnabled),
            'is_premium' => $subscriptionEnabled,
            'price_rent' => $rentPrice,
            'price_buy' => $purchasePrice,
        ])->save();

        return $setting;
    }

    private function validatePrice(string $field, ?int $value, array $policy, bool $enabled): void
    {
        if (! $enabled) {
            return;
        }
        $min = (int) ($policy['min_minor'] ?? 0);
        $max = (int) ($policy['max_minor'] ?? PHP_INT_MAX);
        $increment = max(1, (int) ($policy['increment_minor'] ?? 1));
        if ($value === null || $value < $min || $value > $max || $value % $increment !== 0) {
            throw ValidationException::withMessages([
                $field => ["Price must be between {$min} and {$max} UGX in increments of {$increment}."],
            ]);
        }
    }
}
