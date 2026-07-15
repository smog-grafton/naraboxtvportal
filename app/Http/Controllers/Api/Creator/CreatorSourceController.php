<?php

namespace App\Http\Controllers\Api\Creator;

use App\Jobs\FetchVideoFromUrlJob;
use App\Jobs\TelegramToContaboImportJob;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\VideoSource;
use App\Services\CdnMediaClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreatorSourceController extends CreatorBaseController
{
    public function __construct(private readonly CdnMediaClientService $cdn)
    {
    }

    /**
     * List all sources for a movie (ownership-gated).
     */
    public function indexForMovie(Request $request, int $movieId): JsonResponse
    {
        $user = $request->user();
        $movie = $this->creatorMovieQuery($user)->find($movieId);

        if (!$movie) {
            return response()->json(['success' => false, 'message' => 'Movie not found or not authorized.'], 404);
        }

        $sources = VideoSource::where('sourceable_type', Movie::class)
            ->where('sourceable_id', $movie->id)
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sources->map(fn($s) => $this->formatVideoSource($s)),
        ]);
    }

    /**
     * Add a new source for a movie.
     *
     * For local upload: creates CDN asset + VideoSource, returns HMAC-signed upload token.
     * For url/fetched: queues direct import into Contabo Object Storage.
     * For youtube/vimeo: creates VideoSource record with url.
     * For telegram: queues Tele-OB import into Contabo Object Storage.
     */
    public function storeForMovie(Request $request, int $movieId): JsonResponse
    {
        $user = $request->user();
        $movie = $this->creatorMovieQuery($user)->find($movieId);

        if (!$movie) {
            return response()->json(['success' => false, 'message' => 'Movie not found or not authorized.'], 404);
        }

        $validated = $request->validate([
            'type'     => ['required', 'in:local,url,youtube,vimeo,fetched,telegram,tele_ob,contabo_object_storage'],
            'url'      => ['required_if:type,url,youtube,vimeo,fetched,telegram,tele_ob,contabo_object_storage', 'nullable', 'string', 'max:2048'],
            'quality'  => ['nullable', 'string', 'max:20'],
            'filename' => ['required_if:type,local', 'nullable', 'string', 'max:255'],
            'mime'     => ['nullable', 'string', 'max:100'],
            'size'     => ['nullable', 'integer'],
            'is_primary' => ['boolean'],
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
            'data' => $sources->map(fn($s) => $this->formatVideoSource($s)),
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
            'type'     => ['required', 'in:url,youtube,vimeo,fetched,telegram,tele_ob,contabo_object_storage'],
            'url'      => ['required', 'string', 'max:2048'],
            'quality'  => ['nullable', 'string', 'max:20'],
            'is_primary' => ['boolean'],
        ]);

        $type = $validated['type'];
        $quality = $validated['quality'] ?? 'auto';
        $creatorMeta = $this->buildCreatorEpisodeMeta($request->user(), $episode);

        switch ($type) {
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

        if (!$movie) {
            return response()->json(['success' => false, 'message' => 'Movie not found or not authorized.'], 404);
        }

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mime'     => ['nullable', 'string', 'max:100'],
            'size'     => ['nullable', 'integer'],
            'quality'  => ['nullable', 'string', 'max:20'],
        ]);

        $filename = $validated['filename'];
        $mime     = $validated['mime'] ?? null;
        $size     = $validated['size'] ?? null;
        $quality  = $validated['quality'] ?? 'auto';

        if ($tooLarge = $this->rejectOversizedCreatorUpload($size)) {
            return $tooLarge;
        }

        // Ensure CDN asset exists for this movie
        $assetId = $movie->cdn_asset_id;
        if (!$assetId) {
            $assetResult = $this->cdn->importFromUrl('', $movie->title, 'movie');
            if (!empty($assetResult['data']['id'])) {
                $assetId = $assetResult['data']['id'];
                $movie->update(['cdn_asset_id' => $assetId]);
            }
        }

        // Create VideoSource record as placeholder
        $source = VideoSource::create([
            'sourceable_type' => Movie::class,
            'sourceable_id'   => $movie->id,
            'type'            => 'local',
            'quality'         => $quality,
            'is_primary'      => !VideoSource::where('sourceable_type', Movie::class)
                ->where('sourceable_id', $movie->id)->exists(),
            'is_active'       => false,
            'metadata'        => [
                'cdn_asset_id'  => $assetId,
                'cdn_status'    => 'pending_upload',
                'filename'      => $filename,
            ],
        ]);

        // Generate HMAC signature
        $secret = (string) config('services.cdn.ingest_secret', '');
        $timestamp = time();
        $nonce = Str::uuid()->toString();
        $canonical = implode('|', [$timestamp, $nonce, $source->id, $assetId, $filename, (string) ($size ?? ''), (string) ($mime ?? '')]);
        $signature = hash_hmac('sha256', $canonical, $secret);

        $cdnBase = rtrim((string) config('services.cdn.base_url', ''), '/');
        $ingestPath = (string) config('services.cdn.ingest_endpoint', '/api/ingest/asset-source-upload');

        return response()->json([
            'success' => true,
            'data' => [
                'source_id'      => $source->id,
                'asset_id'       => $assetId,
                'cdn_ingest_url' => $cdnBase . $ingestPath,
                'signature'      => $signature,
                'timestamp'      => $timestamp,
                'nonce'          => $nonce,
                'filename'       => $filename,
                'mime_type'      => $mime,
                'size_bytes'     => $size,
            ],
        ]);
    }

    /**
     * Poll CDN source status and return the result.
     */
    public function status(Request $request, int $sourceId): JsonResponse
    {
        $user = $request->user();
        $source = VideoSource::find($sourceId);

        if (!$source) {
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
            if ($cdnResult['ok'] && !empty($cdnResult['data'])) {
                $cdnData = $cdnResult['data'];
                $newMeta = array_merge($metadata, [
                    'cdn_status'    => $cdnData['status'] ?? $metadata['cdn_status'],
                    'hls_master_url'=> $cdnData['hls_master_url'] ?? null,
                    'mp4_play_url'  => $cdnData['mp4_url'] ?? null,
                    'progress'      => $cdnData['progress_percent'] ?? null,
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

        if (!$source) {
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
        $filename = $validated['filename'];
        $mime = $validated['mime'] ?? null;
        $size = $validated['size'] ?? null;

        if ($tooLarge = $this->rejectOversizedCreatorUpload($size)) {
            return $tooLarge;
        }

        // Ensure CDN asset exists
        $assetId = $movie->cdn_asset_id;
        if (!$assetId) {
            $assetResult = $this->cdn->importFromUrl('', $movie->title, 'movie');
            if (!empty($assetResult['data']['id'])) {
                $assetId = $assetResult['data']['id'];
                $movie->update(['cdn_asset_id' => $assetId]);
            }
        }

        $source = VideoSource::create([
            'sourceable_type' => Movie::class,
            'sourceable_id'   => $movie->id,
            'type'            => 'local',
            'quality'         => $quality,
            'is_primary'      => $validated['is_primary'] ?? !VideoSource::where('sourceable_type', Movie::class)->where('sourceable_id', $movie->id)->exists(),
            'is_active'       => false,
            'metadata'        => array_merge([
                'cdn_asset_id'  => $assetId,
                'cdn_status'    => 'pending_upload',
                'filename'      => $filename,
            ], $creatorMeta),
        ]);

        // Generate HMAC upload token
        $secret = (string) config('services.cdn.ingest_secret', '');
        $timestamp = time();
        $nonce = Str::uuid()->toString();
        $canonical = implode('|', [$timestamp, $nonce, $source->id, $assetId, $filename, (string) ($size ?? ''), (string) ($mime ?? '')]);
        $signature = hash_hmac('sha256', $canonical, $secret);

        $cdnBase = rtrim((string) config('services.cdn.base_url', ''), '/');
        $ingestPath = (string) config('services.cdn.ingest_endpoint', '/api/ingest/asset-source-upload');

        return response()->json([
            'success' => true,
            'message' => 'Upload token generated. Upload the file directly to CDN.',
            'data' => array_merge(
                $this->formatVideoSource($source),
                [
                    'upload_token' => [
                        'cdn_ingest_url' => $cdnBase . $ingestPath,
                        'asset_id'       => $assetId,
                        'source_id'      => $source->id,
                        'signature'      => $signature,
                        'timestamp'      => $timestamp,
                        'nonce'          => $nonce,
                        'filename'       => $filename,
                        'mime_type'      => $mime,
                        'size_bytes'     => $size,
                    ],
                ]
            ),
        ], 201);
    }

    private function handleRemoteFetchToContabo(Movie $movie, array $validated, string $quality, array $creatorMeta): JsonResponse
    {
        $url = trim((string) ($validated['url'] ?? ''));
        if ($url === '') {
            return response()->json(['success' => false, 'message' => 'Video URL is required.'], 422);
        }

        $source = VideoSource::create([
            'sourceable_type' => Movie::class,
            'sourceable_id'   => $movie->id,
            'type'            => 'contabo_object_storage',
            'url'             => $url,
            'quality'         => $quality,
            'format'          => 'mp4',
            'is_primary'      => $validated['is_primary'] ?? !VideoSource::where('sourceable_type', Movie::class)->where('sourceable_id', $movie->id)->exists(),
            'is_active'       => false,
            'metadata'        => array_merge([
                'provider'       => 'contabo_object_storage',
                'storage_target' => 'contabo_object_storage',
                'fetch_status'   => 'queued',
                'fetch_mode'     => 'creator_queue',
                'source_url'     => $url,
                'last_message'   => 'Remote URL import queued for Contabo Object Storage.',
                'queued_at'      => now()->toDateTimeString(),
            ], $creatorMeta),
        ]);

        FetchVideoFromUrlJob::dispatch(
            $source->id,
            $url,
            Movie::class,
            (int) $movie->id,
            $quality,
            'auto',
            'contabo_object_storage'
        )->onQueue('contabo-imports');

        return response()->json([
            'success' => true,
            'message' => 'Remote video import queued for Object Storage. Status will update here.',
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

        if ($cdnAssetId && !$assetId) {
            $movie->update(['cdn_asset_id' => $cdnAssetId]);
            $assetId = $cdnAssetId;
        }

        $source = VideoSource::create([
            'sourceable_type' => Movie::class,
            'sourceable_id'   => $movie->id,
            'type'            => $type,
            'url'             => $url,
            'quality'         => $quality,
            'is_primary'      => $validated['is_primary'] ?? !VideoSource::where('sourceable_type', Movie::class)->where('sourceable_id', $movie->id)->exists(),
            'is_active'       => false,
            'metadata'        => array_merge([
                'cdn_asset_id'  => $assetId,
                'cdn_source_id' => $cdnSourceId,
                'cdn_status'    => $importResult['ok'] ? 'importing' : 'failed',
                'fetch_status'  => $importResult['ok'] ? 'downloading' : 'failed',
            ], $creatorMeta),
        ]);

        return response()->json([
            'success' => true,
            'message' => $importResult['ok']
                ? 'Remote fetch started. Poll status to track progress.'
                : 'CDN import failed: ' . ($importResult['error'] ?? 'unknown error'),
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function handleExternalEmbed(Movie $movie, array $validated, string $quality, string $type): JsonResponse
    {
        $source = VideoSource::create([
            'sourceable_type' => Movie::class,
            'sourceable_id'   => $movie->id,
            'type'            => $type,
            'url'             => $validated['url'],
            'quality'         => $quality,
            'is_primary'      => $validated['is_primary'] ?? !VideoSource::where('sourceable_type', Movie::class)->where('sourceable_id', $movie->id)->exists(),
            'is_active'       => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($type) . ' source added.',
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function handleTelegramSource(Movie $movie, array $validated, string $quality, array $creatorMeta): JsonResponse
    {
        if ($capacityError = $this->rejectTeleObPortalOverflow()) {
            return $capacityError;
        }

        $telegramUrl = trim((string) ($validated['url'] ?? ''));
        if ($telegramUrl === '') {
            return response()->json(['success' => false, 'message' => 'Telegram URL is required.'], 422);
        }

        $source = VideoSource::create([
            'sourceable_type' => Movie::class,
            'sourceable_id'   => $movie->id,
            'type'            => 'tele_ob',
            'url'             => $telegramUrl,
            'format'          => 'mp4',
            'quality'         => $quality,
            'is_primary'      => $validated['is_primary'] ?? !VideoSource::where('sourceable_type', Movie::class)->where('sourceable_id', $movie->id)->exists(),
            'is_active'       => false,
            'metadata'        => array_merge([
                'provider'        => 'tele_ob',
                'storage_target'  => 'contabo_object_storage',
                'fetch_status'    => 'queued',
                'fetch_mode'      => 'tele_ob_queue',
                'telegram_status' => 'telegram_submitted',
                'telegram_url'    => $telegramUrl,
                'source_url'      => $telegramUrl,
                'source_role'     => 'telegram',
                'last_message'    => 'Telegram to Object Storage import queued.',
                'queued_at'       => now()->toDateTimeString(),
            ], $creatorMeta),
        ]);

        TelegramToContaboImportJob::dispatch($source->id)->onQueue('tele-ob-imports');

        return response()->json([
            'success' => true,
            'message' => 'Telegram import queued. Telebot will fetch it and Portal will store it on Object Storage.',
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function handleEpisodeRemoteFetchToContabo(Episode $episode, array $validated, string $quality, array $creatorMeta): JsonResponse
    {
        $url = trim((string) ($validated['url'] ?? ''));
        if ($url === '') {
            return response()->json(['success' => false, 'message' => 'Video URL is required.'], 422);
        }

        $source = VideoSource::create([
            'sourceable_type' => Episode::class,
            'sourceable_id'   => $episode->id,
            'type'            => 'contabo_object_storage',
            'url'             => $url,
            'quality'         => $quality,
            'format'          => 'mp4',
            'is_primary'      => $validated['is_primary'] ?? !VideoSource::where('sourceable_type', Episode::class)->where('sourceable_id', $episode->id)->exists(),
            'is_active'       => false,
            'metadata'        => array_merge([
                'provider'       => 'contabo_object_storage',
                'storage_target' => 'contabo_object_storage',
                'fetch_status'   => 'queued',
                'fetch_mode'     => 'creator_queue',
                'source_url'     => $url,
                'last_message'   => 'Episode remote URL import queued for Contabo Object Storage.',
                'queued_at'      => now()->toDateTimeString(),
            ], $creatorMeta),
        ]);

        FetchVideoFromUrlJob::dispatch(
            $source->id,
            $url,
            Episode::class,
            (int) $episode->id,
            $quality,
            'auto',
            'contabo_object_storage'
        )->onQueue('contabo-imports');

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
            'sourceable_id'   => $episode->id,
            'type'            => $type,
            'url'             => $validated['url'],
            'quality'         => $quality,
            'is_primary'      => $validated['is_primary'] ?? !VideoSource::where('sourceable_type', Episode::class)->where('sourceable_id', $episode->id)->exists(),
            'is_active'       => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => ucfirst($type) . ' episode source added.',
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function handleEpisodeTelegramSource(Episode $episode, array $validated, string $quality, array $creatorMeta): JsonResponse
    {
        if ($capacityError = $this->rejectTeleObPortalOverflow()) {
            return $capacityError;
        }

        $telegramUrl = trim((string) ($validated['url'] ?? ''));
        if ($telegramUrl === '') {
            return response()->json(['success' => false, 'message' => 'Telegram URL is required.'], 422);
        }

        $source = VideoSource::create([
            'sourceable_type' => Episode::class,
            'sourceable_id'   => $episode->id,
            'type'            => 'tele_ob',
            'url'             => $telegramUrl,
            'format'          => 'mp4',
            'quality'         => $quality,
            'is_primary'      => $validated['is_primary'] ?? !VideoSource::where('sourceable_type', Episode::class)->where('sourceable_id', $episode->id)->exists(),
            'is_active'       => false,
            'metadata'        => array_merge([
                'provider'        => 'tele_ob',
                'storage_target'  => 'contabo_object_storage',
                'fetch_status'    => 'queued',
                'fetch_mode'      => 'tele_ob_queue',
                'telegram_status' => 'telegram_submitted',
                'telegram_url'    => $telegramUrl,
                'source_url'      => $telegramUrl,
                'source_role'     => 'telegram',
                'last_message'    => 'Episode Telegram to Object Storage import queued.',
                'queued_at'       => now()->toDateTimeString(),
            ], $creatorMeta),
        ]);

        TelegramToContaboImportJob::dispatch($source->id)->onQueue('tele-ob-imports');

        return response()->json([
            'success' => true,
            'message' => 'Episode Telegram import queued. Telebot will fetch it and Portal will store it on Object Storage.',
            'data' => $this->formatVideoSource($source),
        ], 201);
    }

    private function rejectTeleObPortalOverflow(): ?JsonResponse
    {
        $limit = max(1, (int) config('services.telebot.max_portal_objects', 3));
        $active = VideoSource::query()
            ->where('type', 'tele_ob')
            ->where('is_active', false)
            ->whereIn('metadata->fetch_status', ['queued', 'processing', 'downloading'])
            ->count();

        if ($active < $limit) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Tele-OB queue is currently full. Wait for one Telegram import to finish before adding another.',
        ], 429);
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
            'message' => 'Direct uploads are limited to ' . $this->formatBytes($maxBytes) . ' per movie source.',
            'max_bytes' => $maxBytes,
        ], 422);
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

        return rtrim(rtrim(number_format($value, 2), '0'), '.') . ' ' . $units[$index];
    }

    private function buildCreatorMeta(\App\Models\User $user, Movie $movie): array
    {
        $meta = ['portal_movie_id' => $movie->id];

        if ($user->isVJ()) {
            $vj = $this->resolveVjProfile($user);
            $meta['creator_ref'] = $vj ? 'vj:' . $vj->id : 'user:' . $user->id;
            $meta['creator_type'] = 'vj';
        } elseif ($user->isMediaLibrary()) {
            $library = $this->resolveMediaLibraryProfile($user);
            $meta['creator_ref'] = $library ? 'media_library:' . $library->id : 'user:' . $user->id;
            $meta['creator_type'] = 'media_library';
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

        if ($user->isVJ()) {
            $vj = $this->resolveVjProfile($user);
            $meta['creator_ref'] = $vj ? 'vj:' . $vj->id : 'user:' . $user->id;
            $meta['creator_type'] = 'vj';
        } elseif ($user->isMediaLibrary()) {
            $library = $this->resolveMediaLibraryProfile($user);
            $meta['creator_ref'] = $library ? 'media_library:' . $library->id : 'user:' . $user->id;
            $meta['creator_type'] = 'media_library';
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
