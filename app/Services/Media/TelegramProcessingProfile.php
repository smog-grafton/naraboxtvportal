<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Structured contract mirrored by Teletyde; never accepts encoder arguments. */
final class TelegramProcessingProfile
{
    public const MODES = [
        'auto' => 'Auto — Recommended',
        'fast_mp4' => 'Fast MP4 Conversion',
        '720p_balanced' => '720p Balanced',
        '720p_compact' => '720p Compact',
        '720p_extra_compact' => '720p Extra Compact',
        '1080p_balanced' => '1080p Balanced',
        'keep_original' => 'Keep Original',
        'custom' => 'Custom',
    ];

    public const HEIGHTS = [480, 720, 1080];

    public const FRAME_RATES = [24, 25, 30, 50, 60];

    public const PRESETS = ['veryfast', 'faster', 'fast'];

    public static function description(string $mode): string
    {
        return match ($mode) {
            'fast_mp4' => 'Quickly prepares MP4 by preserving compatible video and audio. This usually preserves file size; incompatible video still needs conversion for playback.',
            '720p_balanced' => 'Up to 720p with RF 23 by default, preserving more fine detail. Aspect ratio and lower frame rates are preserved.',
            '720p_compact' => 'Up to 720p with RF 26 by default for smaller files and reasonable movie quality.',
            '720p_extra_compact' => 'Up to 720p with RF 27 by default. Saves more space at the cost of fine detail.',
            '1080p_balanced' => 'Up to 1080p with RF 23 by default. Lower-resolution sources are never enlarged.',
            'keep_original' => 'Validates and stores the original without intentional re-encoding. Playback support depends on its original streams.',
            'custom' => 'Choose a resolution limit and constant quality. RF is a quality target; final size depends on each movie.',
            default => 'Inspects the actual media and chooses the fastest compatible operation: preserve, prepare MP4, convert audio, or optimize video only when required.',
        };
    }

    public static function compresses(string $mode): bool
    {
        return in_array($mode, ['720p_balanced', '720p_compact', '720p_extra_compact', '1080p_balanced', 'custom'], true);
    }

    public static function normalize(array $input = []): array
    {
        $validated = Validator::make(['processing' => $input], [
            'processing' => ['array:mode,target_height,rf,max_frame_rate,fast_start,encoder_preset,require_compatible,audio_stream_index'],
            'processing.mode' => ['sometimes', 'required', Rule::in(array_keys(self::MODES))],
            'processing.target_height' => ['sometimes', 'nullable', 'integer', Rule::in(self::HEIGHTS)],
            'processing.rf' => ['sometimes', 'integer', 'between:18,30'],
            'processing.max_frame_rate' => ['sometimes', 'nullable', 'integer', Rule::in(self::FRAME_RATES)],
            'processing.fast_start' => ['sometimes', 'boolean'],
            'processing.encoder_preset' => ['sometimes', Rule::in(self::PRESETS)],
            'processing.require_compatible' => ['sometimes', 'boolean'],
            'processing.audio_stream_index' => ['sometimes', 'nullable', 'integer', 'between:0,255'],
        ])->validate()['processing'];

        $mode = (string) ($validated['mode'] ?? 'auto');
        $height = match ($mode) {
            '720p_balanced', '720p_compact', '720p_extra_compact' => 720,
            '1080p_balanced' => 1080,
            'custom' => isset($validated['target_height']) ? (int) $validated['target_height'] : null,
            default => null,
        };
        $defaultRf = match ($mode) {
            '720p_compact' => 26,
            '720p_extra_compact' => 27,
            default => 23,
        };

        return [
            'mode' => $mode,
            'target_height' => $height,
            'rf' => self::compresses($mode) ? (int) ($validated['rf'] ?? $defaultRf) : 23,
            'max_frame_rate' => $mode === 'custom'
                ? (isset($validated['max_frame_rate']) ? (int) $validated['max_frame_rate'] : null)
                : (self::compresses($mode) ? 30 : null),
            'fast_start' => $mode === 'keep_original' ? false : ($mode === 'custom' ? (bool) ($validated['fast_start'] ?? true) : true),
            'encoder_preset' => $validated['encoder_preset'] ?? 'veryfast',
            'require_compatible' => $mode !== 'keep_original',
            'audio_stream_index' => isset($validated['audio_stream_index']) ? (int) $validated['audio_stream_index'] : null,
        ];
    }
}
