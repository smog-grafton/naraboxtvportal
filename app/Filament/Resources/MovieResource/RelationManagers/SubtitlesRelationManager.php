<?php

namespace App\Filament\Resources\MovieResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Subtitle;
use Filament\Notifications\Notification;

class SubtitlesRelationManager extends RelationManager
{
    protected static string $relationship = 'subtitles';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        'upload' => 'Upload Subtitle File',
                        'fetched' => 'Fetch from URL',
                    ])
                    ->required()
                    ->live()
                    ->default('upload'),
                Forms\Components\FileUpload::make('file_path')
                    ->label('Subtitle File')
                    ->directory('subtitles')
                    ->acceptedFileTypes(['.vtt', '.srt', '.ass', '.sub'])
                    ->maxSize(10240) // 10MB
                    ->visible(fn (Forms\Get $get) => $get('type') === 'upload')
                    ->required(fn (Forms\Get $get) => $get('type') === 'upload'),
                Forms\Components\Section::make('Fetch Subtitle')
                    ->description('Enter the subtitle URL above, then click "Fetch Subtitle" to download and save it to the server.')
                    ->schema([
                        Forms\Components\TextInput::make('url')
                            ->label('Subtitle URL')
                            ->url()
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('type') === 'fetched')
                            ->required(fn (Forms\Get $get) => $get('type') === 'fetched')
                            ->helperText('Enter the subtitle file URL to fetch and download'),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('fetch_subtitle')
                                ->label('Fetch Subtitle')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('info')
                                ->size('lg')
                                ->requiresConfirmation()
                                ->modalHeading('Fetch Subtitle from URL')
                                ->modalDescription(fn (Forms\Get $get) => 'This will download the subtitle file from: ' . ($get('url') ?? 'No URL provided'))
                                ->modalSubmitActionLabel('Start Fetching')
                                ->visible(fn (Forms\Get $get) => $get('type') === 'fetched')
                                ->action(function (Forms\Set $set, Forms\Get $get) {
                                    $fetchUrl = $get('url');
                                    
                                    if (empty($fetchUrl)) {
                                        Notification::make()
                                            ->warning()
                                            ->title('URL Required')
                                            ->body('Please enter a subtitle URL first.')
                                            ->send();
                                        return;
                                    }
                                    
                                    try {
                                        $ownerRecord = $this->getOwnerRecord();
                                        
                                        if (!$ownerRecord || !$ownerRecord->id) {
                                            Notification::make()
                                                ->warning()
                                                ->title('Save Required')
                                                ->body('Please save the movie/episode first before fetching subtitles.')
                                                ->send();
                                            return;
                                        }
                                        
                                        $subtitleableType = $ownerRecord::class;
                                        $subtitleableId = $ownerRecord->id;
                                        
                                        Notification::make()
                                            ->info()
                                            ->title('Fetching Subtitle...')
                                            ->body('Downloading subtitle file. Please wait...')
                                            ->persistent()
                                            ->send();
                                        
                                        $fetchController = app(\App\Http\Controllers\Api\SubtitleFetchController::class);
                                        $request = new \Illuminate\Http\Request([
                                            'url' => $fetchUrl,
                                            'subtitleable_type' => $subtitleableType,
                                            'subtitleable_id' => $subtitleableId,
                                            'language' => $get('language') ?? 'en',
                                            'label' => $get('label') ?? 'English',
                                        ]);
                                        
                                        $response = $fetchController->fetch($request);
                                        $responseData = json_decode($response->getContent(), true);
                                        
                                        if ($response->getStatusCode() === 200 && isset($responseData['success']) && $responseData['success']) {
                                            $subtitle = $responseData['subtitle'];
                                            
                                            $set('file_path', $subtitle['file_path']);
                                            $set('language', $subtitle['language']);
                                            $set('label', $subtitle['label']);
                                            $set('format', $subtitle['format']);
                                            $set('type', 'fetched');
                                            
                                            Notification::make()
                                                ->success()
                                                ->title('Subtitle Fetched Successfully')
                                                ->body('Subtitle has been downloaded and saved to the database.')
                                                ->send();
                                        } else {
                                            Notification::make()
                                                ->danger()
                                                ->title('Fetch Failed')
                                                ->body($responseData['message'] ?? 'Failed to fetch subtitle')
                                                ->send();
                                        }
                                    } catch (\Exception $e) {
                                        Notification::make()
                                            ->danger()
                                            ->title('Fetch Error')
                                            ->body('Error: ' . $e->getMessage())
                                            ->send();
                                    }
                                }),
                        ]),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'fetched')
                    ->collapsible()
                    ->collapsed(false),
                Forms\Components\TextInput::make('language')
                    ->label('Language Code')
                    ->default('en')
                    ->maxLength(10)
                    ->helperText('ISO 639-1 language code (e.g., en, es, fr, de)')
                    ->required(),
                Forms\Components\TextInput::make('label')
                    ->label('Display Label')
                    ->maxLength(255)
                    ->helperText('Display name (e.g., "English", "English (CC)", "Spanish")')
                    ->default('English'),
                Forms\Components\Select::make('format')
                    ->label('Format')
                    ->options([
                        'vtt' => 'WebVTT (.vtt)',
                        'srt' => 'SubRip (.srt)',
                        'ass' => 'Advanced SubStation Alpha (.ass)',
                        'sub' => 'MicroDVD (.sub)',
                    ])
                    ->default('vtt')
                    ->required(),
                Forms\Components\Toggle::make('is_default')
                    ->label('Default Subtitle')
                    ->helperText('Use this as the default subtitle track')
                    ->default(false),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('language')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('format')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'vtt' => 'success',
                        'srt' => 'info',
                        'ass' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_default')
                    ->boolean()
                    ->label('Default'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'upload' => 'success',
                        'fetched' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('language')
                    ->options([
                        'en' => 'English',
                        'es' => 'Spanish',
                        'fr' => 'French',
                        'de' => 'German',
                        'it' => 'Italian',
                        'pt' => 'Portuguese',
                        'ru' => 'Russian',
                        'ja' => 'Japanese',
                        'ko' => 'Korean',
                        'zh' => 'Chinese',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
            ->defaultSort('sort_order', 'asc');
    }
}
