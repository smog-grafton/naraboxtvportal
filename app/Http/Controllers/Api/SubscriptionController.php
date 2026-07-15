<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

/**
 * @group Subscription plans
 *
 * Public list of subscription plans (no auth required).
 */
class SubscriptionController extends Controller
{
    /**
     * Filament stores features as [{"feature":"..."}]; mobile/web expect string[].
     *
     * @param  mixed  $features
     * @return list<string>
     */
    private function normalizeFeatures(mixed $features): array
    {
        if (! is_array($features)) {
            return [];
        }

        $out = [];
        foreach ($features as $item) {
            if (is_string($item)) {
                $out[] = $item;

                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            if (isset($item['feature'])) {
                $out[] = (string) $item['feature'];

                continue;
            }
            if (isset($item['name'])) {
                $out[] = (string) $item['name'];

                continue;
            }
            if (isset($item['title'])) {
                $out[] = (string) $item['title'];
            }
        }

        return $out;
    }

    /**
     * Get all active subscription plans
     */
    public function plans()
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'duration_days' => $plan->duration_days,
                    'price' => $plan->price,
                    'currency' => 'UGX',
                    'features' => $this->normalizeFeatures($plan->features ?? []),
                ];
            });

        return response()->json($plans);
    }

    /**
     * Get a specific subscription plan
     */
    public function show($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        return response()->json([
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'duration_days' => $plan->duration_days,
            'price' => $plan->price,
            'currency' => 'UGX',
            'features' => $this->normalizeFeatures($plan->features ?? []),
        ]);
    }
}
