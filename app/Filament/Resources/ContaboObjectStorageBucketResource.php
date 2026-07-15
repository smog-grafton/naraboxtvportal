<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContaboObjectStorageBucketResource\Pages;
use App\Models\ContaboObjectStorageBucket;
use App\Models\VideoSource;
use App\Services\ContaboApiClientService;
use App\Services\ContaboObjectStorageService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ContaboObjectStorageBucketResource extends Resource
{
    protected static ?string $model = ContaboObjectStorageBucket::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Contabo Storage';
    protected static ?string $modelLabel = 'Contabo Storage Bucket';
    protected static ?string $pluralModelLabel = 'Contabo Storage Buckets';
    protected static ?int $navigationSort = 80;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Bucket')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bucket')
                            ->required()
                            ->default('nbx')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('endpoint')
                            ->required()
                            ->url()
                            ->default('https://usc1.contabostorage.com')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('public_url')
                            ->url()
                            ->default('https://usc1.contabostorage.com/d052ede4e40a478d92ab1a7ad3f1e435:nbx')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('path_prefix')
                            ->default('videos')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('disk')
                            ->required()
                            ->default('contabo')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contabo API')
                    ->schema([
                        Forms\Components\TextInput::make('object_storage_id')
                            ->label('Object Storage ID')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('s3_tenant_id')
                            ->label('S3 Tenant ID')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('user_id')
                            ->label('Contabo User ID')
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_default')
                            ->label('Default Bucket'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('bucket')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('endpoint')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('path_prefix')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('object_storage_id')
                    ->label('Storage ID')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('metadata.last_api_sync_at')
                    ->label('API Sync')
                    ->dateTime()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('import_remote_inventory')
                    ->label('Sync Contabo API')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (): void {
                        $api = app(ContaboApiClientService::class);
                        $result = $api->listObjectStorages();

                        if (! ($result['ok'] ?? false)) {
                            Notification::make()
                                ->danger()
                                ->title('Contabo API sync failed')
                                ->body((string) ($result['error'] ?? 'Unable to list object storages.'))
                                ->send();

                            return;
                        }

                        $items = is_array($result['data'] ?? null) ? $result['data'] : [];
                        $synced = 0;

                        foreach ($items as $item) {
                            if (! is_array($item)) {
                                continue;
                            }

                            $objectStorageId = (string) ($item['objectStorageId'] ?? $item['id'] ?? '');

                            if ($objectStorageId === '') {
                                continue;
                            }

                            ContaboObjectStorageBucket::updateOrCreate(
                                ['object_storage_id' => $objectStorageId],
                                [
                                    'name' => (string) ($item['displayName'] ?? 'Contabo Object Storage'),
                                    'bucket' => (string) config('services.contabo_object_storage.bucket', 'nbx'),
                                    'endpoint' => rtrim((string) ($item['s3Url'] ?? config('services.contabo_object_storage.endpoint', 'https://usc1.contabostorage.com')), '/'),
                                    'public_url' => (string) config('services.contabo_object_storage.public_url'),
                                    'path_prefix' => (string) config('services.contabo_object_storage.path_prefix', 'videos'),
                                    'disk' => (string) config('services.contabo_object_storage.disk', 'contabo'),
                                    's3_tenant_id' => isset($item['s3TenantId']) ? (string) $item['s3TenantId'] : null,
                                    'is_active' => true,
                                    'metadata' => [
                                        'remote' => $item,
                                        'last_api_sync_at' => now()->toDateTimeString(),
                                    ],
                                ]
                            );

                            $synced++;
                        }

                        Notification::make()
                            ->success()
                            ->title('Contabo API synced')
                            ->body('Synced ' . $synced . ' object storage record(s).')
                            ->send();
                    }),
                Tables\Actions\Action::make('cleanup_local_video_uploads')
                    ->label('Clean Local Video Temps')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete unused local video uploads?')
                    ->modalDescription('This deletes stale Livewire upload temps and unused files under storage/app/public/videos that are older than 30 minutes.')
                    ->action(function (): void {
                        $referenced = VideoSource::query()
                            ->whereNotNull('file_path')
                            ->pluck('file_path')
                            ->map(fn ($path) => ltrim((string) $path, '/'))
                            ->filter(fn (string $path): bool => $path !== '' && ! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://'))
                            ->flip();

                        $deleted = 0;
                        $cutoff = now()->subMinutes(30)->timestamp;

                        $deleteOlderThan = function (string $diskName, string $directory, ?callable $shouldKeep = null) use (&$deleted, $cutoff): void {
                            try {
                                $disk = Storage::disk($diskName);

                                foreach ($disk->allFiles($directory) as $path) {
                                    if ($shouldKeep !== null && $shouldKeep($path)) {
                                        continue;
                                    }

                                    try {
                                        if ($disk->lastModified($path) > $cutoff) {
                                            continue;
                                        }

                                        if ($disk->delete($path)) {
                                            $deleted++;
                                        }
                                    } catch (\Throwable) {
                                        continue;
                                    }
                                }
                            } catch (\Throwable) {
                                //
                            }
                        };

                        $deleteOlderThan('public', 'videos', fn (string $path): bool => isset($referenced[$path]));
                        $deleteOlderThan('local', 'livewire-tmp');
                        $deleteOlderThan('public', 'livewire-tmp');

                        Notification::make()
                            ->success()
                            ->title('Local cleanup finished')
                            ->body('Deleted ' . $deleted . ' unused local video file(s).')
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('test_s3_connection')
                    ->label('Test S3')
                    ->icon('heroicon-o-signal')
                    ->color('success')
                    ->action(function (ContaboObjectStorageBucket $record): void {
                        try {
                            $objects = app(ContaboObjectStorageService::class)
                                ->listObjects((string) ($record->path_prefix ?? ''), 10);

                            $metadata = array_merge((array) ($record->metadata ?? []), [
                                'last_s3_check_at' => now()->toDateTimeString(),
                                'last_s3_sample' => $objects,
                            ]);
                            $record->update(['metadata' => $metadata]);

                            Notification::make()
                                ->success()
                                ->title('S3 connection works')
                                ->body('Found ' . count($objects) . ' object(s) in the configured prefix.')
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->danger()
                                ->title('S3 connection failed')
                                ->body($exception->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('sync_stats')
                    ->label('Stats')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->action(function (ContaboObjectStorageBucket $record): void {
                        $objectStorageId = (string) ($record->object_storage_id ?: config('services.contabo_api.object_storage_id', ''));

                        if ($objectStorageId === '') {
                            Notification::make()
                                ->warning()
                                ->title('Object Storage ID required')
                                ->body('Add the Contabo object storage ID or set CONTABO_API_OBJECT_STORAGE_ID.')
                                ->send();

                            return;
                        }

                        $result = app(ContaboApiClientService::class)->getObjectStorageStats($objectStorageId);

                        if (! ($result['ok'] ?? false)) {
                            Notification::make()
                                ->danger()
                                ->title('Stats sync failed')
                                ->body((string) ($result['error'] ?? 'Unable to read Contabo stats.'))
                                ->send();

                            return;
                        }

                        $metadata = array_merge((array) ($record->metadata ?? []), [
                            'api_stats' => $result['data'] ?? null,
                            'last_api_sync_at' => now()->toDateTimeString(),
                        ]);

                        $record->update(['metadata' => $metadata]);

                        Notification::make()
                            ->success()
                            ->title('Stats synced')
                            ->body('Contabo object storage stats were saved on this bucket record.')
                            ->send();
                    }),
                Tables\Actions\Action::make('check_credentials')
                    ->label('Credentials')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->action(function (ContaboObjectStorageBucket $record): void {
                        $api = app(ContaboApiClientService::class);
                        $result = $api->getS3Credentials($record->object_storage_id ?: null, $record->user_id ?: null);

                        if (! ($result['ok'] ?? false)) {
                            Notification::make()
                                ->danger()
                                ->title('Credential lookup failed')
                                ->body((string) ($result['error'] ?? 'Unable to read Contabo S3 credentials.'))
                                ->send();

                            return;
                        }

                        $credentials = is_array($result['data'] ?? null) ? $result['data'] : [];
                        $metadata = array_merge((array) ($record->metadata ?? []), [
                            'last_credential_check_at' => now()->toDateTimeString(),
                            'resolved_object_storage_id' => $credentials['objectStorageId'] ?? $api->resolveObjectStorageId(),
                            'credential_id' => $credentials['credentialId'] ?? null,
                        ]);
                        $record->update([
                            'object_storage_id' => $record->object_storage_id ?: ($metadata['resolved_object_storage_id'] ?? null),
                            'user_id' => $record->user_id ?: $api->resolveUserId(),
                            'metadata' => $metadata,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Credential lookup works')
                            ->body('Contabo returned S3 credentials. Secrets were not stored or displayed.')
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContaboObjectStorageBuckets::route('/'),
            'create' => Pages\CreateContaboObjectStorageBucket::route('/create'),
            'edit' => Pages\EditContaboObjectStorageBucket::route('/{record}/edit'),
        ];
    }
}
