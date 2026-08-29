<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Minimal progress counter for a bulk NBX resync run, backed by cache
 * rather than a new table — this is a one-off disaster-recovery operation,
 * not a feature that needs a durable audit trail of its own.
 */
class ResyncRunTracker
{
    private const TTL_DAYS = 7;

    public static function start(string $tier, int $total): string
    {
        $key = 'nbx-resync:'.$tier.':'.(string) Str::ulid();
        Cache::put($key, [
            'tier' => $tier,
            'status' => 'running',
            'total' => $total,
            'matched' => 0,
            'unmatched' => 0,
            'recreated' => 0,
            'failed' => 0,
            'started_at' => now()->toIso8601String(),
            'completed_at' => null,
        ], now()->addDays(self::TTL_DAYS));
        Cache::put(self::latestKey($tier), $key, now()->addDays(self::TTL_DAYS));

        return $key;
    }

    public static function increment(string $runCacheKey, string $counter): void
    {
        Cache::lock($runCacheKey.':lock', 10)->block(5, function () use ($runCacheKey, $counter): void {
            $state = Cache::get($runCacheKey);
            if (! is_array($state)) {
                return;
            }
            $state[$counter] = (int) ($state[$counter] ?? 0) + 1;
            $processed = (int) $state['matched'] + (int) $state['unmatched'] + (int) $state['recreated'] + (int) $state['failed'];
            if ($processed >= (int) $state['total']) {
                $state['status'] = 'completed';
                $state['completed_at'] = now()->toIso8601String();
            }
            Cache::put($runCacheKey, $state, now()->addDays(self::TTL_DAYS));
        });
    }

    public static function latest(string $tier): ?array
    {
        $key = Cache::get(self::latestKey($tier));

        return is_string($key) ? self::get($key) : null;
    }

    public static function get(string $runCacheKey): ?array
    {
        $state = Cache::get($runCacheKey);

        return is_array($state) ? $state : null;
    }

    private static function latestKey(string $tier): string
    {
        return 'nbx-resync:latest:'.$tier;
    }
}
