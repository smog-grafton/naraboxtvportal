<?php

namespace App\Filament\Resources\MovieResource\RelationManagers;

use App\Filament\Resources\Concerns\ManagesContaboVideoSources;
use App\Filament\Resources\Concerns\ManagesTeleObVideoSources;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\VideoSource;
use App\Services\BunnyStreamClientService;
use App\Services\CdnMediaClientService;
use App\Services\CdnPlaybackReadinessService;
use App\Services\CdnUrlDerivationService;
use App\Services\ContaboObjectStorageService;
use App\Services\NbxVideoSourceService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class VideoSourcesRelationManager extends RelationManager
{
    use ManagesContaboVideoSources;
    use ManagesTeleObVideoSources;

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
                        'bunny_stream' => 'Bunny Stream',
                        'contabo_object_storage' => 'Contabo Object Storage',
                        'nbx-engine' => 'NBX Engine',
                        'tele_ob' => 'Tele-OB (Telegram to Contabo)',
                    ])
                    ->required()
                    ->live()
                    ->default('url'),
                Forms\Components\TextInput::make('url')
                    ->label('Video URL')
                    ->url()
                    ->maxLength(2048)
                    ->visible(fn (Forms\Get $get) => in_array($get('type'), ['url', 'youtube', 'vimeo', 'fetched', 'bunny_stream', 'contabo_object_storage', 'nbx-engine', 'tele_ob']))
                    ->required(fn (Forms\Get $get) => in_array($get('type'), ['url', 'youtube', 'vimeo', 'fetched', 'tele_ob'], true)
                        || ($get('type') === 'nbx-engine' && empty($get('file_path'))))
                    ->helperText(fn (Forms\Get $get) => match ($get('type')) {
                        'fetched' => 'Enter the video URL to fetch and download',
                        'bunny_stream' => 'Paste an existing Bunny Stream URL, or leave this empty and upload a file below.',
                        'contabo_object_storage' => 'Paste a remote video URL to fetch into Contabo, a Contabo public URL, or leave empty and upload below.',
                        'nbx-engine' => 'Paste a remote video URL, or leave empty and upload below. NBX Engine will process it on the CDN VPS.',
                        'tele_ob' => 'Paste a Telegram message URL. Telebot downloads it, then Portal streams it into Contabo Object Storage.',
                        default => 'Enter the video URL',
                    }),
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
                                    $fetchUrl = trim((string) ($get('url') ?? ''));
                                    
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
                                                ->body('Please save the movie/TV show first before fetching videos.')
                                                ->send();
                                            return;
                                        }
                                        
                                        $sourceableType = $ownerRecord::class;
                                        $sourceableId = $ownerRecord->id;
                                        $quality = (string) ($get('quality') ?? config('video_sources.defaults.quality', '480p')) ?: '480p';
                                        $format = (string) ($get('format') ?? config('video_sources.defaults.format', 'mp4')) ?: 'mp4';
                                        $sourceType = (string) ($get('type') ?? 'fetched');
                                        if ($sourceType === 'nbx-engine') {
                                            $this->submitNbxFetchFromForm($set, $get, 'movie');
                                            return;
                                        }
                                        $storageTarget = $sourceType === 'contabo_object_storage' ? 'contabo_object_storage' : 'cdn';

                                        $existingSource = VideoSource::where('sourceable_type', $sourceableType)
                                            ->where('sourceable_id', $sourceableId)
                                            ->where('url', $fetchUrl)
                                            ->where('type', $sourceType)
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
                                            'storage_target' => $storageTarget,
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
                                            $set('type', $sourceType);
                                            $set('imported_source_id', $videoSource['id'] ?? null);
                                            
                                            Notification::make()
                                                ->success()
                                                ->title('Video Fetched Successfully')
                                                ->body(($storageTarget === 'contabo_object_storage' ? 'Video has been saved to Contabo Object Storage. File size: ' : 'Video has been downloaded and saved to the database. File size: ') . $this->formatBytes(isset($videoSource['file_size']) ? (int) $videoSource['file_size'] : null))
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
                                            ->body('Please save the movie/TV show first before fetching videos.')
                                            ->send();
                                        return;
                                    }

                                    $sourceableType = $ownerRecord::class;
                                    $sourceableId = $ownerRecord->id;
                                    $quality = (string) ($get('quality') ?? config('video_sources.defaults.quality', '480p')) ?: '480p';
                                    $format = (string) ($get('format') ?? config('video_sources.defaults.format', 'mp4')) ?: 'mp4';
                                    $sourceType = (string) ($get('type') ?? 'fetched');
                                    if ($sourceType === 'nbx-engine') {
                                        $this->submitNbxFetchFromForm($set, $get, 'movie');
                                        return;
                                    }
                                    $storageTarget = $sourceType === 'contabo_object_storage' ? 'contabo_object_storage' : 'cdn';

                                    $fetchController = app(\App\Http\Controllers\Api\VideoFetchController::class);
                                    $request = new \Illuminate\Http\Request([
                                        'url' => $fetchUrl,
                                        'sourceable_type' => $sourceableType,
                                        'sourceable_id' => $sourceableId,
                                        'quality' => $quality,
                                        'format' => $format,
                                        'import_mode' => 'queue',
                                        'storage_target' => $storageTarget,
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
                    ->visible(fn (Forms\Get $get) => in_array($get('type'), ['fetched', 'contabo_object_storage', 'nbx-engine'], true))
                    ->collapsible()
                    ->collapsed(false),
                Forms\Components\FileUpload::make('file_path')
                    ->label('Video File')
                    ->disk('public')
                    ->directory('videos')
                    ->acceptedFileTypes(['video/mp4', 'video/mkv', 'video/webm', 'video/avi'])
                    ->maxSize(52_428_800) // 50GB
                    ->visible(function (Forms\Get $get): bool {
                        $type = $get('type');
                        $path = $this->normalizeContaboUploadState($get('file_path') ?? '');

                        return in_array($type, ['local', 'bunny_stream'], true)
                            || (in_array($type, ['contabo_object_storage', 'nbx-engine'], true) && ! str_starts_with($path, 'http'));
                    })
                    ->required(fn (Forms\Get $get) => $get('type') === 'local'),
                Forms\Components\TextInput::make('file_path')
                    ->label('Stored File URL')
                    ->disabled()
                    ->visible(fn (Forms\Get $get) => in_array($get('type'), ['fetched', 'contabo_object_storage'], true) && $this->normalizeContaboUploadState($get('file_path') ?? '') !== '' && str_starts_with($this->normalizeContaboUploadState($get('file_path') ?? ''), 'http'))
                    ->helperText('This will be automatically filled after fetching or uploading the video'),
                Forms\Components\Select::make('quality')
                    ->label('Quality')
                    ->options([
                        '480p' => '480p',
                        'auto' => 'Auto',
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
                Forms\Components\Section::make('NBX Engine Options')
                    ->schema([
                        Forms\Components\Select::make('nbx_storage_target')
                            ->label('Storage Target')
                            ->options([
                                'contabo' => 'Contabo Object Storage',
                                'public' => 'Local/Public Disk',
                            ])
                            ->default('contabo'),
                        Forms\Components\Toggle::make('nbx_faststart')->label('Faststart MP4')->default(true),
                        Forms\Components\Toggle::make('nbx_compress_enabled')->label('Compress before optimization')->default(false),
                        Forms\Components\Toggle::make('nbx_hls_480p')->label('Generate HLS 480p')->default(true),
                        Forms\Components\Toggle::make('nbx_hls_720p')->label('Generate HLS 720p')->default(false),
                        Forms\Components\Toggle::make('nbx_hls_1080p')
                            ->label('Request HLS 1080p')
                            ->helperText('NBX skips 1080p unless enabled in CDN config and the source is capable.')
                            ->default(false),
                        Forms\Components\Toggle::make('nbx_allow_downloads')->label('Allow MP4 downloads')->default(true),
                        Forms\Components\Toggle::make('nbx_allow_hls_streaming')->label('Allow HLS streaming')->default(true),
                    ])
                    ->columns(2)
                    ->visible(fn (Forms\Get $get): bool => $get('type') === 'nbx-engine')
                    ->collapsible(),
                Forms\Components\TextInput::make('file_size')
                    ->label('File Size (bytes)')
                    ->numeric()
                    ->default(fn () => config('video_sources.defaults.file_size', 380_000_000))
                    ->helperText('Default for manual entry; CDN/fetch may overwrite.'),
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
                        'bunny_stream' => 'success',
                        'contabo_object_storage' => 'primary',
                        'nbx-engine' => 'info',
                        'tele_ob' => 'primary',
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
                Tables\Columns\TextColumn::make('metadata.telegram_status')
                    ->label('Telegram')
                    ->badge()
                    ->default(null)
                    ->placeholder('—')
                    ->color(fn (?string $state): string => match ($state) {
                        'telegram_submitted' => 'warning',
                        'awaiting_admin_fetch' => 'info',
                        'fetching' => 'primary',
                        'attached' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'telegram_submitted' => 'Queued',
                        'awaiting_admin_fetch' => 'Queued',
                        'fetching' => 'Fetching',
                        'attached' => 'Attached',
                        'failed' => 'Failed',
                        default => $state ?? '—',
                    })
                    ->tooltip('Telegram to Object Storage import status'),
                Tables\Columns\TextColumn::make('metadata.creator_ref')
                    ->label('Creator')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                        'bunny_stream' => 'Bunny Stream',
                        'contabo_object_storage' => 'Contabo Object Storage',
                        'nbx-engine' => 'NBX Engine',
                        'tele_ob' => 'Tele-OB',
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
                Tables\Actions\Action::make('sync_nbx')
                    ->label('Sync NBX')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (VideoSource $record): bool => $record->type === 'nbx-engine')
                    ->action(function (VideoSource $record): void {
                        try {
                            app(NbxVideoSourceService::class)->sync($record);
                            Notification::make()->success()->title('NBX source synced')->send();
                        } catch (\Throwable $exception) {
                            Notification::make()->danger()->title('NBX sync failed')->body($exception->getMessage())->send();
                        }
                    }),
                Tables\Actions\Action::make('backfill_nbx_contabo')
                    ->label('Backfill NBX')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->visible(fn (VideoSource $record): bool => in_array($record->type, ['contabo_object_storage', 'url'], true)
                        && app(ContaboObjectStorageService::class)->isContaboPublicUrl((string) ($record->url ?: $record->file_path ?: (is_array($record->metadata) ? ($record->metadata['public_url'] ?? '') : ''))))
                    ->form([
                        Forms\Components\Toggle::make('include_720p')
                            ->label('Also schedule 720p HLS')
                            ->helperText('480p HLS and faststart MP4 are always requested. 1080p stays disabled.'),
                    ])
                    ->action(function (VideoSource $record, array $data): void {
                        try {
                            $contabo = app(ContaboObjectStorageService::class);
                            $url = (string) ($record->url ?: $record->file_path ?: (is_array($record->metadata) ? ($record->metadata['public_url'] ?? '') : ''));
                            $source = app(NbxVideoSourceService::class)->submitObjectStorageBackfill($record->sourceable, [
                                'key' => $contabo->objectKeyFromPublicUrl($url),
                                'url' => $url,
                                'disk' => $contabo->diskName(),
                                'size' => $record->file_size,
                            ], [
                                'include_720p' => (bool) ($data['include_720p'] ?? false),
                                'quality' => '480p',
                                'format' => 'mp4',
                                'is_active' => true,
                            ], 'movie');

                            Notification::make()->success()->title('NBX backfill queued')->body('Video source #' . $source->id . ' is tracking the NBX job.')->send();
                        } catch (\Throwable $exception) {
                            Notification::make()->danger()->title('NBX backfill failed')->body($exception->getMessage())->send();
                        }
                    }),
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
    private function submitNbxFetchFromForm(Forms\Set $set, Forms\Get $get, string $assetType): void
    {
        $fetchUrl = trim((string) ($get('url') ?? ''));
        $ownerRecord = $this->getOwnerRecord();

        if ($fetchUrl === '' || ! $ownerRecord?->id) {
            Notification::make()
                ->warning()
                ->title('NBX URL required')
                ->body('Save the record and enter a remote video URL before queueing NBX Engine.')
                ->send();
            return;
        }

        try {
            $source = app(NbxVideoSourceService::class)->submitRemote($ownerRecord, [
                'url' => $fetchUrl,
                'quality' => (string) ($get('quality') ?? '480p'),
                'format' => (string) ($get('format') ?? 'mp4'),
                'import_mode' => 'queue',
                'nbx_storage_target' => $get('nbx_storage_target') ?? 'contabo',
                'nbx_faststart' => (bool) ($get('nbx_faststart') ?? true),
                'nbx_compress_enabled' => (bool) ($get('nbx_compress_enabled') ?? false),
                'nbx_hls_480p' => (bool) ($get('nbx_hls_480p') ?? true),
                'nbx_hls_720p' => (bool) ($get('nbx_hls_720p') ?? false),
                'nbx_hls_1080p' => (bool) ($get('nbx_hls_1080p') ?? false),
                'nbx_allow_downloads' => (bool) ($get('nbx_allow_downloads') ?? true),
                'nbx_allow_hls_streaming' => (bool) ($get('nbx_allow_hls_streaming') ?? true),
                'is_primary' => (bool) ($get('is_primary') ?? false),
                'is_active' => (bool) ($get('is_active') ?? true),
            ], $assetType);

            $set('imported_source_id', $source->id);

            Notification::make()
                ->success()
                ->title('NBX job queued')
                ->body('NBX Engine accepted the video. Poll with Sync NBX or php artisan nbx:sync-video-sources.')
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('NBX queue failed')
                ->body($exception->getMessage())
                ->send();
        }
    }

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
        if (isset($data['quality']) && $data['quality'] === '_other' && isset($data['quality_other'])) {
            $data['quality'] = trim((string) $data['quality_other']);
        }
        unset($data['quality_other']);
        if (isset($data['format']) && $data['format'] === '_other' && isset($data['format_other'])) {
            $data['format'] = trim((string) $data['format_other']);
        }
        unset($data['format_other']);

        $owner = $this->getOwnerRecord();
        $sourceType = (string) ($data['type'] ?? $record?->type ?? 'url');

        if (in_array($sourceType, ['fetched', 'contabo_object_storage', 'nbx-engine'], true) && ! empty($data['imported_source_id'])) {
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

                return $this->syncPlaybackReadiness($existing->fresh());
            }
        }

        if ($sourceType === 'bunny_stream') {
            return $this->createOrUpdateBunnyStreamSource($record, $data, 'movie');
        }

        if ($sourceType === 'nbx-engine') {
            if ($record && empty($data['url']) && empty($data['file_path'])) {
                return app(NbxVideoSourceService::class)->sync($record);
            }

            return ! empty($data['url'])
                ? app(NbxVideoSourceService::class)->submitRemote($owner, $data, 'movie')
                : app(NbxVideoSourceService::class)->submitUpload($owner, $data, 'movie');
        }

        if ($sourceType === 'contabo_object_storage') {
            return $this->createOrUpdateContaboObjectStorageSource($record, $data, 'movie');
        }

        if ($sourceType === 'tele_ob') {
            return $this->createOrUpdateTeleObVideoSource($record, $data, 'movie');
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
                    (string) ($owner->title ?? ('Movie ' . $owner->id)),
                    'movie',
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
            return $this->syncPlaybackReadiness($record->fresh());
        }

        return $this->syncPlaybackReadiness($this->getOwnerRecord()->videoSources()->create($payload));
    }

    private function createOrUpdateBunnyStreamSource(?VideoSource $record, array $data, string $assetType): VideoSource
    {
        $owner = $this->getOwnerRecord();
        $bunnyService = app(BunnyStreamClientService::class);
        $remoteUrl = trim((string) ($data['url'] ?? ''));
        $uploadPath = (string) ($data['file_path'] ?? $record?->file_path ?? '');
        $isExistingBunnyUrl = $remoteUrl !== '' && $bunnyService->isBunnyStreamUrl($remoteUrl);
        if (! $isExistingBunnyUrl && ! $bunnyService->isConfigured()) {
            throw new \RuntimeException('Bunny Stream is not configured. Set the Bunny Stream env values and clear config cache.');
        }

        $title = trim((string) ($owner->title ?? ucfirst($assetType) . ' ' . $owner->id));
        $result = null;
        $sourceMode = 'direct_url';

        if ($isExistingBunnyUrl) {
            $videoId = (string) ($bunnyService->extractVideoId($remoteUrl) ?? '');
            $video = null;
            if ($videoId !== '' && $bunnyService->isConfigured()) {
                $lookup = $bunnyService->getVideo($videoId);
                $video = ($lookup['ok'] ?? false) && is_array($lookup['data'] ?? null) ? (array) $lookup['data'] : null;
            }
            $result = [
                'ok' => true,
                'data' => [
                    'video_id' => $videoId,
                    'video' => $video,
                    'playback' => $videoId !== '' && $bunnyService->isConfigured() ? $bunnyService->buildPlaybackPayload($videoId, $video) : null,
                ],
            ];
        } elseif ($remoteUrl !== '') {
            $sourceMode = 'url_fetch';
            $result = $bunnyService->fetchVideoFromUrl($remoteUrl, $title . ' [' . now()->format('YmdHis') . ']');
        } elseif ($uploadPath !== '' && ! str_starts_with($uploadPath, 'http://') && ! str_starts_with($uploadPath, 'https://')) {
            $sourceMode = 'upload';
            $result = $bunnyService->uploadFromStoragePath('public', $uploadPath, $title);
        } else {
            throw new \RuntimeException('Add a Bunny Stream URL, a remote URL to import, or a local video file to upload.');
        }

        if (! ($result['ok'] ?? false)) {
            throw new \RuntimeException((string) ($result['error'] ?? 'Bunny Stream request failed.'));
        }

        $bunnyData = (array) ($result['data'] ?? []);
        $playback = is_array($bunnyData['playback'] ?? null) ? (array) $bunnyData['playback'] : null;
        $video = is_array($bunnyData['video'] ?? null) ? (array) $bunnyData['video'] : null;
        $videoId = (string) ($bunnyData['video_id'] ?? ($video['guid'] ?? ''));
        $hlsUrl = (string) (($playback['hls_master_url'] ?? null) ?: '');
        $mp4Url = (string) (($playback['mp4_play_url'] ?? null) ?: ($playback['mp4_url'] ?? ''));
        $originalUrl = (string) (($playback['original_url'] ?? null) ?: '');
        $downloadUrl = (string) (($playback['download_url'] ?? null) ?: ($originalUrl ?: $mp4Url));
        $remotePath = strtolower((string) parse_url($remoteUrl, PHP_URL_PATH));
        if ($hlsUrl === '' && $remoteUrl !== '' && str_ends_with($remotePath, '.m3u8')) {
            $hlsUrl = $remoteUrl;
        }
        if ($mp4Url === '' && $remoteUrl !== '' && str_ends_with($remotePath, '.mp4')) {
            $mp4Url = $remoteUrl;
        }
        $playbackUrl = $hlsUrl ?: $mp4Url ?: ($sourceMode === 'direct_url' ? ($remoteUrl ?: null) : null);

        $metadata = array_merge((array) ($data['metadata'] ?? []), [
            'provider' => 'bunny_stream',
            'fetch_status' => $videoId !== '' ? 'completed' : 'queued',
            'fetch_mode' => $sourceMode,
            'last_message' => $videoId !== ''
                ? 'Bunny Stream source saved. Encoding may continue until Bunny marks the video finished.'
                : 'Bunny Stream accepted the URL fetch, but the video ID was not visible yet.',
            'bunny_stream_video_id' => $videoId !== '' ? $videoId : null,
            'bunny_stream_library_id' => config('services.bunny_stream.library_id'),
            'bunny_stream_status' => $playback['status'] ?? ($video['status'] ?? null),
            'bunny_stream_status_label' => $playback['status_label'] ?? null,
            'bunny_stream_encode_progress' => $playback['encode_progress'] ?? ($video['encodeProgress'] ?? null),
            'bunny_stream_playback' => $playback,
            'bunny_stream_video' => $video,
            'source_url' => $remoteUrl !== '' ? $remoteUrl : null,
            'playback_type' => $hlsUrl !== '' ? 'hls' : 'mp4',
            'hls_master_url' => $hlsUrl !== '' ? $hlsUrl : null,
            'original_url' => $originalUrl !== '' ? $originalUrl : null,
            'mp4_play_url' => $mp4Url !== '' ? $mp4Url : null,
            'mp4_url' => $mp4Url !== '' ? $mp4Url : null,
            'download_url' => $downloadUrl !== '' ? $downloadUrl : null,
            'last_synced_at' => now()->toDateTimeString(),
        ]);

        if ($sourceMode === 'upload' && $uploadPath !== '') {
            Storage::disk('public')->delete($uploadPath);
        }

        $payload = [
            'type' => 'bunny_stream',
            'url' => $playbackUrl,
            'file_path' => $playbackUrl,
            'quality' => (string) ($data['quality'] ?? 'auto'),
            'format' => $hlsUrl !== '' ? 'm3u8' : ((string) ($data['format'] ?? 'mp4') ?: 'mp4'),
            'file_size' => isset($video['storageSize']) && (int) $video['storageSize'] > 0 ? (int) $video['storageSize'] : null,
            'duration_seconds' => isset($video['length']) && (int) $video['length'] > 0 ? (int) $video['length'] : null,
            'is_primary' => (bool) ($data['is_primary'] ?? $record?->is_primary ?? false),
            'is_active' => $playbackUrl !== null && (bool) ($data['is_active'] ?? true),
            'metadata' => $metadata,
        ];

        if ($record) {
            $record->update($payload);
            return $record->fresh();
        }

        return $this->getOwnerRecord()->videoSources()->create($payload);
    }

    private function syncPlaybackReadiness(VideoSource $source): VideoSource
    {
        $owner = $this->getOwnerRecord();

        try {
            app(CdnPlaybackReadinessService::class)->syncForSourceable($owner, true, true);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $source->fresh();
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
        if (($meta['provider'] ?? null) === 'bunny_stream' || ! empty($meta['bunny_stream_video_id'])) {
            $playback = is_array($meta['bunny_stream_playback'] ?? null) ? (array) $meta['bunny_stream_playback'] : [];

            return [
                'hls_master_url' => (string) ($playback['hls_master_url'] ?? $meta['hls_master_url'] ?? ''),
                'mp4_playback_url' => (string) ($playback['mp4_play_url'] ?? $playback['mp4_url'] ?? $meta['mp4_play_url'] ?? ''),
                'download_url' => (string) ($playback['download_url'] ?? $meta['download_url'] ?? ''),
                'variants' => [],
            ];
        }

        if (($meta['provider'] ?? null) === 'nbx_engine') {
            return [
                'hls_master_url' => (string) ($meta['hls_master_url'] ?? ''),
                'mp4_playback_url' => (string) ($meta['mp4_play_url'] ?? $meta['mp4_url'] ?? ''),
                'download_url' => (string) ($meta['download_url'] ?? ''),
                'variants' => array_values(array_filter(array_map(static function ($quality): ?array {
                    return is_array($quality) && isset($quality['url'])
                        ? ['label' => (string) ($quality['label'] ?? $quality['id'] ?? 'variant'), 'url' => (string) $quality['url']]
                        : null;
                }, is_array($meta['qualities'] ?? null) ? $meta['qualities'] : []))),
            ];
        }

        if (in_array(($meta['provider'] ?? null), ['contabo_object_storage', 'tele_ob'], true) || in_array($record->type, ['contabo_object_storage', 'tele_ob'], true)) {
            $url = (string) ($meta['public_url'] ?? $meta['download_url'] ?? $record->file_path ?? $record->url ?? '');

            return [
                'hls_master_url' => strtolower((string) $record->format) === 'm3u8' ? $url : '',
                'mp4_playback_url' => strtolower((string) $record->format) === 'm3u8' ? '' : $url,
                'download_url' => $url,
                'variants' => [],
            ];
        }

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
