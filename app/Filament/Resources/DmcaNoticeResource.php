<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DmcaNoticeResource\Pages;
use App\Models\DmcaNotice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class DmcaNoticeResource extends Resource
{
    protected static ?string $model = DmcaNotice::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationLabel = 'DMCA Notices';
    protected static ?string $modelLabel = 'DMCA Notice';
    protected static ?string $pluralModelLabel = 'DMCA Notices';
    protected static ?string $navigationGroup = 'Compliance';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notice')
                    ->schema([
                        Forms\Components\Select::make('content_type')
                            ->options(['MOVIE' => 'Movie', 'TV_SHOW' => 'TV Show'])
                            ->required(),
                        Forms\Components\TextInput::make('content_id')
                            ->numeric()
                            ->required()
                            ->helperText('Movie/TV show id. (Use the title’s id from admin list.)'),
                        Forms\Components\TextInput::make('reference_number')
                            ->required()
                            ->maxLength(255)
                            ->default(fn () => 'DMCA-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)))
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending_review' => 'Pending review',
                                'validated' => 'Validated',
                                'actioned' => 'Actioned',
                                'rejected' => 'Rejected',
                                'restored' => 'Restored',
                                'closed' => 'Closed',
                            ])
                            ->default('pending_review')
                            ->required(),
                        Forms\Components\TextInput::make('source')
                            ->helperText('email | webform | google | manual')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('affected_url')
                            ->maxLength(1024),
                        Forms\Components\DateTimePicker::make('received_at'),
                        Forms\Components\DateTimePicker::make('reviewed_at'),
                        Forms\Components\TextInput::make('action_taken')
                            ->helperText('e.g. dmca_removed, restored, no_action')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Complainant')
                    ->schema([
                        Forms\Components\TextInput::make('complainant_name')->maxLength(255),
                        Forms\Components\TextInput::make('complainant_email')->email()->maxLength(255),
                        Forms\Components\TextInput::make('represented_rightsholder')->maxLength(255),
                        Forms\Components\Textarea::make('claim_description')->rows(6)->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')->rows(4)->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('content_type')->badge(),
                Tables\Columns\TextColumn::make('content_id')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('received_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('reviewed_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('complainant_email')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDmcaNotices::route('/'),
            'create' => Pages\CreateDmcaNotice::route('/create'),
            'edit' => Pages\EditDmcaNotice::route('/{record}/edit'),
        ];
    }
}

