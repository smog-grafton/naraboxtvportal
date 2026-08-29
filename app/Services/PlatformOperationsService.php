<?php

namespace App\Services;

use App\Models\MaintenanceWindow;
use App\Models\PlatformVersionPolicy;
use App\Models\SystemStatusMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PlatformOperationsService
{
    public const PLATFORMS = ['android_mobile', 'ios', 'android_tv', 'nextjs_web'];

    public const FEATURES = [
        'catalogue', 'playback', 'authentication', 'pairing', 'subscriptions',
        'payments', 'mobile_money', 'card_payments', 'downloads', 'comments',
        'search', 'news', 'vj_pages',
    ];

    public function normalizePlatform(?string $platform): string
    {
        return match (strtolower(trim((string) $platform))) {
            'android', 'android_mobile', 'mobile_android' => 'android_mobile',
            'ios', 'iphone', 'ipad' => 'ios',
            'tv', 'android_tv', 'androidtv' => 'android_tv',
            'web', 'next', 'nextjs', 'nextjs_web' => 'nextjs_web',
            default => 'nextjs_web',
        };
    }

    public function bootstrap(string $platform, int $build = 0, ?string $version = null): array
    {
        $platform = $this->normalizePlatform($platform);
        $revision = (int) Cache::get('operations:revision', 1);
        $cacheKey = "operations:bootstrap:{$revision}:{$platform}:{$build}:".sha1((string) $version);

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($platform, $build, $version, $revision): array {
            $messages = $this->statusMessages($platform);
            $windows = $this->maintenanceWindows($platform);
            $maintenance = $this->maintenancePayload($windows);
            $versionPolicy = $this->versionDecision($platform, $build, $version);
            $messageSeverity = $messages->map(fn (SystemStatusMessage $message) => $message->severity)
                ->sortByDesc(fn (string $level) => $this->severityRank($level))
                ->first();
            $severity = $messageSeverity ?? 'operational';
            if ($maintenance['active'] && $this->severityRank('maintenance') > $this->severityRank($severity)) {
                $severity = 'maintenance';
            }

            return [
                'server_time' => now()->toIso8601String(),
                'revision' => (string) $revision,
                'platform' => $platform,
                'client' => ['version' => $version, 'build' => $build],
                'system_status' => [
                    'severity' => $severity,
                    'label' => $this->severityLabel($severity),
                    'operational' => $severity === 'operational',
                ],
                'messages' => $messages->map(fn (SystemStatusMessage $message) => [
                    'id' => $message->id,
                    'revision' => $message->revision,
                    'title' => $message->title,
                    'message' => $message->message,
                    'details' => $message->details,
                    'severity' => $message->severity,
                    'affected_services' => $message->affected_services ?? [],
                    'dismissible' => $message->is_dismissible && ! in_array($message->severity, ['critical', 'maintenance'], true),
                    'show_on_home' => $message->show_on_home,
                    'show_globally' => $message->show_globally,
                    'cta' => $message->cta_label ? ['label' => $message->cta_label, 'url' => $message->cta_url] : null,
                    'starts_at' => $message->starts_at?->toIso8601String(),
                    'ends_at' => $message->ends_at?->toIso8601String(),
                ])->values()->all(),
                'maintenance' => $maintenance,
                'disabled_features' => $maintenance['affected_features'],
                'version_policy' => $versionPolicy,
                'cache_ttl_seconds' => 30,
            ];
        });
    }

    public function maintenanceWindows(string $platform): Collection
    {
        return MaintenanceWindow::query()
            ->currentlyActive()
            ->orderByDesc('priority')
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (MaintenanceWindow $window) => $this->targetsPlatform($window->platforms, $platform))
            ->values();
    }

    public function statusMessages(string $platform): Collection
    {
        return SystemStatusMessage::query()
            ->currentlyActive()
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (SystemStatusMessage $message) => $this->targetsPlatform($message->platforms, $platform))
            ->values();
    }

    public function versionDecision(string $platform, int $build, ?string $version = null): array
    {
        $policy = PlatformVersionPolicy::query()
            ->where('platform', $this->normalizePlatform($platform))
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('effective_at')->orWhere('effective_at', '<=', now()))
            ->first();

        if (! $policy) {
            return ['status' => 'supported', 'prompt_enabled' => false];
        }

        $belowMinimum = $build > 0 && $build < $policy->minimum_build;
        $belowLatest = $build > 0 && $build < $policy->latest_build;
        $inGrace = $policy->grace_period_ends_at?->isFuture() ?? false;
        $requiredByPolicy = $belowLatest && $policy->update_type === 'required' && ! $inGrace;
        $status = ($belowMinimum || $requiredByPolicy)
            ? 'required'
            : ($belowLatest && $policy->prompt_enabled && filled($policy->update_url) ? 'optional' : 'supported');

        return [
            'status' => $status,
            'installed_version' => $version,
            'installed_build' => $build,
            'latest_version' => $policy->latest_version,
            'latest_build' => $policy->latest_build,
            'minimum_version' => $policy->minimum_version,
            'minimum_build' => $policy->minimum_build,
            'title' => $policy->title ?: ($status === 'required' ? 'Update required' : 'Update available'),
            'message' => $policy->message ?: ($status === 'required'
                ? 'This version is no longer supported. Update NaraBox TV to continue.'
                : 'A newer NaraBox TV release is available.'),
            'update_url' => $policy->update_url,
            'release_notes' => $policy->release_notes,
            'prompt_enabled' => $policy->prompt_enabled && filled($policy->update_url),
            'grace_period_ends_at' => $policy->grace_period_ends_at?->toIso8601String(),
            'revision' => $policy->revision,
        ];
    }

    public function maintenancePayload(Collection $windows): array
    {
        if ($windows->isEmpty()) {
            return [
                'active' => false, 'blocking' => false, 'mode' => null,
                'affected_features' => [], 'available_features' => [],
                'allow_read_only' => false,
            ];
        }

        /** @var MaintenanceWindow $primary */
        $primary = $windows->first(fn (MaintenanceWindow $window) => $window->mode === 'full') ?? $windows->first();
        $affected = $windows->flatMap(fn (MaintenanceWindow $window) => $window->affected_features ?? [])->unique()->values()->all();
        $available = $windows->flatMap(fn (MaintenanceWindow $window) => $window->available_features ?? [])->unique()->values()->all();
        $isFull = $primary->mode === 'full';

        return [
            'active' => true,
            'blocking' => $isFull && ! $primary->allow_read_only,
            'id' => $primary->id,
            'revision' => $primary->revision,
            'mode' => $primary->mode,
            'title' => $primary->title,
            'message' => $primary->message,
            'details' => $primary->details,
            'affected_features' => $isFull ? ['all'] : $affected,
            'available_features' => $available,
            'allow_read_only' => $primary->allow_read_only,
            'starts_at' => $primary->starts_at?->toIso8601String(),
            'ends_at' => $primary->ends_at?->toIso8601String(),
        ];
    }

    private function targetsPlatform(?array $platforms, string $platform): bool
    {
        return ! $platforms || in_array('all', $platforms, true) || in_array($platform, $platforms, true);
    }

    private function severityRank(string $severity): int
    {
        return ['operational' => 0, 'advisory' => 1, 'degraded' => 2, 'maintenance' => 3, 'critical' => 4][$severity] ?? 1;
    }

    private function severityLabel(string $severity): string
    {
        return match ($severity) {
            'operational' => 'Operational',
            'degraded' => 'Degraded service',
            'critical' => 'Critical incident',
            'maintenance' => 'Maintenance',
            default => 'Advisory',
        };
    }
}
