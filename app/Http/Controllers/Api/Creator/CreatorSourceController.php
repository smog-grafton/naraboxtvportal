<?php

namespace App\Http\Controllers\Api\Creator;

use App\Jobs\FetchVideoFromUrlJob;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\VideoSource;
use App\Models\CreatorContentSubmission;
use App\Models\CreatorUploadSession;
use App\Services\CdnMediaClientService;
use App\Services\CreatorContentWorkflowService;
use App\Services\NbxVideoSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreatorSourceController extends CreatorBaseController
{
    public function __construct(
        private readonly CdnMediaClientService $cdn,
        private readonly NbxVideoSourceService $nbx,
        private readonly CreatorContentWorkflowService $workflow
    ) {
    }

    /**
     * List all sources for a movie (ownership-gated).
     */
    public function indexForMovie(Request $request, int $movieId): JsonResponse
    {
        $user = $request->user();
        $movie = $this->creatorMovieQuery($user)->find($movieId);

        if (! $movie) {
            return response()->json(['success' => false, 'message' => 'Movie not found or not authorized.'], 404);
        }

        $sources = VideoSource::where('sourceable_type', Movie::class)
            ->where('sourceable_id', $movie->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sources->map(fn ($s) => $this->formatVideoSource($s)),
        ]);
    }

    /**
     * Add a new source for a movie.
     *
     * For local upload: creates CDN asset + VideoSource, returns HMAC-signed upload token.
     * For url/fetched: queues direct import into the selected object-storage target.
     * For youtube/vimeo: creates VideoSource record with url.
     * For telegram: queues Tele-OB import into the selected object-storage target.
     */
    public function storeForMovie(Request $request, int $movieId): JsonResponse
    {
        $user = $request->user();
        $movie = $this->creatorMovieQuery($user)->find($movieId);

        if (! $movie) {
            return response()->json(['success' => false, 'message' => 'Movie not found or not authorized.'], 404);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:local,url,youtube,vimeo,fetched,telegram,tele_ob,contabo_object_storage'],
            'url' => ['required_if:type,url,youtube,vimeo,fetched,telegram,tele_ob,contabo_object_storage', 'nullable', 'string', 'max:2048'],
            'quality' => ['nullable', 'string', 'max:20'],
            'filename' => ['required_if:type,local', 'nullable', 'string', 'max:255'],
            'mime' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'integer'],
            'is_primary' => ['boolean'],
            'compress_enabled' => ['nullable', 'boolean'],
            'hls_480p' => ['nullable', 'boolean'],
            'hls_720p' => ['nullable', 'boolean'],
            'hls_1080p' => ['nullable', 'boolean'],
            'retention_policy' => ['nullable', 'in:optimized_only,keep_original_only,retain_original'],
            'max_resolution' => ['nullable', 'integer', 'in:480,720,1080'],
            'processing_profile' => ['nullable', Rule::in(array_keys((array) config('creator.processing_profiles', [])))],
            'storage_target_key' => ['nullable', Rule::in($this->creatorStorageTargetKeys())],
            'nbx_storage_target' => ['nullable', Rule::in($this->creatorStorageTargetKeys())],
        ]);

        $type = $validated['type'];
        $quality = $validated['quality'] ?? 'auto';

        // Resolve creator metadata for CDN source_metadata
        $creatorMeta = $this->buildCreatorMeta($user, $movie);

        switch ($type) {
            case 'local':
                return $this->handleLocalUpload($movie, $validated, $quality, $creatorMeta);

            case 'url':
            case 'fetched':
            case 'contabo_object_storage':
                return $this->handleRemoteFetchToContabo($movie, $validated, $quality, $creatorMeta);

            case 'youtube':
            case 'vimeo':
                return $this->handleExternalEmbed($movie, $validated, $quality, $type);

            case 'telegram':
            case 'tele_ob':
                return $this->handleTelegramSource($movie, $validated, $quality, $creatorMeta);
        }

        return response()->json(['success' => false, 'message' => 'Unsupported source type.'], 422);
    }

    /**
     * List all sources for an episode (ownership-gated through the parent TV show).
     */
    public function indexForEpisode(Request $request, int $episodeId): JsonResponse
    {
        $episode = $this->creatorEpisode($request, $episodeId);

        if (! $episode) {
            return response()->json(['success' => false, 'message' => 'Episode not found or not authorized.'], 404);
        }

        $sources = VideoSource::where('sourceable_type', Episode::class)
            ->where('sourceable_id', $episode->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sources->map(fn ($s) => $this->formatVideoSource($s)),
        ]);
    }

    /**
     * Add a new source for an episode.
     *
     * Episodes support remote URL/Object Storage, external embeds, and Tele-OB queue imports.
     * Browser direct upload stays movie-only for now because it relies on the movie CDN asset.
     */
    public function storeForEpisode(Request $request, int $episodeId): JsonResponse
    {
        $episode = $this->creatorEpisode($request, $episodeId);

        if (! $episode) {
            return response()->json(['success' => false, 'message' => 'Episode not found or not authorized.'], 404);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:local,url,youtube,vimeo,fetched,telegram,tele_ob,contabo_object_storage'],
            'url' => ['required_unless:type,local', 'nullable', 'string', 'max:2048'],
            'quality' => ['nullable', 'string', 'max:20'],
            'filename' => ['required_if:type,local', 'nullable', 'string', 'max:255'],
            'mime' => ['nullable', 'string', 'max:150'],
            'size' => ['required_if:type,local', 'nullable', 'integer', 'min:1'],
            'is_primary' => ['boolean'],
            'compress_enabled' => ['nullable', 'boolean'],
            'hls_480p' => ['nullable', 'boolean'],
            'hls_720p' => ['nullable', 'boolean'],
            'hls_1080p' => ['nullable', 'boolean'],
            'retention_policy' => ['nullable', 'in:optimized_only,keep_original_only,retain_original'],
            'max_resolution' => ['nullable', 'integer', 'in:480,720,1080'],
            'processing_profile' => ['nullable', Rule::in(array_keys((array) config('creator.processing_profiles', [])))],
            'storage_target_key' => ['nullable', Rule::in($this->creatorStorageTargetKeys())],
            'nbx_storage_target' => ['nullable', Rule::in($this->creatorStorageTargetKeys())],
        ]);

        $type = $validated['type'];
        $quality = $validated['quality'] ?? 'auto';
        $creatorMeta = $this->buildCreatorEpisodeMeta($request->user(), $episode);

        switch ($type) {
            case 'local':
                return $this->handleDirectUpload($episode, $validated, $quality, $creatorMeta, 'episode');

            case 'url':
            case 'fetched':
            case 'contabo_object_storage':
                return $this->handleEpisodeRemoteFetchToContabo($episode, $validated, $quality, $creatorMeta);

            case 'youtube':
            case 'vimeo':
                return $this->handleEpisodeExternalEmbed($episode, $validated, $quality, $type);

            case 'telegram':
            case 'tele_ob':
                return $this->handleEpisodeTelegramSource($episode, $validated, $quality, $creatorMeta);
        }

        return response()->json(['success' => false, 'message' => 'Unsupported source type.'], 422);
    }

    /**
     * Get CDN upload token for direct browser → CDN upload.
     * Returns HMAC-signed parameters to attach to the CDN ingest request.
     */
    public function cdnUploadToken(Request $request, int $movieId): JsonResponse
    {
        $user = $request->user();
        $movie = $this->creatorMovieQuery($user)->find($movieId);

        if (! $movie) {
            return response()->json(['success' => false, 'message' => 'Movie not found or not authorized.'], 404);
        }

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mime' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'integer'],
            'quality' => ['nullable', 'string', 'max:20'],
            'processing_profile' => ['nullable', Rule::in(array_keys((array) config('creator.processing_profiles', [])))],
            'storage_target_key' => ['nullable', Rule::in($this->creatorStorageTargetKeys())],
            'nbx_storage_target' => ['nullable', Rule::in($this->creatorStorageTargetKeys())],
        ]);

        return $this->handleDirectUpload(
            $movie,
            $validated,
            $validated['quality'] ?? 'auto',
            $this->buildCreatorMeta($user, $movie),
            'movie'
        );
    }

    public function episodeUploadToken(Request $request, int $episodeId): JsonResponse
    {
        $episode = $this->creatorEpisode($request, $episodeId);
        if (! $episode) {
            return response()->json(['success' => false, 'message' => 'Episode not found or not authorized.'], 404);
        }
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mime' => ['nullable', 'string', 'max:150'],
            'size' => ['required', 'integer', 'min:1'],
            'quality' => ['nullable', 'string', 'max:20'],
            'processing_profile' => ['nullable', Rule::in(array_keys((array) config('creator.processing_profiles', [])))],
            'storage_target_key' => ['nullable', Rule::in($this->creatorStorageTargetKeys())],
            'nbx_storage_target' => ['nullable', Rule::in($this->creatorStorageTargetKeys())],
        ]);

        return $this->handleDirectUpload(
            $episode,
            $validated,
            $validated['quality'] ?? 'auto',
            $this->buildCreatorEpisodeMeta($request->user(), $episode),
            'episode'
        );
    }

    public function submissionStatus(Request $request, string $submissionId): JsonResponse
    {
        $submission = CreatorContentSubmission::where('user_id', $request->user()->id)
            ->whereKey($submissionId)
            ->first();
        if (! $submission && ! $request->user()->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Submission not found.'], 404);
        }
        $submission ??= CreatorContentSubmission::findOrFail($submissionId);

        if ($submission->video_source_id) {
            $source = VideoSource::find($submission->video_source_id);
            if ($source && $submission->processing_status !== 'ready') {
                try {
                    $this->nbx->sync($source);
                } catch (\Throwable) {
                    // The persisted status remains the reliable response when NBX is transiently unavailable.
                }
                $submission->refresh();
            }
        }

        return response()->json(['success' => true, 'data' => $this->formatSubmission($submission)]);
    }

    public function retry(Request $request, string $submissionId): JsonResponse
    {
        $submission = CreatorContentSubmission::where('user_id', $request->user()->id)
            ->whereKey($submissionId)
            ->firstOrFail();
        if ($submission->processing_status !== 'processing_failed' || ! $submission->video_source_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only failed NBX jobs with a stored source can be retried.',
            ], 422);
        }
        $source = VideoSource::findOrFail($submission->video_source_id);
        if (! $this->creatorOwnsSource($request->user(), $source)) {
            abort(403);
        }
        $failure = (array) ($submission->result_metadata ?? []);
        if (array_key_exists('retryable', $failure) && ! (bool) $failure['retryable']) {
            return response()->json([
                'success' => false,
                'message' => 'This file needs a new upload or support review; retrying the same input would fail again.',
            ], 422);
        }
        $operation = match ((string) ($failure['error_code'] ?? '')) {
            'STORAGE_UPLOAD_FAILED' => 'retry_storage',
            'PORTAL_SYNC_FAILED' => 'retry_portal_sync',
            default => 'retry',
        };
        try {
            $source = $this->nbx->runExplicitAction($source, $operation);
            $submission->increment('retry_count');
            $submission->update([
                'processing_status' => 'processing',
                'failure_reason' => null,
                'last_retried_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'NBX retry accepted.',
            'data' => $this->formatSubmission($submission->fresh()),
        ], 202);
    }

    /**
     * Poll CDN source status and return the result.
     */
    public function status(Request $request, int $sourceId): JsonResponse
    {
        $user = $request->user();
        $source = VideoSource::find($sourceId);

        if (! $source) {
            return response()->json(['success' => false, 'message' => 'Source not found.'], 404);
        }

        if (! $this->creatorOwnsSource($user, $source)) {
            return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
        }

        $metadata = $source->metadata ?? [];
        $cdnSourceId = $metadata['cdn_source_id'] ?? null;

        // Poll CDN if we have a CDN source ID
        if ($cdnSourceId) {
            $cdnResult = $this->cdn->getSource((int) $cdnSourceId);
            if ($cdnResult['ok'] && ! empty($cdnResult['data'])) {
                $cdnData = $cdnResult['data'];
                $newMeta = array_merge($metadata, [
                    'cdn_status' => $cdnData['status'] ?? $metadata['cdn_status'],
                    'hls_master_url' => $cdnData['hls_master_url'] ?? null,
                    'mp4_play_url' => $cdnData['mp4_url'] ?? null,
                    'progress' => $cdnData['progress_percent'] ?? null,
                ]);
                $source->update(['metadata' => $newMeta]);
                $metadata = $newMeta;

                // If ready, mark source active
                if (in_array($cdnData['status'] ?? '', ['ready', 'done'])) {
                    $source->update([
                        'is_active' => true,
                        'url' => $cdnData['hls_master_url'] ?? $cdnData['mp4_url'] ?? $source->url,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatVideoSource($source->refresh()),
        ]);
    }

    /**
     * Delete a video source (ownership-gated).
     */
    public function destroy(Request $request, int $sourceId): JsonResponse
    {
        $user = $request->user();
        $source = VideoSource::find($sourceId);

        if (! $source) {
            return response()->json(['success' => false, 'message' => 'Source not found.'], 404);
        }

        if (! $this->creatorOwnsSource($user, $source)) {
            return response()->json(['success' => false, 'message' => 'Not authorized.'], 403);
        }

        $source->delete();

        return response()->json(['success' => true, 'message' => 'Source deleted.']);
    }

    // ─────────────────────────────────────────────── private helpers ──

    private function handleLocalUpload(Movie $movie, array $validated, string $quality, array $creatorMeta): JsonResponse
    {
        return $this->handleDirectUpload($movie, $validated, $quality, $creatorMeta, 'movie');
    }

    private function handleRemoteFetchToContabo(Movie $movie, array $validated, string $quality, array $creatorMeta): JsonResponse
    {
        $url = trim((string) ($validated['url'] ?? ''));
        if ($url === '') {
            return response()->json(['success' => false, 'message' => 'Video URL is required.'], 422);
        }

        try {
            $source = $this->nbx->submitRemote($movie, $this->creatorNbxPayload(
                $validated,
                $url,
                $quality,
                $creatorMeta,
                ! VideoSource::where('sourceable_type', Movie::class)->where('sourceable_id', $movie->id)->exists(),
            ), 'movie');
            $submission = $this->workflow->submission($movie, request()->user(), 'remote_url');
            $this->workflow->markProcessing($movie, $submission, 'queued', [
                'video_source_id' => $source->id,
                'processing_job_id' => $source->processing_job_id,
                'status_message' => 'NBX accepted the remote URL.',
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Remote video import queued through NBX. Status will update here.',
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function handleRemoteFetch(Movie $movie, array $validated, string $quality, string $type, array $creatorMeta): JsonResponse
    {
        $url = $validated['url'];

        // Ensure CDN asset
        $assetId = $movie->cdn_asset_id;

        $importResult = $this->cdn->importFromUrl($url, $movie->title, 'movie', 'public', null, 'queue');
        $cdnAssetId = $importResult['data']['id'] ?? null;
        $cdnSourceId = $importResult['data']['source_id'] ?? null;

        if ($cdnAssetId && ! $assetId) {
            $movie->update(['cdn_asset_id' => $cdnAssetId]);
            $assetId = $cdnAssetId;
        }

        $source = VideoSource::create([
            'sourceable_type' => Movie::class,
            'sourceable_id' => $movie->id,
            'type' => $type,
            'url' => $url,
            'quality' => $quality,
            'is_primary' => $validated['is_primary'] ?? ! VideoSource::where('sourceable_type', Movie::class)->where('sourceable_id', $movie->id)->exists(),
            'is_active' => false,
            'metadata' => array_merge([
                'cdn_asset_id' => $assetId,
                'cdn_source_id' => $cdnSourceId,
                'cdn_status' => $importResult['ok'] ? 'importing' : 'failed',
                'fetch_status' => $importResult['ok'] ? 'downloading' : 'failed',
            ], $creatorMeta),
        ]);

        return response()->json([
            'success' => true,
            'message' => $importResult['ok']
                ? 'Remote fetch started. Poll status to track progress.'
                : 'CDN import failed: '.($importResult['error'] ?? 'unknown error'),
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function handleExternalEmbed(Movie $movie, array $validated, string $quality, string $type): JsonResponse
    {
        $source = VideoSource::create([
            'sourceable_type' => Movie::class,
            'sourceable_id' => $movie->id,
            'type' => $type,
            'url' => $validated['url'],
            'quality' => $quality,
            'is_primary' => $validated['is_primary'] ?? ! VideoSource::where('sourceable_type', Movie::class)->where('sourceable_id', $movie->id)->exists(),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($type).' source added.',
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function handleTelegramSource(Movie $movie, array $validated, string $quality, array $creatorMeta): JsonResponse
    {
        $telegramUrl = trim((string) ($validated['url'] ?? ''));
        if ($telegramUrl === '') {
            return response()->json(['success' => false, 'message' => 'Telegram URL is required.'], 422);
        }

        try {
            $source = $this->nbx->submitTelegram($movie, $this->creatorNbxPayload(
                $validated,
                $telegramUrl,
                $quality,
                $creatorMeta,
                ! VideoSource::where('sourceable_type', Movie::class)->where('sourceable_id', $movie->id)->exists(),
            ), 'movie');
            $submission = $this->workflow->submission($movie, request()->user(), 'telegram');
            $this->workflow->markProcessing($movie, $submission, 'queued', [
                'video_source_id' => $source->id,
                'processing_job_id' => $source->processing_job_id,
                'status_message' => 'NBX accepted the Telegram post and handed it to Teletyde.',
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Telegram import queued through NBX. Teletyde will provide a signed source URL for processing.',
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function handleEpisodeRemoteFetchToContabo(Episode $episode, array $validated, string $quality, array $creatorMeta): JsonResponse
    {
        $url = trim((string) ($validated['url'] ?? ''));
        if ($url === '') {
            return response()->json(['success' => false, 'message' => 'Video URL is required.'], 422);
        }

        try {
            $source = $this->nbx->submitRemote($episode, $this->creatorNbxPayload(
                $validated,
                $url,
                $quality,
                $creatorMeta,
                ! VideoSource::where('sourceable_type', Episode::class)->where('sourceable_id', $episode->id)->exists(),
            ), 'episode');
            $submission = $this->workflow->submission($episode, request()->user(), 'remote_url');
            $this->workflow->markProcessing($episode, $submission, 'queued', [
                'video_source_id' => $source->id,
                'processing_job_id' => $source->processing_job_id,
                'status_message' => 'NBX accepted the episode URL.',
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Episode video import queued for Object Storage. Status will update here.',
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function handleEpisodeExternalEmbed(Episode $episode, array $validated, string $quality, string $type): JsonResponse
    {
        $source = VideoSource::create([
            'sourceable_type' => Episode::class,
            'sourceable_id' => $episode->id,
            'type' => $type,
            'url' => $validated['url'],
            'quality' => $quality,
            'is_primary' => $validated['is_primary'] ?? ! VideoSource::where('sourceable_type', Episode::class)->where('sourceable_id', $episode->id)->exists(),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($type).' episode source added.',
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function handleEpisodeTelegramSource(Episode $episode, array $validated, string $quality, array $creatorMeta): JsonResponse
    {
        $telegramUrl = trim((string) ($validated['url'] ?? ''));
        if ($telegramUrl === '') {
            return response()->json(['success' => false, 'message' => 'Telegram URL is required.'], 422);
        }

        try {
            $source = $this->nbx->submitTelegram($episode, $this->creatorNbxPayload(
                $validated,
                $telegramUrl,
                $quality,
                $creatorMeta,
                ! VideoSource::where('sourceable_type', Episode::class)->where('sourceable_id', $episode->id)->exists(),
            ), 'episode');
            $submission = $this->workflow->submission($episode, request()->user(), 'telegram');
            $this->workflow->markProcessing($episode, $submission, 'queued', [
                'video_source_id' => $source->id,
                'processing_job_id' => $source->processing_job_id,
                'status_message' => 'NBX accepted the Telegram post and handed it to Teletyde.',
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Episode Telegram import queued through NBX.',
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function creatorNbxPayload(
        array $validated,
        string $telegramUrl,
        string $quality,
        array $creatorMeta,
        bool $defaultPrimary,
    ): array {
        return [
            'url' => $telegramUrl,
            'quality' => $quality,
            'format' => 'mp4',
            'is_primary' => (bool) ($validated['is_primary'] ?? $defaultPrimary),
            'is_active' => true,
            'metadata' => $creatorMeta,
            'nbx_storage_target' => $this->creatorStorageTarget($validated),
            'nbx_faststart' => true,
            'nbx_compress_enabled' => (bool) ($validated['compress_enabled'] ?? false),
            'nbx_hls_480p' => (bool) ($validated['hls_480p'] ?? false),
            'nbx_hls_720p' => (bool) ($validated['hls_720p'] ?? false),
            'nbx_hls_1080p' => (bool) ($validated['hls_1080p'] ?? false),
            'nbx_allow_downloads' => true,
            'nbx_allow_hls_streaming' => true,
            'nbx_retention_policy' => (string) ($validated['retention_policy'] ?? 'optimized_only'),
            'nbx_processing_preset' => 'automatic',
            'nbx_max_resolution' => (string) ($validated['max_resolution'] ?? 720),
            'nbx_crf' => 23,
            'nbx_encoder_preset' => 'medium',
            'nbx_audio_bitrate' => '128k',
        ];
    }

    private function rejectOversizedCreatorUpload(?int $size): ?JsonResponse
    {
        $maxBytes = $this->creatorDirectUploadMaxBytes();

        if ($size === null || $size <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'File size is required before a direct upload token can be issued.',
                'max_bytes' => $maxBytes,
            ], 422);
        }

        if ($size <= $maxBytes) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Direct uploads are limited to '.$this->formatBytes($maxBytes).' per movie source.',
            'max_bytes' => $maxBytes,
        ], 422);
    }

    /**
     * @return array<int, string>
     */
    private function creatorStorageTargetKeys(): array
    {
        return array_values(array_unique(array_merge(
            ['auto', 'contabo'],
            array_keys((array) config('storage_targets.targets', [])),
        )));
    }

    private function creatorStorageTarget(array $validated): string
    {
        $requested = (string) ($validated['storage_target_key'] ?? $validated['nbx_storage_target'] ?? 'auto');

        return $requested === '' ? 'auto' : $requested;
    }

    private function handleDirectUpload(
        Movie|Episode $content,
        array $validated,
        string $quality,
        array $creatorMeta,
        string $assetType
    ): JsonResponse {
        $filename = trim((string) ($validated['filename'] ?? ''));
        $size = isset($validated['size']) ? (int) $validated['size'] : null;
        $mime = isset($validated['mime']) ? strtolower(trim((string) $validated['mime'])) : null;
        if ($tooLarge = $this->rejectOversizedCreatorUpload($size)) {
            return $tooLarge;
        }

        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($extension, (array) config('creator.allowed_video_extensions'), true)) {
            return response()->json([
                'success' => false,
                'message' => "The .{$extension} container is not allowed for creator uploads.",
            ], 422);
        }
        if ($mime && ! in_array($mime, (array) config('creator.allowed_video_mimes'), true)) {
            return response()->json([
                'success' => false,
                'message' => "The declared MIME type {$mime} is not allowed.",
            ], 422);
        }

        $profileKey = (string) ($validated['processing_profile'] ?? 'balanced');
        $profiles = (array) config('creator.processing_profiles', []);
        $profile = (array) ($profiles[$profileKey] ?? $profiles['balanced'] ?? []);
        $payload = [
            'filename' => $filename,
            'size_bytes' => $size,
            'mime_type' => $mime,
            'extension' => $extension,
            'quality' => $quality,
            'is_primary' => (bool) ($validated['is_primary'] ?? ! $content->videoSources()->exists()),
            'is_active' => true,
            'metadata' => array_merge($creatorMeta, ['processing_profile' => $profileKey]),
            'nbx_storage_target' => $this->creatorStorageTarget($validated),
            'nbx_faststart' => (bool) ($profile['faststart_enabled'] ?? true),
            'nbx_compress_enabled' => (bool) ($profile['compress_enabled'] ?? true),
            'nbx_hls_480p' => (bool) ($profile['hls_480p'] ?? true),
            'nbx_hls_720p' => (bool) ($profile['hls_720p'] ?? true),
            'nbx_hls_1080p' => (bool) ($profile['hls_1080p'] ?? false),
            'nbx_allow_downloads' => true,
            'nbx_allow_hls_streaming' => true,
            'nbx_retention_policy' => (string) ($profile['retention_policy'] ?? 'optimized_only'),
            'nbx_processing_preset' => 'automatic',
            'nbx_max_resolution' => (int) ($profile['max_resolution'] ?? 1080),
            'nbx_crf' => 23,
            'nbx_encoder_preset' => 'medium',
            'nbx_audio_bitrate' => '128k',
        ];

        try {
            $nbxSession = $this->nbx->initDirectUploadSession($content, $payload, $assetType);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        $submission = $this->workflow->submission($content, request()->user(), 'direct_upload');
        $this->workflow->markProcessing($content, $submission, 'uploading', [
            'processing_profile' => array_merge(['key' => $profileKey], $profile),
            'status_message' => 'Waiting for the browser to upload directly to NBX.',
        ]);
        $nbxSessionId = (string) ($nbxSession['session_id'] ?? '');
        $token = (string) ($nbxSession['headers']['X-NBX-Upload-Token'] ?? '');
        if ($nbxSessionId !== '' && $token !== '') {
            CreatorUploadSession::updateOrCreate(
                ['id' => $nbxSessionId],
                [
                    'submission_id' => $submission->id,
                    'user_id' => request()->user()->id,
                    'token_hash' => hash('sha256', $token),
                    'filename' => $filename,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                    'processing_profile' => array_merge(['key' => $profileKey], $profile),
                    'expires_at' => $nbxSession['expires_at'] ?? now()->addMinutes((int) config('creator.upload_session_ttl_minutes', 30)),
                    'remote_ip' => request()->ip(),
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Short-lived upload session created. Send the file directly to NBX.',
            'data' => [
                'submission' => $this->formatSubmission($submission->fresh()),
                'upload_session' => $nbxSession,
            ],
        ], 201);
    }

    private function formatSubmission(CreatorContentSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'submission_source' => $submission->submission_source,
            'processing_status' => $submission->processing_status,
            'editorial_status' => $submission->editorial_status,
            'publication_status' => $submission->publication_status,
            'monetization_status' => $submission->monetization_status,
            'processing_job_id' => $submission->processing_job_id,
            'video_source_id' => $submission->video_source_id,
            'progress_percent' => $submission->progress_percent,
            'processing_stage' => $submission->processing_stage,
            'status_message' => $submission->status_message,
            'failure_reason' => $submission->failure_reason,
            'processing_profile' => $submission->processing_profile,
            'result_metadata' => $submission->result_metadata,
            'retry_count' => $submission->retry_count,
            'completed_at' => $submission->completed_at?->toIso8601String(),
            'updated_at' => $submission->updated_at?->toIso8601String(),
        ];
    }

    private function creatorDirectUploadMaxBytes(): int
    {
        return max(1, (int) config('services.creator.direct_upload_max_mb', 600)) * 1024 * 1024;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = $bytes;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return rtrim(rtrim(number_format($value, 2), '0'), '.').' '.$units[$index];
    }

    private function buildCreatorMeta(\App\Models\User $user, Movie $movie): array
    {
        $meta = ['portal_movie_id' => $movie->id];

        if ($this->resolveVjProfile($user)) {
            $vj = $this->resolveVjProfile($user);
            $meta['creator_ref'] = $vj ? 'vj:'.$vj->id : 'user:'.$user->id;
            $meta['creator_type'] = 'vj';
        } elseif ($this->resolveMediaLibraryProfile($user)) {
            $library = $this->resolveMediaLibraryProfile($user);
            $meta['creator_ref'] = $library ? 'media_library:'.$library->id : 'user:'.$user->id;
            $meta['creator_type'] = 'media_library';
        } else {
            $meta['creator_ref'] = 'user:'.$user->id;
            $meta['creator_type'] = 'applicant';
        }

        return $meta;
    }

    private function buildCreatorEpisodeMeta(\App\Models\User $user, Episode $episode): array
    {
        $episode->loadMissing('season.tvShow');
        $meta = [
            'portal_episode_id' => $episode->id,
            'portal_tv_show_id' => $episode->season?->tv_show_id,
            'portal_season_id' => $episode->season_id,
        ];

        if ($this->resolveVjProfile($user)) {
            $vj = $this->resolveVjProfile($user);
            $meta['creator_ref'] = $vj ? 'vj:'.$vj->id : 'user:'.$user->id;
            $meta['creator_type'] = 'vj';
        } elseif ($this->resolveMediaLibraryProfile($user)) {
            $library = $this->resolveMediaLibraryProfile($user);
            $meta['creator_ref'] = $library ? 'media_library:'.$library->id : 'user:'.$user->id;
            $meta['creator_type'] = 'media_library';
        } else {
            $meta['creator_ref'] = 'user:'.$user->id;
            $meta['creator_type'] = 'applicant';
        }

        return $meta;
    }

    private function creatorEpisode(Request $request, int $episodeId): ?Episode
    {
        $user = $request->user();
        $episode = Episode::with('season.tvShow')->find($episodeId);

        if (! $episode || ! $episode->season) {
            return null;
        }

        $show = $this->creatorTvShowQuery($user)->find($episode->season->tv_show_id);

        return $show ? $episode : null;
    }

    private function creatorOwnsSource(\App\Models\User $user, VideoSource $source): bool
    {
        if ($source->sourceable_type === Movie::class) {
            return $this->creatorMovieQuery($user)->whereKey($source->sourceable_id)->exists();
        }

        if ($source->sourceable_type === Episode::class) {
            $episode = Episode::with('season')->find($source->sourceable_id);
            if (! $episode || ! $episode->season) {
                return false;
            }

            return $this->creatorTvShowQuery($user)->whereKey($episode->season->tv_show_id)->exists();
        }

        return false;
    }
}
