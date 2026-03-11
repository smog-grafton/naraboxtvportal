<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TelegramImportResource\Pages;
use App\Models\TelegramImport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TelegramImportResource extends Resource
{
    protected static ?string $model = TelegramImport::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Telegram Imports';
    protected static ?string $modelLabel = 'Telegram Import';
    protected static ?string $pluralModelLabel = 'Telegram Imports';
    protected static ?string $navigationGroup = 'Content Management';
    protected static ?int $navigationSort = 18;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Telegram')
                    ->schema([
                        Forms\Components\TextInput::make('telegram_channel')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('telegram_chat_id')->maxLength(64),
                        Forms\Components\TextInput::make('telegram_message_id')->maxLength(64),
                        Forms\Components\TextInput::make('title_guess')->maxLength(255),
                        Forms\Components\TextInput::make('vj_guess')->maxLength(255),
                        Forms\Components\TextInput::make('episode_guess')->maxLength(50),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('CDN')
                    ->schema([
                        Forms\Components\TextInput::make('cdn_asset_id'),
                        Forms\Components\TextInput::make('cdn_source_id')->numeric(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'uploaded' => 'Uploaded',
                                'processing' => 'Processing',
                                'ready' => 'Ready',
                                'failed' => 'Failed',
                            ])
                            ->required(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Raw metadata')
                    ->schema([
                        Forms\Components\Textarea::make('raw_metadata')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->rows(12),
                    ])
                    ->collapsed()
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('telegram_channel')
                    ->label('Channel')
                    ->searchable()
                    ->limit(30)
                    ->sortable(),
                Tables\Columns\TextColumn::make('title_guess')
                    ->label('Title')
                    ->searchable()
                    ->limit(40)
                    ->sortable(),
                Tables\Columns\TextColumn::make('vj_guess')
                    ->label('VJ')
                    ->searchable()
                    ->limit(20)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cdn_asset_id')
                    ->label('CDN Asset')
                    ->copyable()
                    ->limit(12)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cdn_source_id')
                    ->label('CDN Source')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ready' => 'success',
                        'failed' => 'danger',
                        'processing' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'uploaded' => 'Uploaded',
                        'processing' => 'Processing',
                        'ready' => 'Ready',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('create_movie')
                    ->label('Create Movie')
                    ->icon('heroicon-o-plus')
                    ->url(fn (TelegramImport $record): string => \App\Filament\Resources\MovieResource::getUrl('create', [
                        'title' => $record->title_guess,
                        'vj' => $record->vj_guess,
                        'telegram_import_id' => $record->id,
                    ]))
                    ->openUrlInNewTab(false),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTelegramImports::route('/'),
            'view' => Pages\ViewTelegramImport::route('/{record}'),
            'edit' => Pages\EditTelegramImport::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
