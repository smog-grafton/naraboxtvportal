<?php

namespace App\Services;

use App\Models\AdminAlertSetting;
use App\Models\MediaPlaybackReport;
use App\Models\PlaybackSession;
use App\Models\VideoSource;

class PlaybackHealthService
{
    public function createReport(array $data): MediaPlaybackReport
    {
        $session = null;

        if (! empty($data['playback_session_id'])) {
            $session = PlaybackSession::query()->find($data['playback_session_id']);
        }

        $report = MediaPlaybackReport::create([
            'user_id' => $data['user_id'] ?? null,
            'playback_session_id' => $session?->id,
            'source_id' => $data['source_id'] ?? null,
            'source_role' => $data['source_role'] ?? null,
            'server_key' => $data['server_key'] ?? null,
            'source_format' => $data['source_format'] ?? null,
            'media_type' => strtoupper((string) $data['media_type']),
            'media_id' => $data['media_id'],
            'episode_id' => $data['episode_id'] ?? null,
            'error_type' => $data['error_type'],
            'error_message' => $data['error_message'] ?? null,
            'http_status' => $data['http_status'] ?? null,
            'player_error_code' => $data['player_error_code'] ?? null,
            'playback_url' => $this->redactPlaybackUrl($data['playback_url'] ?? null),
            'device' => $data['device'] ?? 'web',
            'platform' => $data['platform'] ?? $data['device'] ?? 'web',
            'app_version' => $data['app_version'] ?? null,
            'attempt_number' => $data['attempt_number'] ?? null,
            'fallback_source_id' => $data['fallback_source_id'] ?? null,
            'fallback_succeeded' => $data['fallback_succeeded'] ?? null,
            'startup_time_ms' => $data['startup_time_ms'] ?? $data['load_time_ms'] ?? null,
            'load_time_ms' => $data['load_time_ms'] ?? $session?->startup_ms,
            'buffering_count' => $data['buffering_count'] ?? $session?->buffer_count,
            'buffering_duration_ms' => $data['buffering_duration_ms'] ?? $session?->total_buffer_ms,
            'status' => 'open',
        ]);

        $this->applyFlags($report);
        $this->applySourceHealthSignal($report);

        return $report->fresh();
    }

    private function redactPlaybackUrl(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $safe = $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '');

        return mb_substr($safe, 0, 255);
    }

    private function applySourceHealthSignal(MediaPlaybackReport $report): void
    {
        if ($report->fallback_succeeded && $report->fallback_source_id) {
            $fallback = VideoSource::query()->find($report->fallback_source_id);
            if ($fallback) {
                VideoSource::withoutEvents(fn () => $fallback->forceFill([
                    'health_status' => 'healthy',
                    'last_health_check_at' => now(),
                    'last_health_error' => null,
                    'consecutive_failures' => 0,
                    'verified_at' => $fallback->verified_at ?: now(),
                ])->save());
            }
        }

        if (! $report->source_id || ! in_array($report->error_type, ['not_found', 'playback_failed', 'manifest_failed'], true)) {
            return;
        }

        $source = VideoSource::query()->find($report->source_id);
        if (! $source) {
            return;
        }

        $failures = (int) $source->consecutive_failures + 1;
        $permanent = in_array((int) $report->http_status, [404, 410], true);
        VideoSource::withoutEvents(fn () => $source->forceFill([
            'health_status' => $permanent ? 'unreachable' : 'degraded',
            'last_health_check_at' => now(),
            'last_http_status' => $report->http_status,
            'last_health_error' => 'Playback client reported a source initialization failure.',
            'consecutive_failures' => $failures,
        ])->save());

        $threshold = max(2, (int) config('video_sources.health.failure_threshold', 2));
        if (! $source->is_primary || $failures < $threshold || ! $source->sourceable) {
            return;
        }

        $fallback = app(MediaSourceSelectionService::class)
            ->candidatesFor($source->sourceable, $report->platform)
            ->first(fn (VideoSource $candidate): bool => $candidate->id !== $source->id
                && $candidate->health_status === 'healthy'
            );
        if ($fallback) {
            app(MediaSourceSelectionService::class)->promoteIfHealthy($fallback, 'repeated_playback_failure');
        }
    }

    public function applyFlags(MediaPlaybackReport $report): void
    {
        $settings = AdminAlertSetting::current();

        $recentFailureCount = MediaPlaybackReport::query()
            ->where('media_type', $report->media_type)
            ->where('media_id', $report->media_id)
            ->where('episode_id', $report->episode_id)
            ->whereIn('error_type', ['not_found', 'timeout', 'buffering', 'playback_failed', 'manifest_failed', 'slow_start'])
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $isSlow = ($report->load_time_ms ?? 0) >= $settings->slow_start_threshold_ms;
        $needsAttention = $recentFailureCount >= $settings->playback_failure_threshold;

        $report->update([
            'report_count' => $recentFailureCount,
            'is_slow' => $isSlow,
            'needs_attention' => $needsAttention,
        ]);

        if ($needsAttention || $isSlow) {
            app(AdminAlertService::class)->queue(
                type: 'playback_issue',
                title: $isSlow ? 'Slow-loading content detected' : 'Repeated playback failures detected',
                message: "Playback issue reported for {$report->media_type} #{$report->media_id}.",
                payload: $report->toArray(),
            );
        }
    }
}
