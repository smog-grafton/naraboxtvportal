<?php

namespace App\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class PresenceService
{
    public const ACTIVE_TTL_SECONDS = 150;

    private const CACHE_KEY = 'narabox.presence.entries.v1';

    private const LOCK_KEY = 'narabox.presence.entries.lock.v1';

    /**
     * Record an explicit foreground heartbeat. API requests must never call this
     * service implicitly; the clients call the heartbeat endpoint directly.
     *
     * @param  array{presence_id:string, platform:string, current_path?:string|null, app_version?:string|null}  $payload
     * @return array<string, mixed>
     */
    public function heartbeat(array $payload, ?int $userId = null): array
    {
        $platform = $this->normalizePlatform((string) ($payload['platform'] ?? 'other'));
        $presenceId = Str::limit(trim((string) ($payload['presence_id'] ?? '')), 128, '');

        if ($presenceId === '') {
            throw new \InvalidArgumentException('A stable presence_id is required.');
        }

        $now = now();
        $expiresAt = $now->copy()->addSeconds(self::ACTIVE_TTL_SECONDS);
        $identity = ($userId ? 'user:'.$userId : 'guest:'.$presenceId)
            .':'.$platform.':'.$presenceId;

        $this->mutateEntries(function (array $entries) use ($identity, $presenceId, $platform, $userId, $payload, $now, $expiresAt): array {
            foreach ($entries as $key => $entry) {
                if (($entry['expires_at'] ?? 0) <= $now->timestamp) {
                    unset($entries[$key]);

                    continue;
                }

                // A login on the same browser/device replaces its anonymous entry.
                if ($userId && ($entry['presence_id'] ?? null) === $presenceId && ($entry['platform'] ?? null) === $platform) {
                    if (($entry['user_id'] ?? null) === null || (int) ($entry['user_id'] ?? 0) === $userId) {
                        unset($entries[$key]);
                    }
                }
            }

            $entries[$identity] = [
                'presence_id' => $presenceId,
                'user_id' => $userId,
                'platform' => $platform,
                'current_path' => filled($payload['current_path'] ?? null)
                    ? Str::limit((string) $payload['current_path'], 255, '')
                    : null,
                'app_version' => filled($payload['app_version'] ?? null)
                    ? Str::limit((string) $payload['app_version'], 64, '')
                    : null,
                'last_seen_at' => $now->toIso8601String(),
                'expires_at' => $expiresAt->timestamp,
            ];

            return $entries;
        });

        return [
            'presence_id' => $presenceId,
            'platform' => $platform,
            'expires_at' => $expiresAt->toIso8601String(),
            'heartbeat_interval_seconds' => 45,
        ];
    }

    /**
     * Return deduplicated audience counts. One account can have multiple
     * platforms/devices, so account count and active-device count are distinct.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $now = now()->timestamp;
        $entries = $this->readEntries();
        $active = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => (int) ($entry['expires_at'] ?? 0) > $now
        ));

        $accounts = collect($active)
            ->filter(fn (array $entry): bool => filled($entry['user_id'] ?? null))
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id)
            ->unique();

        $platforms = collect(['web', 'android', 'ios', 'android_tv', 'other'])
            ->mapWithKeys(function (string $platform) use ($active): array {
                $rows = array_filter($active, static fn (array $entry): bool => ($entry['platform'] ?? 'other') === $platform);
                $unique = collect($rows)
                    ->map(fn (array $entry): string => filled($entry['user_id'] ?? null)
                        ? 'user:'.$entry['user_id']
                        : 'guest:'.($entry['presence_id'] ?? 'unknown'))
                    ->unique()
                    ->count();

                return [$platform => $unique];
            })
            ->all();

        $uniquePeople = collect($active)
            ->map(fn (array $entry): string => filled($entry['user_id'] ?? null)
                ? 'user:'.$entry['user_id']
                : 'guest:'.($entry['presence_id'] ?? 'unknown'))
            ->unique()
            ->count();

        return [
            'people_online' => $uniquePeople,
            'unique_accounts_online' => $accounts->count(),
            'guests_online' => max(0, $uniquePeople - $accounts->count()),
            'active_devices' => count($active),
            'platforms' => $platforms,
            'active_window_seconds' => self::ACTIVE_TTL_SECONDS,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function readEntries(): array
    {
        try {
            $entries = $this->cache()->get(self::CACHE_KEY, []);

            return is_array($entries) ? $entries : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @param callable(array<string, array<string, mixed>>): array<string, array<string, mixed>> $callback */
    private function mutateEntries(callable $callback): void
    {
        $mutate = function () use ($callback): void {
            $entries = $callback($this->readEntries());
            $this->cache()->put(self::CACHE_KEY, $entries, self::ACTIVE_TTL_SECONDS);
        };

        try {
            $cache = $this->cache();
            if (method_exists($cache, 'lock')) {
                $cache->lock(self::LOCK_KEY, 3)->block(1, $mutate);

                return;
            }

            $mutate();
        } catch (Throwable) {
            // Redis is recommended in production, but presence must not break
            // sign-in or playback when an optional cache backend is unavailable.
            try {
                $fallback = Cache::store('file');
                $entries = $callback($fallback->get(self::CACHE_KEY, []));
                $fallback->put(self::CACHE_KEY, $entries, self::ACTIVE_TTL_SECONDS);
            } catch (Throwable) {
                // Presence is non-critical telemetry.
            }
        }
    }

    private function cache(): Repository
    {
        $preferred = (string) config('cache.presence_store', 'file');

        if ($preferred === 'redis' && ! class_exists(\Redis::class)) {
            return Cache::store('file');
        }

        try {
            return Cache::store($preferred);
        } catch (Throwable) {
            return Cache::store('file');
        }
    }

    private function normalizePlatform(string $platform): string
    {
        return match (strtolower(trim($platform))) {
            'web', 'nextjs_web' => 'web',
            'android', 'android_mobile' => 'android',
            'ios' => 'ios',
            'android_tv', 'tv', 'android-tv' => 'android_tv',
            default => 'other',
        };
    }
}
