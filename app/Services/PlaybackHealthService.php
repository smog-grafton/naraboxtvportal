<?php

namespace App\Services;

use App\Models\AdminAlertSetting;
use App\Models\MediaPlaybackReport;
use App\Models\PlaybackSession;

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
            'media_type' => strtoupper((string) $data['media_type']),
            'media_id' => $data['media_id'],
            'episode_id' => $data['episode_id'] ?? null,
            'error_type' => $data['error_type'],
            'error_message' => $data['error_message'] ?? null,
            'playback_url' => $data['playback_url'] ?? null,
            'device' => $data['device'] ?? 'web',
            'app_version' => $data['app_version'] ?? null,
            'load_time_ms' => $data['load_time_ms'] ?? $session?->startup_ms,
            'buffering_count' => $data['buffering_count'] ?? $session?->buffer_count,
            'buffering_duration_ms' => $data['buffering_duration_ms'] ?? $session?->total_buffer_ms,
            'status' => 'open',
        ]);

        $this->applyFlags($report);

        return $report->fresh();
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
