<?php

namespace App\Filament\Resources\MovieResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\DownloadSource;

class DownloadSourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'downloadSources';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        'local' => 'Local Upload',
                        'url' => 'External URL',
                        'fetched' => 'Fetched (cURL)',
                    ])
                    ->required()
                    ->live()
                    ->default('url'),
                Forms\Components\TextInput::make('url')
                    ->label('Download URL')
                    ->url()
                    ->maxLength(255)
                    ->visible(fn (Forms\Get $get) => in_array($get('type'), ['url', 'fetched']))
                    ->required(fn (Forms\Get $get) => in_array($get('type'), ['url', 'fetched'])),
                Forms\Components\FileUpload::make('file_path')
                    ->label('Download File')
                    ->directory('downloads')
                    ->acceptedFileTypes(['video/mp4', 'video/mkv', 'video/webm', 'video/avi'])
                    ->maxSize(51200) // 50GB
                    ->visible(fn (Forms\Get $get) => $get('type') === 'local')
                    ->required(fn (Forms\Get $get) => $get('type') === 'local'),
                Forms\Components\Select::make('quality')
                    ->label('Quality')
                    ->options([
                        '480p' => '480p',
                        '720p' => '720p',
                        '1080p' => '1080p',
                        '4K' => '4K',
                        'Play 480p' => 'Play 480p',
                        'hls 480p' => 'hls 480p',
                        '_other' => 'Other (type below)',
                    ])
                    ->searchable()
                    ->required()
                    ->default('480p')
                    ->live(),
                Forms\Components\TextInput::make('quality_other')
                    ->label('Quality (custom)')
                    ->maxLength(50)
                    ->visible(fn (Forms\Get $get) => $get('quality') === '_other')
                    ->dehydrated(false),
                Forms\Components\Select::make('format')
                    ->label('Format')
                    ->options([
                        'mp4' => 'mp4',
                        'mkv' => 'mkv',
                        'webm' => 'webm',
                        'm3u8' => 'm3u8',
                        '_other' => 'Other (type below)',
                    ])
                    ->searchable()
                    ->required()
                    ->default('mp4')
                    ->live(),
                Forms\Components\TextInput::make('format_other')
                    ->label('Format (custom)')
                    ->maxLength(50)
                    ->visible(fn (Forms\Get $get) => $get('format') === '_other')
                    ->dehydrated(false),
                Forms\Components\TextInput::make('label')
                    ->label('Label')
                    ->maxLength(255)
                    ->helperText('e.g., "1080p MP4", "4K MKV" (optional, will be auto-generated if empty)'),
                Forms\Components\TextInput::make('file_size')
                    ->label('File Size (bytes)')
                    ->numeric()
                    ->default(380000000),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'local' => 'success',
                        'fetched' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quality'),
                Tables\Columns\TextColumn::make('format'),
                Tables\Columns\TextColumn::make('label')
                    ->searchable(),
                Tables\Columns\TextColumn::make('file_size')
                    ->formatStateUsing(fn ($state) => $state ? $this->formatBytes($state) : '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
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
                        'fetched' => 'Fetched',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('sort_order', 'asc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['quality']) && $data['quality'] === '_other' && isset($data['quality_other'])) {
                            $data['quality'] = trim((string) $data['quality_other']);
                        }
                        unset($data['quality_other']);
                        if (isset($data['format']) && $data['format'] === '_other' && isset($data['format_other'])) {
                            $data['format'] = trim((string) $data['format_other']);
                        }
                        unset($data['format_other']);
                        return $data;
                    }),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['quality']) && $data['quality'] === '_other' && isset($data['quality_other'])) {
                            $data['quality'] = trim((string) $data['quality_other']);
                        }
                        unset($data['quality_other']);
                        if (isset($data['format']) && $data['format'] === '_other' && isset($data['format_other'])) {
                            $data['format'] = trim((string) $data['format_other']);
                        }
                        unset($data['format_other']);
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->fillForm(function (DownloadSource $record): array {
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
                    ->mutateFormDataUsing(function (array $data): array {
                        if (isset($data['quality']) && $data['quality'] === '_other' && isset($data['quality_other'])) {
                            $data['quality'] = trim((string) $data['quality_other']);
                        }
                        unset($data['quality_other']);
                        if (isset($data['format']) && $data['format'] === '_other' && isset($data['format_other'])) {
                            $data['format'] = trim((string) $data['format_other']);
                        }
                        unset($data['format_other']);
                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
