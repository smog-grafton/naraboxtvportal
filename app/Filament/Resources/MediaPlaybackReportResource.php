<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaPlaybackReportResource\Pages;
use App\Filament\Resources\MovieResource;
use App\Filament\Resources\TVShowResource;
use App\Filament\Resources\EpisodeResource;
use App\Models\MediaPlaybackReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MediaPlaybackReportResource extends Resource
{
    protected static ?string $model = MediaPlaybackReport::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Operations';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('media_type')->disabled(),
            Forms\Components\TextInput::make('resolved_content_title')
                ->label('Reported Content')
                ->disabled()
                ->dehydrated(false),
            Forms\Components\TextInput::make('resolved_content_subtitle')
                ->label('Content Type')
                ->disabled()
                ->dehydrated(false),
            Forms\Components\TextInput::make('media_id')->disabled(),
            Forms\Components\TextInput::make('episode_id')->disabled(),
            Forms\Components\TextInput::make('source_id')->disabled(),
            Forms\Components\Select::make('status')
                ->options([
                    'open' => 'Open',
                    'investigating' => 'Investigating',
                    'fixed' => 'Fixed',
                    'ignored' => 'Ignored',
                ])
                ->required(),
            Forms\Components\Textarea::make('admin_notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('media_type')->badge(),
            Tables\Columns\TextColumn::make('resolved_content_title')
                ->label('Reported Content')
                ->searchable(query: function ($query, string $search) {
                    $query
                        ->whereHas('movie', fn ($movieQuery) => $movieQuery->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('tvShow', fn ($showQuery) => $showQuery->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('episode', fn ($episodeQuery) => $episodeQuery->where('title', 'like', "%{$search}%"));
                })
                ->description(fn (MediaPlaybackReport $record): ?string => $record->resolved_content_subtitle)
                ->url(fn (MediaPlaybackReport $record): ?string => static::resolveContentAdminUrl($record))
                ->openUrlInNewTab(false)
                ->color('primary')
                ->sortable(false),
            Tables\Columns\TextColumn::make('media_id')
                ->label('Movie/Show ID')
                ->sortable(),
            Tables\Columns\TextColumn::make('episode_id')
                ->label('Episode ID')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('error_type')->badge(),
            Tables\Columns\IconColumn::make('needs_attention')->boolean(),
            Tables\Columns\IconColumn::make('is_slow')->boolean(),
            Tables\Columns\TextColumn::make('load_time_ms')->numeric(),
            Tables\Columns\TextColumn::make('buffering_count')->numeric(),
            Tables\Columns\TextColumn::make('report_count')->numeric(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')
                ->options([
                    'open' => 'Open',
                    'investigating' => 'Investigating',
                    'fixed' => 'Fixed',
                    'ignored' => 'Ignored',
                ]),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaPlaybackReports::route('/'),
            'edit' => Pages\EditMediaPlaybackReport::route('/{record}/edit'),
        ];
    }

    protected static function resolveContentAdminUrl(MediaPlaybackReport $record): ?string
    {
        if ($record->episode) {
            return EpisodeResource::getUrl('edit', ['record' => $record->episode]);
        }

        if (strtoupper((string) $record->media_type) === 'TV_SHOW' && $record->tvShow) {
            return TVShowResource::getUrl('edit', ['record' => $record->tvShow]);
        }

        if ($record->movie) {
            return MovieResource::getUrl('edit', ['record' => $record->movie]);
        }

        return null;
    }
}
