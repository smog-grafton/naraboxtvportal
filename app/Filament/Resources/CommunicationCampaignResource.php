<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunicationCampaignResource\Pages;
use App\Jobs\SendCampaignJob;
use App\Models\CommunicationCampaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommunicationCampaignResource extends Resource
{
    protected static ?string $model = CommunicationCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationGroup = 'Engagement';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Campaign')
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\Select::make('email_template_id')
                        ->relationship('template', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('channel')
                        ->options(['email' => 'Email', 'sms' => 'SMS'])
                        ->default('email')
                        ->required(),
                    Forms\Components\Select::make('audience_type')
                        ->options([
                            'selected_users' => 'Selected users',
                            'active_subscribers' => 'Active subscribers',
                            'expired_subscribers' => 'Expired subscribers',
                            'new_users' => 'New users',
                            'renters' => 'Renters',
                            'buyers' => 'Buyers',
                            'inactive_watchers' => 'Inactive watchers',
                            'never_subscribed' => 'Never subscribed',
                        ])
                        ->default('selected_users')
                        ->required(),
                    Forms\Components\Toggle::make('send_to_all')->default(false),
                    Forms\Components\Toggle::make('marketing_only')->default(true),
                    Forms\Components\TagsInput::make('recipient_emails')
                        ->separator(',')
                        ->helperText('Optional manual emails.'),
                    Forms\Components\DateTimePicker::make('scheduled_at'),
                    Forms\Components\Textarea::make('last_error')->disabled()->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('template.name')->label('Template'),
                Tables\Columns\TextColumn::make('audience_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('success_count')->numeric(),
                Tables\Columns\TextColumn::make('failure_count')->numeric(),
                Tables\Columns\TextColumn::make('scheduled_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('launch')
                    ->label('Launch')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (CommunicationCampaign $record): void {
                        $record->update([
                            'status' => 'scheduled',
                            'created_by' => auth()->id(),
                        ]);

                        SendCampaignJob::dispatch($record->id);

                        Notification::make()->title('Campaign queued')->success()->send();
                    }),
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
            'index' => Pages\ListCommunicationCampaigns::route('/'),
            'create' => Pages\CreateCommunicationCampaign::route('/create'),
            'edit' => Pages\EditCommunicationCampaign::route('/{record}/edit'),
        ];
    }
}
