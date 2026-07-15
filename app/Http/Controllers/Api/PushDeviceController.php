<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @group Push devices
 *
 * Register and manage device tokens for push notifications.
 */
class PushDeviceController extends Controller
{
    /**
     * Register or update a device token
     *
     * This endpoint is used by the mobile/web app to register a push token.
     * If a device with the same provider+token already exists, it will be updated.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|string|in:android,ios,web,other',
            'provider' => 'required|string|in:fcm,onesignal,custom',
            'token' => 'required|string|max:255',
            'device_id' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'app_version' => 'nullable|string|max:50',
            'notifications_enabled' => 'nullable|boolean',
            'marketing_opt_in' => 'nullable|boolean',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $userId = Auth::id();

        $device = PushDevice::updateOrCreate(
            [
                'provider' => $request->input('provider'),
                'token' => $request->input('token'),
            ],
            [
                'user_id' => $userId,
                'platform' => $request->input('platform'),
                'device_id' => $request->input('device_id'),
                'device_name' => $request->input('device_name'),
                'app_version' => $request->input('app_version'),
                'is_active' => true,
                'notifications_enabled' => $request->boolean('notifications_enabled', true),
                'marketing_opt_in' => $request->boolean('marketing_opt_in', false),
                'tags' => $request->input('tags'),
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Device registered successfully',
            'data' => [
                'id' => $device->id,
                'platform' => $device->platform,
                'provider' => $device->provider,
                'device_id' => $device->device_id,
                'device_name' => $device->device_name,
                'app_version' => $device->app_version,
                'notifications_enabled' => $device->notifications_enabled,
                'marketing_opt_in' => $device->marketing_opt_in,
                'tags' => $device->tags,
            ],
        ]);
    }

    /**
     * Unregister a device token
     *
     * Marks a device as inactive so it stops receiving notifications.
     */
    public function unregister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|string|in:fcm,onesignal,custom',
            'token' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $device = PushDevice::where('provider', $request->input('provider'))
            ->where('token', $request->input('token'))
            ->first();

        if (!$device) {
            return response()->json([
                'message' => 'Device already unregistered',
            ]);
        }

        $device->update([
            'is_active' => false,
        ]);

        return response()->json([
            'message' => 'Device unregistered successfully',
        ]);
    }
}
