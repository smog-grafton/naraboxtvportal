<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use App\Services\CommunicationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Email Templates';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Unique identifier: verification_code, welcome, payment_success'),
                        Forms\Components\TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Use {{variable}} for dynamic content'),
                        Forms\Components\TextInput::make('preheader')
                            ->maxLength(255)
                            ->helperText('Short inbox preview text.'),
                        Forms\Components\TextInput::make('preview_text')
                            ->maxLength(255)
                            ->helperText('Internal preview / helper summary.'),
                        Forms\Components\Select::make('template_type')
                            ->options([
                                'transactional' => 'Transactional',
                                'promotional' => 'Promotional',
                            ])
                            ->default('transactional')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('Active'),
                    ]),
                Forms\Components\Section::make('Email Body')
                    ->schema([
                        Forms\Components\RichEditor::make('body')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Use {{variable}} for dynamic content. Available variables will be shown below.'),
                        Forms\Components\Placeholder::make('preview')
                            ->label('Live preview')
                            ->content(fn (?EmailTemplate $record, Forms\Get $get) => strip_tags((string) ($get('body') ?: $record?->body ?: '')))
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Available Variables')
                    ->schema([
                        Forms\Components\KeyValue::make('variables')
                            ->keyLabel('Variable Name')
                            ->valueLabel('Description')
                            ->helperText('Document available variables for this template'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('template_type')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('test_send')
                    ->label('Test send')
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required(),
                    ])
                    ->action(function (EmailTemplate $record, array $data): void {
                        try {
                            $log = app(CommunicationService::class)->deliverTemplatedEmail(
                                to: $data['email'],
                                templateName: $record->name,
                                data: [
                                    'user_name' => 'Narabox Tester',
                                    'email' => $data['email'],
                                    'movie_title' => 'Movie of the Day',
                                    'show_title' => 'Narabox Originals',
                                    'episode_title' => 'Episode 1',
                                    'subscription_plan' => 'Weekly Access',
                                    'amount' => '5,000',
                                    'status' => 'TEST',
                                    'watch_url' => rtrim((string) config('app.url'), '/') . '/',
                                    'unsubscribe_url' => rtrim((string) config('app.url'), '/') . '/api/v1/notifications/preferences/unsubscribe',
                                    'created_at' => now(),
                                    'expiry_date' => now()->addWeek(),
                                    'title' => 'Admin test',
                                    'message' => 'This is a test alert from Narabox TV.',
                                ],
                            );

                            if ($log->status === 'sent') {
                                Notification::make()
                                    ->title('Test email sent successfully')
                                    ->success()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Test email failed')
                                ->body($log->error_message ?: 'SMTP send failed.')
                                ->danger()
                                ->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('Test email failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
