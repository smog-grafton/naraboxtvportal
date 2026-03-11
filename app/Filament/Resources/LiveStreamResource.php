<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LiveStreamResource\Pages;
use App\Filament\Resources\LiveStreamResource\RelationManagers;
use App\Models\LiveStream;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LiveStreamResource extends Resource
{
    protected static ?string $model = LiveStream::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';
    protected static ?string $navigationLabel = 'Live Streams';
    protected static ?string $modelLabel = 'Live Stream';
    protected static ?string $pluralModelLabel = 'Live Streams';
    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('stream_url')
                    ->label('Stream URL')
                    ->url()
                    ->required()
                    ->maxLength(500)
                    ->helperText('YouTube, Vimeo, m3u8, or other streaming URL'),
                Forms\Components\Select::make('platform')
                    ->options([
                        'youtube' => 'YouTube',
                        'vimeo' => 'Vimeo',
                        'm3u8' => 'M3U8 Stream',
                        'other' => 'Other',
                    ])
                    ->required()
                    ->default('other'),
                Forms\Components\FileUpload::make('thumbnail')
                    ->image()
                    ->directory('live-streams')
                    ->visibility('public')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_live')
                    ->label('Is Live')
                    ->default(true),
                Forms\Components\Toggle::make('is_archived')
                    ->label('Is Archived')
                    ->default(false),
                Forms\Components\TextInput::make('viewer_count')
                    ->label('Viewer Count')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('order')
                    ->label('Display Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('platform')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'youtube' => 'danger',
                        'vimeo' => 'success',
                        'm3u8' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_live')
                    ->boolean()
                    ->label('Live')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_archived')
                    ->boolean()
                    ->label('Archived')
                    ->sortable(),
                Tables\Columns\TextColumn::make('viewer_count')
                    ->label('Viewers')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_live')
                    ->label('Live Status')
                    ->placeholder('All streams')
                    ->trueLabel('Live only')
                    ->falseLabel('Not live'),
                Tables\Filters\TernaryFilter::make('is_archived')
                    ->label('Archived')
                    ->placeholder('All streams')
                    ->trueLabel('Archived only')
                    ->falseLabel('Not archived'),
                Tables\Filters\SelectFilter::make('platform')
                    ->options([
                        'youtube' => 'YouTube',
                        'vimeo' => 'Vimeo',
                        'm3u8' => 'M3U8',
                        'other' => 'Other',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLiveStreams::route('/'),
            'create' => Pages\CreateLiveStream::route('/create'),
            'edit' => Pages\EditLiveStream::route('/{record}/edit'),
        ];
    }
}
