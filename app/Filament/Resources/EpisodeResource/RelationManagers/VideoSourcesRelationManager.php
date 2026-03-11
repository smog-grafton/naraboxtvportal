<?php

namespace App\Filament\Resources\EpisodeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\VideoSource;
use App\Services\CdnMediaClientService;
use App\Services\CdnUrlDerivationService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class VideoSourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'videoSources';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        'local' => 'Local Upload',
                        'url' => 'External URL',
                        'youtube' => 'YouTube',
                        'vimeo' => 'Vimeo',
                        'fetched' => 'Fetched (cURL)',
                    ])
                    ->required()
                    ->live()
                    ->default('url'),
                Forms\Components\TextInput::make('url')
                    ->label('Video URL')
                    ->url()
                    ->maxLength(255)
                    ->visible(fn (Forms\Get $get) => in_array($get('type'), ['url', 'youtube', 'vimeo', 'fetched']))
                    ->required(fn (Forms\Get $get) => in_array($get('type'), ['url', 'youtube', 'vimeo', 'fetched']))
                    ->helperText(fn (Forms\Get $get) => $get('type') === 'fetched' ? 'Enter the video URL to fetch and download' : 'Enter the video URL'),
                Forms\Components\Hidden::make('imported_source_id')
                    ->dehydrated(true),
                Forms\Components\Section::make('Fetch Video')
                    ->description('Use Import Now for instant fetch, or Queue Fetch to run in the background while status updates in real time.')
                    ->schema([
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('import_now')
                                ->label('Import Now')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('info')
                                ->size('lg')
                                ->action(function (Forms\Set $set, Forms\Get $get) {
                                    $fetchUrl = $get('url');
                                    
                                    if (empty($fetchUrl)) {
                                        Notification::make()
                                            ->warning()
                                            ->title('URL Required')
                                            ->body('Please enter a video URL first.')
                                            ->send();
                                        return;
                                    }
                                    
                                    try {
                                        $ownerRecord = $this->getOwnerRecord();
                                        
                                        if (!$ownerRecord || !$ownerRecord->id) {
                                            Notification::make()
                                                ->warning()
                                                ->title('Save Required')
                                                ->body('Please save the episode first before fetching videos.')
                                                ->send();
                                            return;
                                        }
                                        
                                        $sourceableType = $ownerRecord::class;
                                        $sourceableId = $ownerRecord->id;
                                        $quality = (string) ($get('quality') ?? config('video_sources.defaults.quality', '480p')) ?: '480p';
                                        $format = (string) ($get('format') ?? config('video_sources.defaults.format', 'mp4')) ?: 'mp4';

                                        $existingSource = VideoSource::where('sourceable_type', $sourceableType)
                                            ->where('sourceable_id', $sourceableId)
                                            ->where('url', $fetchUrl)
                                            ->where('type', 'fetched')
                                            ->first();

                                        if ($existingSource) {
                                            $metadata = (array) ($existingSource->metadata ?? []);
                                            $existingSource->update([
                                                'metadata' => array_merge($metadata, [
                                                    'fetch_status' => 'processing',
                                                    'fetch_mode' => 'import_now',
                                                    'started_at' => now()->toDateTimeString(),
                                                    'last_message' => 'Immediate fetch started.',
                                                ]),
                                            ]);
                                        }
                                        
                                        Notification::make()
                                            ->info()
                                            ->title('Fetching Video...')
                                            ->body('Downloading video file. This may take a while. Please wait...')
                                            ->persistent()
                                            ->send();
                                        
                                        // Call the fetch controller directly (internal call, no HTTP needed)
                                        $fetchController = app(\App\Http\Controllers\Api\VideoFetchController::class);
                                        $request = new \Illuminate\Http\Request([
                                            'url' => $fetchUrl,
                                            'sourceable_type' => $sourceableType,
                                            'sourceable_id' => $sourceableId,
                                            'quality' => $quality,
                                            'format' => $format,
                                            'import_mode' => 'now',
                                        ]);
                                        
                                        $response = $fetchController->fetch($request);
                                        $responseData = json_decode($response->getContent(), true);
                                        
                                        if ($response->getStatusCode() === 200 && isset($responseData['success']) && $responseData['success']) {
                                            $videoSource = $responseData['video_source'];
                                            $fetchedSource = VideoSource::find((int) ($videoSource['id'] ?? 0));

                                            if ($fetchedSource) {
                                                $metadata = (array) ($fetchedSource->metadata ?? []);
                                                $fetchedSource->update([
                                                    'metadata' => array_merge($metadata, [
                                                        'fetch_status' => 'completed',
                                                        'fetch_mode' => 'import_now',
                                                        'completed_at' => now()->toDateTimeString(),
                                                        'last_message' => 'Immediate fetch completed successfully.',
                                                    ]),
                                                ]);
                                            }
                                            
                                            // The VideoFetchController already creates/updates the VideoSource record
                                            // So we just populate the form fields to show the user what was fetched
                                            $set('file_path', $videoSource['file_path']);
                                            $set('file_size', $videoSource['file_size']);
                                            $fmt = (string) ($videoSource['format'] ?? pathinfo($videoSource['file_path'] ?? '', PATHINFO_EXTENSION) ?: 'mp4');
                                            $qual = (string) ($videoSource['quality'] ?? '480p');
                                            $formatOptions = ['mp4', 'mkv', 'webm', 'm3u8'];
                                            $qualityOptions = ['480p', '720p', '1080p', '4K', 'Play 480p', 'hls 480p'];
                                            $set('format', in_array($fmt, $formatOptions, true) ? $fmt : 'mp4');
                                            $set('quality', in_array($qual, $qualityOptions, true) ? $qual : '480p');
                                            $set('type', 'fetched');
                                            $set('imported_source_id', $videoSource['id'] ?? null);
                                            
                                            Notification::make()
                                                ->success()
                                                ->title('Video Fetched Successfully')
                                                ->body('Video has been downloaded and saved to the database. File size: ' . $this->formatBytes(isset($videoSource['file_size']) ? (int) $videoSource['file_size'] : null))
                                                ->send();
                                        } else {
                                            if ($existingSource) {
                                                $metadata = (array) ($existingSource->metadata ?? []);
                                                $existingSource->update([
                                                    'metadata' => array_merge($metadata, [
                                                        'fetch_status' => 'failed',
                                                        'fetch_mode' => 'import_now',
                                                        'completed_at' => now()->toDateTimeString(),
                                                        'last_message' => (string) ($responseData['message'] ?? 'Immediate fetch failed.'),
                                                    ]),
                                                ]);
                                            }

                                            Notification::make()
                                                ->danger()
                                                ->title('Fetch Failed')
                                                ->body($responseData['message'] ?? 'Failed to fetch video')
                                                ->send();
                                        }
                                    } catch (\Exception $e) {
                                        if (isset($existingSource) && $existingSource) {
                                            $metadata = (array) ($existingSource->metadata ?? []);
                                            $existingSource->update([
                                                'metadata' => array_merge($metadata, [
                                                    'fetch_status' => 'failed',
                                                    'fetch_mode' => 'import_now',
                                                    'completed_at' => now()->toDateTimeString(),
                                                    'last_message' => $e->getMessage(),
                                                ]),
                                            ]);
                                        }

                                        Notification::make()
                                            ->danger()
                                            ->title('Fetch Error')
                                            ->body('Error: ' . $e->getMessage())
                                            ->send();
                                    }
                                }),
                            Forms\Components\Actions\Action::make('queue_fetch')
                                ->label('Queue Fetch')
                                ->icon('heroicon-o-clock')
                                ->color('warning')
                                ->size('lg')
                                ->action(function (Forms\Set $set, Forms\Get $get) {
                                    $fetchUrl = trim((string) ($get('url') ?? ''));

                                    if ($fetchUrl === '') {
                                        Notification::make()
                                            ->warning()
                                            ->title('URL Required')
                                            ->body('Please enter a video URL first.')
                                            ->send();
                                        return;
                                    }

                                    $ownerRecord = $this->getOwnerRecord();

                                    if (!$ownerRecord || !$ownerRecord->id) {
                                        Notification::make()
                                            ->warning()
                                            ->title('Save Required')
                                            ->body('Please save the episode first before fetching videos.')
                                            ->send();
                                        return;
                                    }

                                    $sourceableType = $ownerRecord::class;
                                    $sourceableId = $ownerRecord->id;
                                    $quality = (string) ($get('quality') ?? config('video_sources.defaults.quality', '480p')) ?: '480p';
                                    $format = (string) ($get('format') ?? config('video_sources.defaults.format', 'mp4')) ?: 'mp4';

                                    $fetchController = app(\App\Http\Controllers\Api\VideoFetchController::class);
                                    $request = new \Illuminate\Http\Request([
                                        'url' => $fetchUrl,
                                        'sourceable_type' => $sourceableType,
                                        'sourceable_id' => $sourceableId,
                                        'quality' => $quality,
                                        'format' => $format,
                                        'import_mode' => 'queue',
                                    ]);

                                    $response = $fetchController->fetch($request);
                                    $responseData = json_decode($response->getContent(), true);

                                    if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300 && ($responseData['success'] ?? false)) {
                                        $sourcePayload = $responseData['video_source'] ?? [];
                                        $set('imported_source_id', $sourcePayload['id'] ?? null);
                                        Notification::make()
                                            ->success()
                                            ->title('Fetch Queued')
                                            ->body($responseData['message'] ?? 'Background fetch queued. Status will update in real time in the table.')
                                            ->send();
                                    } else {
                                        Notification::make()
                                            ->danger()
                                            ->title('Queue Failed')
                                            ->body($responseData['message'] ?? 'Failed to queue remote fetch on CDN.')
                                            ->send();
                                        return;
                                    }
                                }),
                        ]),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'fetched')
                    ->collapsible()
                    ->collapsed(false),
                Forms\Components\FileUpload::make('file_path')
                    ->label('Video File')
                    ->disk('public')
                    ->directory('videos')
                    ->acceptedFileTypes(['video/mp4', 'video/mkv', 'video/webm', 'video/avi'])
                    ->maxSize(51200) // 50GB
                    ->visible(fn (Forms\Get $get) => $get('type') === 'local')
                    ->required(fn (Forms\Get $get) => $get('type') === 'local'),
                Forms\Components\TextInput::make('file_path')
                    ->label('File Path (Auto-filled after fetch)')
                    ->disabled()
                    ->visible(fn (Forms\Get $get) => $get('type') === 'fetched' && !empty($get('file_path')))
                    ->helperText('This will be automatically filled after fetching the video'),
                Forms\Components\Select::make('quality')
                    ->label('Quality')
                    ->options([
                        '480p' => '480p',
                        '720p' => '720p',
                        '1080p' => '1080p',
                        '4K' => '4K',
                        'Play 480p' => 'Play 480p',
                        'hls 480p' => 'hls 480p',
                    ])
                    ->default('480p')
                    ->required(),
                Forms\Components\Select::make('format')
                    ->label('Format')
                    ->options([
                        'mp4' => 'mp4',
                        'mkv' => 'mkv',
                        'webm' => 'webm',
                        'm3u8' => 'm3u8',
                    ])
                    ->default('mp4')
                    ->required(),
                Forms\Components\TextInput::make('file_size')
                    ->label('File Size (bytes)')
                    ->numeric()
                    ->default(fn () => config('video_sources.defaults.file_size', 380_000_000))
                    ->helperText('CDN/fetch may overwrite when you use Fetch.'),
                Forms\Components\TextInput::make('duration_seconds')
                    ->label('Duration (seconds)')
                    ->numeric()
                    ->default(fn () => config('video_sources.defaults.duration_seconds', 7200)),
                Forms\Components\Toggle::make('is_primary')
                    ->label('Primary Source')
                    ->helperText('Use this as the main playback source')
                    ->default(fn () => config('video_sources.defaults.is_primary', false)),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(fn () => config('video_sources.defaults.is_active', true)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('url')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'local' => 'success',
                        'fetched' => 'info',
                        'youtube' => 'danger',
                        'vimeo' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quality'),
                Tables\Columns\TextColumn::make('format'),
                Tables\Columns\TextColumn::make('metadata.fetch_status')
                    ->label('Fetch Status')
                    ->badge()
                    ->default('n/a')
                    ->color(fn (string $state): string => match ($state) {
                        'queued' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'local' => 'Local',
                        'url' => 'URL',
                        'youtube' => 'YouTube',
                        'vimeo' => 'Vimeo',
                        'fetched' => 'Fetched',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): VideoSource {
                        return $this->createOrUpdateSource(null, $data);
                    }),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('copy_urls')
                    ->label('Copy URLs')
                    ->icon('heroicon-o-clipboard-document')
                    ->modalHeading('Copy URLs')
                    ->modalSubmitAction(false)
                    ->fillForm(function (VideoSource $record): array {
                        $urls = $this->getCdnUrlsForSource($record);
                        return [
                            'hls_master_url' => $urls['hls_master_url'] ?? '',
                            'mp4_playback_url' => $urls['mp4_playback_url'] ?? '',
                            'download_url' => $urls['download_url'] ?? '',
                            'variants' => $urls['variants'] ?? [],
                        ];
                    })
                    ->form([
                        Forms\Components\TextInput::make('hls_master_url')
                            ->label('HLS Master URL')
                            ->readOnly(),
                        Forms\Components\TextInput::make('mp4_playback_url')
                            ->label('MP4 Playback URL')
                            ->readOnly(),
                        Forms\Components\TextInput::make('download_url')
                            ->label('Download URL (Original MP4)')
                            ->readOnly(),
                        Forms\Components\Repeater::make('variants')
                            ->label('HLS variants')
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->disabled(),
                                Forms\Components\TextInput::make('url')
                                    ->readOnly(),
                            ])
                            ->columns(2)
                            ->addable(false)
                            ->reorderable(false)
                            ->deletable(false)
                            ->visible(fn (Forms\Get $get): bool => count($get('variants') ?? []) > 0),
                    ]),
                Tables\Actions\EditAction::make()
                    ->fillForm(function (VideoSource $record): array {
                        $data = $record->toArray();
                        $qualityOptions = ['480p', '720p', '1080p', '4K', 'Play 480p', 'hls 480p'];
                        if (! in_array((string) ($data['quality'] ?? ''), $qualityOptions, true)) {
                            $data['quality_other'] = $data['quality'] ?? '';
                            $data['quality'] = '_other';
                        }
                        $formatOptions = ['mp4', 'mkv', 'webm', 'm3u8'];
                        if (! in_array((string) ($data['format'] ?? ''), $formatOptions, true)) {
                            $data['format_other'] = $data['format'] ?? '';
                            $data['format'] = '_other';
                        }
                        return $data;
                    })
                    ->using(function (VideoSource $record, array $data): VideoSource {
                        return $this->createOrUpdateSource($record, $data);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->poll('2s');
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(?int $bytes, int $precision = 2): string
    {
        if ($bytes === null || $bytes <= 0) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function createOrUpdateSource(?VideoSource $record, array $data): VideoSource
    {
        $owner = $this->getOwnerRecord();
        $sourceType = (string) ($data['type'] ?? $record?->type ?? 'url');

        if ($sourceType === 'fetched' && ! empty($data['imported_source_id'])) {
            $importedId = (int) $data['imported_source_id'];
            $existing = VideoSource::where('id', $importedId)
                ->where('sourceable_type', $owner::class)
                ->where('sourceable_id', $owner->id)
                ->first();

            if ($existing) {
                $existing->update([
                    'quality' => (string) ($data['quality'] ?? $existing->quality ?? 'auto'),
                    'format' => (string) ($data['format'] ?? $existing->format ?? 'auto'),
                    'is_primary' => (bool) ($data['is_primary'] ?? $existing->is_primary),
                    'is_active' => (bool) ($data['is_active'] ?? $existing->is_active),
                ]);

                return $existing->fresh();
            }
        }

        if ($sourceType === 'local') {
            $uploadPath = (string) ($data['file_path'] ?? $record?->file_path ?? '');
            if ($uploadPath === '') {
                throw new \RuntimeException('Please upload a local video file first.');
            }

            $isRemotePath = str_starts_with($uploadPath, 'http://') || str_starts_with($uploadPath, 'https://');
            if (! $isRemotePath) {
                $cdnService = app(CdnMediaClientService::class);
                $uploadResult = $cdnService->uploadFromStoragePath(
                    'public',
                    $uploadPath,
                    (string) ($owner->title ?? ('Episode ' . $owner->id)),
                    'episode',
                    'public',
                    (string) ($owner->description ?? '')
                );

                if (! $uploadResult['ok']) {
                    throw new \RuntimeException($uploadResult['error'] ?: 'CDN upload failed.');
                }

                $cdnData = (array) ($uploadResult['data'] ?? []);
                $sourceId = isset($cdnData['source_id']) ? (int) $cdnData['source_id'] : null;
                $sourceInfo = $sourceId ? $cdnService->getSource($sourceId) : null;
                $sourceInfoData = is_array($sourceInfo) ? (array) ($sourceInfo['data'] ?? []) : [];
                $publicUrl = (string) ($sourceInfoData['public_url'] ?? $cdnData['public_url_if_ready'] ?? '');

                if ($publicUrl === '') {
                    throw new \RuntimeException('CDN upload finished but no public URL was returned.');
                }

                $metadata = array_merge((array) ($data['metadata'] ?? []), [
                    'cdn_asset_id' => $cdnData['asset_id'] ?? null,
                    'cdn_source_id' => $sourceId,
                    'cdn_status' => $sourceInfoData['status'] ?? $cdnData['status'] ?? 'ready',
                ]);

                Storage::disk('public')->delete($uploadPath);

                $data['url'] = $publicUrl;
                $data['file_path'] = $publicUrl;
                $data['file_size'] = (int) ($sourceInfoData['file_size_bytes'] ?? $data['file_size'] ?? 0) ?: null;
                $data['format'] = (string) ($data['format'] ?? pathinfo(parse_url($publicUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?? 'mp4');
                $data['metadata'] = $metadata;
            }
        }

        $payload = [
            'type' => $sourceType,
            'url' => $data['url'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'quality' => $data['quality'] ?? null,
            'format' => $data['format'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'metadata' => $data['metadata'] ?? null,
        ];

        if ($record) {
            $record->update($payload);
            return $record->fresh();
        }

        return $this->getOwnerRecord()->videoSources()->create($payload);
    }

    /**
     * Resolve Download, MP4 Playback, HLS Master and variant URLs for a CDN-backed VideoSource.
     *
     * @return array{hls_master_url: string, mp4_playback_url: string, download_url: string, variants: array<int, array{label: string, url: string}>}
     */
    private function getCdnUrlsForSource(VideoSource $record): array
    {
        $owner = $this->getOwnerRecord();
        $siblings = VideoSource::where('sourceable_type', $owner::class)
            ->where('sourceable_id', $owner->id)
            ->get();

        $meta = (array) ($record->metadata ?? []);
        $cdnSourceId = $meta['cdn_source_id'] ?? null;
        $currentPath = $record->file_path ?: $record->url;

        if ($cdnSourceId === null && is_string($currentPath) && $currentPath !== '') {
            $derivation = app(CdnUrlDerivationService::class)->deriveFromCdnUrl($currentPath);
            if ($derivation !== null) {
                $cdnSourceId = $derivation['source_id'];
            }
        }

        $downloadUrl = '';
        $mp4PlaybackUrl = '';
        $hlsMasterUrl = '';
        $variants = [];

        foreach ($siblings as $s) {
            $sMeta = (array) ($s->metadata ?? []);
            $sCdnId = $sMeta['cdn_source_id'] ?? null;
            if ($cdnSourceId !== null && $sCdnId != $cdnSourceId) {
                continue;
            }
            $path = $s->file_path ?: $s->url;
            if (! is_string($path) || $path === '') {
                continue;
            }
            $role = $sMeta['source_role'] ?? null;
            if ($role === 'mp4_play' || str_ends_with(strtolower($path), '_play.mp4')) {
                $mp4PlaybackUrl = $path;
            } elseif ($role === 'hls_master' || str_ends_with(strtolower($path), 'master.m3u8')) {
                $hlsMasterUrl = $path;
            } elseif ($role === 'original' || ($role === null && ! str_ends_with(strtolower($path), '_play.mp4') && ! str_ends_with(strtolower($path), '.m3u8'))) {
                if (str_starts_with($path, 'http') && str_contains($path, '/media/')) {
                    $downloadUrl = $path;
                }
            } elseif ($role === 'hls_variant') {
                $variants[] = ['label' => (string) ($s->quality ?? $sMeta['quality_id'] ?? 'variant'), 'url' => $path];
            }
        }

        if ($downloadUrl === '' && $mp4PlaybackUrl === '' && $hlsMasterUrl === '' && is_string($currentPath) && $currentPath !== '') {
            $derivation = app(CdnUrlDerivationService::class)->deriveFromCdnUrl($currentPath);
            if ($derivation !== null) {
                $downloadUrl = $derivation['download_url'] ?? $downloadUrl;
                $mp4PlaybackUrl = $derivation['play_url'] ?? $mp4PlaybackUrl;
                $hlsMasterUrl = $derivation['hls_master_url'] ?? $hlsMasterUrl;
            }
        }

        if ($downloadUrl === '' && ($record->file_path ?: $record->url)) {
            $p = $record->file_path ?: $record->url;
            if (str_contains((string) $p, '/media/') && ! str_ends_with(strtolower((string) $p), '_play.mp4')) {
                $downloadUrl = (string) $p;
            }
        }

        return [
            'hls_master_url' => $hlsMasterUrl,
            'mp4_playback_url' => $mp4PlaybackUrl,
            'download_url' => $downloadUrl,
            'variants' => $variants,
        ];
    }
}


