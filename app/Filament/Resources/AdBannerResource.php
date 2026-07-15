<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdBannerResource\Pages;
use App\Models\AdBanner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdBannerResource extends Resource
{
    protected static ?string $model = AdBanner::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $navigationLabel = 'Ad Banners';
    protected static ?string $modelLabel = 'Ad Banner';
    protected static ?string $pluralModelLabel = 'Ad Banners';
    protected static ?string $navigationGroup = 'Monetization';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Banner')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('type')
                            ->required()
                            ->options([
                                'image' => 'Image banner',
                                'script' => 'Script/HTML banner',
                            ])
                            ->default('image'),
                        Forms\Components\TextInput::make('target_url')
                            ->url()
                            ->maxLength(1024)
                            ->helperText('Destination URL when the banner is clicked (for image banners).'),
                    ])->columns(2),

                Forms\Components\Section::make('Content')
                    ->schema([
                        Forms\Components\FileUpload::make('image_path')
                            ->label('Banner image')
                            ->image()
                            ->directory('banners')
                            ->maxSize(10240)
                            ->helperText('Upload banner image (used when type = image).'),
                        Forms\Components\Textarea::make('script_content')
                            ->label('Script / HTML')
                            ->rows(6)
                            ->helperText('Raw script/HTML from ad network (used when type = script). Render safely on the frontend.'),
                    ])->columns(2),

                Forms\Components\Section::make('Placement & Targeting')
                    ->schema([
                        Forms\Components\TextInput::make('placement')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Logical placement key, e.g. home_hero, home_sidebar, player_overlay.'),
                        Forms\Components\Select::make('platform')
                            ->label('Platform')
                            ->options([
                                'all' => 'All',
                                'app' => 'App only',
                                'web' => 'Web only',
                            ])
                            ->default('all')
                            ->required(),
                        Forms\Components\TextInput::make('width')
                            ->numeric()
                            ->minValue(0)
                            ->label('Width (px)'),
                        Forms\Components\TextInput::make('height')
                            ->numeric()
                            ->minValue(0)
                            ->label('Height (px)'),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('Active'),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->label('Sort order'),
                        Forms\Components\DateTimePicker::make('active_from')
                            ->label('Active from'),
                        Forms\Components\DateTimePicker::make('active_until')
                            ->label('Active until'),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('placement')
                    ->badge(),
                Tables\Columns\TextColumn::make('platform')
                    ->badge(),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('active_from')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('active_until')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdBanners::route('/'),
            'create' => Pages\CreateAdBanner::route('/create'),
            'edit' => Pages\EditAdBanner::route('/{record}/edit'),
        ];
    }
}

