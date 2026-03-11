<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
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
                    'features' => $plan->features ?? [],
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
            'features' => $plan->features ?? [],
        ]);
    }
}
