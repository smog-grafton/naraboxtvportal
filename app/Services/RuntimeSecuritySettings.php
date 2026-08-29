<?php

namespace App\Services;

use App\Models\SecuritySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class RuntimeSecuritySettings
{
    public const DEFAULTS = [
        'registrations_enabled' => true,
        'google_new_registrations_enabled' => true,
        'payments_enabled' => true,
        'iotec_enabled' => true,
        'mobile_money_enabled' => true,
        'card_payments_enabled' => true,
        'automatic_payment_restriction_enabled' => true,
        'enumeration_detection_enabled' => true,
        'lockdown_mode' => false,
    ];

    public function get(string $key): bool
    {
        $default = self::DEFAULTS[$key] ?? false;
        if (! Schema::hasTable('security_settings')) {
            return $default;
        }

        $store = Cache::store(config('security.cache_store'));
        $value = $store->remember("security-setting:{$key}", 60, fn () => SecuritySetting::where('key', $key)->first()?->value);
        if (is_array($value) && array_key_exists('enabled', $value)) {
            return (bool) $value['enabled'];
        }

        return $default;
    }

    public function set(string $key, bool $enabled, ?int $adminId = null): void
    {
        SecuritySetting::updateOrCreate(
            ['key' => $key],
            ['value' => ['enabled' => $enabled], 'updated_by' => $adminId]
        );
        Cache::store(config('security.cache_store'))->forget("security-setting:{$key}");
    }
}
