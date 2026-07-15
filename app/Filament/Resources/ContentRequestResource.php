<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentRequestResource\Pages;
use App\Models\ContentRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContentRequestResource extends Resource
{
    protected static ?string $model = ContentRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';
    protected static ?string $navigationGroup = 'Engagement';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required(),
            Forms\Components\Select::make('type')
                ->options([
                    'movie' => 'Movie',
                    'show' => 'TV Show',
                    'episode' => 'Episode',
                    'other' => 'Other',
                ])
                ->required(),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'reviewing' => 'Reviewing',
                    'added' => 'Added',
                    'rejected' => 'Rejected',
                ])
                ->required(),
            Forms\Components\Textarea::make('message')->columnSpanFull(),
            Forms\Components\Textarea::make('admin_notes')->columnSpanFull(),
            Forms\Components\Toggle::make('notify_on_status_change')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('type')->badge(),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('requested_from')->badge(),
            Tables\Columns\TextColumn::make('email')->searchable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')
                ->options([
                    'pending' => 'Pending',
                    'reviewing' => 'Reviewing',
                    'added' => 'Added',
                    'rejected' => 'Rejected',
                ]),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentRequests::route('/'),
            'create' => Pages\CreateContentRequest::route('/create'),
            'edit' => Pages\EditContentRequest::route('/{record}/edit'),
        ];
    }
}
