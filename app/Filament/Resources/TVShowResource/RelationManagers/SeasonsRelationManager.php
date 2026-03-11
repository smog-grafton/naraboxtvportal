<?php

namespace App\Filament\Resources\TVShowResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Season;
use App\Models\Movie;
use App\Models\Episode;

class SeasonsRelationManager extends RelationManager
{
    protected static string $relationship = 'seasons';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('number')
                    ->label('Season Number')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                Forms\Components\TextInput::make('title')
                    ->label('Season Title')
                    ->maxLength(255)
                    ->helperText('Optional: e.g., "Season 1", "The Beginning"'),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Season')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('episodes_count')
                    ->label('Episodes')
                    ->counts('episodes'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Get the TV show
                        $tvShow = $this->getOwnerRecord();
                        
                        // Find or create corresponding movie entry for this TV show
                        $movie = Movie::where('media_type', 'SERIES')
                            ->where('title', $tvShow->title)
                            ->first();
                        
                        // If no movie entry exists, create one
                        if (!$movie) {
                            $movie = Movie::create([
                                'title' => $tvShow->title,
                                'slug' => $tvShow->slug,
                                'description' => $tvShow->description,
                                'thumbnail' => $tvShow->thumbnail,
                                'backdrop' => $tvShow->backdrop,
                                'rating' => $tvShow->rating,
                                'release_date' => $tvShow->release_date,
                                'category_id' => $tvShow->category_id,
                                'vj_id' => $tvShow->vj_id,
                                'media_type' => 'SERIES',
                                'access_type' => $tvShow->access_type,
                                'is_free' => $tvShow->is_free,
                                'is_premium' => $tvShow->is_premium,
                                'price_rent' => $tvShow->price_rent,
                                'price_buy' => $tvShow->price_buy,
                                'is_active' => $tvShow->is_active,
                            ]);
                        }
                        
                        // Set media_id
                        $data['media_id'] = $movie->id;
                        
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view_episodes')
                    ->label('Manage Episodes')
                    ->icon('heroicon-o-film')
                    ->color('success')
                    ->url(fn (Season $record) => \App\Filament\Resources\SeasonResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab()
                    ->tooltip('Open season page to manage episodes and their video sources'),
                Tables\Actions\Action::make('manage_episodes')
                    ->label('Manage Episodes')
                    ->icon('heroicon-o-film')
                    ->color('info')
                    ->modalHeading(fn (Season $record) => 'Manage Episodes - ' . ($record->title ?: "Season {$record->number}"))
                    ->modalWidth('7xl')
                    ->form([
                        Forms\Components\Repeater::make('episodes')
                            ->label('Episodes')
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
                                Forms\Components\TextInput::make('duration')
                                    ->label('Duration')
                                    ->maxLength(50)
                                    ->helperText('e.g., 45m'),
                                Forms\Components\Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2),
                                Forms\Components\TextInput::make('video_url')
                                    ->label('Video URL')
                                    ->url()
                                    ->maxLength(500),
                                Forms\Components\Toggle::make('download_enabled')
                                    ->label('Enable Downloads')
                                    ->default(true),
                            ])
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? "Episode {$state['number']}" ?? null)
                            ->addActionLabel('Add Episode')
                            ->reorderableWithButtons()
                            ->columns(2),
                    ])
                    ->fillForm(function (Season $record) {
                        return [
                            'episodes' => $record->episodes->map(function ($episode) {
                                return [
                                    'id' => $episode->id,
                                    'number' => $episode->number,
                                    'title' => $episode->title,
                                    'thumbnail' => $episode->thumbnail,
                                    'duration' => $episode->duration,
                                    'description' => $episode->description,
                                    'video_url' => $episode->video_url,
                                    'download_enabled' => $episode->download_enabled,
                                ];
                            })->toArray(),
                        ];
                    })
                    ->action(function (Season $record, array $data) {
                        // Delete episodes that are not in the submitted data
                        $submittedEpisodeNumbers = collect($data['episodes'])->pluck('number')->filter();
                        $record->episodes()->whereNotIn('number', $submittedEpisodeNumbers)->delete();

                        // Update or create episodes
                        foreach ($data['episodes'] as $episodeData) {
                            if (empty($episodeData['number']) || empty($episodeData['title'])) {
                                continue;
                            }

                            Episode::updateOrCreate(
                                [
                                    'season_id' => $record->id,
                                    'number' => $episodeData['number'],
                                ],
                                [
                                    'title' => $episodeData['title'],
                                    'thumbnail' => $episodeData['thumbnail'] ?? null,
                                    'duration' => $episodeData['duration'] ?? null,
                                    'description' => $episodeData['description'] ?? null,
                                    'video_url' => $episodeData['video_url'] ?? null,
                                    'download_enabled' => $episodeData['download_enabled'] ?? true,
                                ]
                            );
                        }

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Episodes Updated')
                            ->body('Episodes have been saved successfully.')
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
            ->defaultSort('number');
    }
}
