<?php

namespace App\Filament\Resources\SeasonResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Episode;

class EpisodesRelationManager extends RelationManager
{
    protected static string $relationship = 'episodes';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('number')
                    ->label('Episode Number')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                Forms\Components\TextInput::make('title')
                    ->label('Episode Title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('thumbnail')
                    ->label('Thumbnail URL')
                    ->url()
                    ->maxLength(500),
                Forms\Components\TextInput::make('video_url')
                    ->label('Video URL (Legacy)')
                    ->url()
                    ->maxLength(500)
                    ->helperText('Note: Use Video Sources tab when editing the episode for managing multiple video sources. This field is kept for backward compatibility.'),
                Forms\Components\TextInput::make('duration')
                    ->label('Duration')
                    ->maxLength(50)
                    ->helperText('e.g., 45m'),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(3),
                Forms\Components\Section::make('Download Settings')
                    ->schema([
                        Forms\Components\Toggle::make('download_enabled')
                            ->label('Enable Downloads')
                            ->default(true)
                            ->helperText('Allow users to download this episode'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Episode')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('duration'),
                Tables\Columns\IconColumn::make('download_enabled')
                    ->label('Downloads')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('download_enabled')
                    ->label('Downloads Enabled'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_video_sources')
                    ->label('Video Sources')
                    ->icon('heroicon-o-video-camera')
                    ->color('info')
                    ->url(fn (Episode $record) => \App\Filament\Resources\EpisodeResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('number');
    }
}
