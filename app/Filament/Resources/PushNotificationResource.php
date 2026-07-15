<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PushNotificationResource\Pages;
use App\Models\Article;
use App\Models\LiveStream;
use App\Models\Movie;
use App\Models\PushNotification;
use App\Models\TVShow;
use App\Models\VJ;
use App\Services\PushNotificationService;
use App\Support\PushDeepLink;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PushNotificationResource extends Resource
{
    protected static ?string $model = PushNotification::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?string $navigationLabel = 'Push Notifications';
    protected static ?string $modelLabel = 'Push Notification';
    protected static ?string $pluralModelLabel = 'Push Notifications';
    protected static ?string $navigationGroup = 'Engagement';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notification')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('body')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('image_url')
                            ->label('Image URL')
                            ->url()
                            ->maxLength(1024),
                        Forms\Components\Select::make('destination_kind')
                            ->label('Destination')
                            ->options([
                                'movie' => 'Movie',
                                'tv_show' => 'TV show',
                                'article' => 'Article',
                                'live' => 'Live stream',
                                'vj' => 'VJ profile',
                                'custom' => 'Custom deep link',
                            ])
                            ->required()
                            ->default('article')
                            ->live()
                            ->dehydrated(false),
                        static::searchableDestinationSelect(
                            'destination_movie_id',
                            'Movie',
                            Movie::class,
                            'title',
                            'movie',
                        ),
                        static::searchableDestinationSelect(
                            'destination_tv_show_id',
                            'TV show',
                            TVShow::class,
                            'title',
                            'tv_show',
                        ),
                        static::searchableDestinationSelect(
                            'destination_article_id',
                            'Article',
                            Article::class,
                            'title',
                            'article',
                        ),
                        static::searchableDestinationSelect(
                            'destination_live_id',
                            'Live stream',
                            LiveStream::class,
                            'title',
                            'live',
                        ),
                        static::searchableDestinationSelect(
                            'destination_vj_id',
                            'VJ profile',
                            VJ::class,
                            'name',
                            'vj',
                        ),
                        Forms\Components\TextInput::make('custom_deep_link')
                            ->label('Custom deep link')
                            ->placeholder('app://news/slug-or-id')
                            ->helperText('Use app://movie/{id}, app://tv-show/{id}, app://news/{slugOrId}, app://live/{id}, or app://vj/{id}.')
                            ->rule('regex:/^(app:\\/\\/|\\/)/i')
                            ->visible(fn (Get $get): bool => $get('destination_kind') === 'custom')
                            ->required(fn (Get $get): bool => $get('destination_kind') === 'custom')
                            ->dehydrated(false),
                        Forms\Components\Placeholder::make('destination_preview')
                            ->label('Resolved deep link')
                            ->content(fn (Get $get): string => static::resolveDeepLinkFromForm($get) ?? 'Pick a destination to generate the deep link.')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Targeting')
                    ->schema([
                        Forms\Components\Select::make('target_platform')
                            ->label('Platform')
                            ->options([
                                'all' => 'All platforms',
                                'android' => 'Android',
                                'ios' => 'iOS',
                                'web' => 'Web',
                            ])
                            ->default('all')
                            ->required(),
                        Forms\Components\Select::make('target_audience')
                            ->label('Audience')
                            ->options([
                                'all' => 'All users',
                                'subscribed' => 'Subscribed users',
                                'free' => 'Free users',
                                'custom' => 'Custom filters (JSON)',
                            ])
                            ->default('all')
                            ->required(),
                        Forms\Components\Textarea::make('filters')
                            ->label('Custom Filters (JSON)')
                            ->rows(3)
                            ->helperText('Optional JSON: {"user_ids":[1,2],"roles":["customer"],"platforms":["ios"]}')
                            ->afterStateHydrated(function (Forms\Components\Textarea $component, $state): void {
                                if (blank($state)) {
                                    $component->state(null);
                                    return;
                                }

                                $component->state(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                            })
                            ->dehydrateStateUsing(fn (?string $state): ?array => blank($state)
                                ? null
                                : json_decode($state, true, 512, JSON_THROW_ON_ERROR))
                            ->nullable()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Delivery')
                    ->schema([
                        Forms\Components\Select::make('provider')
                            ->options([
                                'default' => 'Use default provider',
                                'onesignal' => 'OneSignal',
                                'log' => 'Log only',
                            ])
                            ->default('default')
                            ->required(),
                        Forms\Components\Select::make('notification_type')
                            ->label('Notification type')
                            ->options([
                                'transactional' => 'Transactional',
                                'marketing' => 'Marketing',
                            ])
                            ->default('marketing')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->disabled()
                            ->options([
                                'draft' => 'Draft',
                                'queued' => 'Queued',
                                'sending' => 'Sending',
                                'sent' => 'Sent',
                                'failed' => 'Failed',
                            ])
                            ->default('draft'),
                        Forms\Components\TextInput::make('success_count')
                            ->disabled(),
                        Forms\Components\TextInput::make('failure_count')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('sent_at')
                            ->disabled(),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('notification_type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('target_platform')
                    ->label('Platform')
                    ->badge(),
                Tables\Columns\TextColumn::make('target_audience')
                    ->label('Audience')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'secondary' => 'draft',
                        'warning' => 'queued',
                        'info' => 'sending',
                        'success' => 'sent',
                        'danger' => 'failed',
                    ]),
                Tables\Columns\TextColumn::make('success_count')
                    ->label('Success')
                    ->numeric(),
                Tables\Columns\TextColumn::make('failure_count')
                    ->label('Failure')
                    ->numeric(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('send')
                    ->label('Send now')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PushNotification $record): bool => in_array($record->status, ['draft', 'queued', 'failed'], true))
                    ->action(function (PushNotification $record) {
                        $record->update(['status' => 'queued']);
                        $result = PushNotificationService::send($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Push sent')
                            ->body("Success: {$result['success']}, Failed: {$result['failure']}")
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListPushNotifications::route('/'),
            'create' => Pages\CreatePushNotification::route('/create'),
            'edit' => Pages\EditPushNotification::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mutatePushNotificationData(array $data): array
    {
        $deepLink = PushDeepLink::build(
            $data['destination_kind'] ?? null,
            static::destinationValueFromData($data),
            $data['custom_deep_link'] ?? null,
        );

        if (blank($deepLink)) {
            throw ValidationException::withMessages([
                'destination_kind' => 'Choose a destination or provide a custom deep link.',
            ]);
        }

        $data['deep_link'] = $deepLink;

        unset(
            $data['destination_kind'],
            $data['destination_movie_id'],
            $data['destination_tv_show_id'],
            $data['destination_article_id'],
            $data['destination_live_id'],
            $data['destination_vj_id'],
            $data['custom_deep_link'],
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillDestinationData(array $data): array
    {
        $parsed = PushDeepLink::parse($data['deep_link'] ?? null);
        $data['destination_kind'] = $parsed['kind'];
        $data['custom_deep_link'] = $parsed['custom'];

        foreach ([
            'destination_movie_id',
            'destination_tv_show_id',
            'destination_article_id',
            'destination_live_id',
            'destination_vj_id',
        ] as $field) {
            $data[$field] = null;
        }

        match ($parsed['kind']) {
            'movie' => $data['destination_movie_id'] = $parsed['value'],
            'tv_show' => $data['destination_tv_show_id'] = $parsed['value'],
            'article' => $data['destination_article_id'] = $parsed['value'],
            'live' => $data['destination_live_id'] = $parsed['value'],
            'vj' => $data['destination_vj_id'] = $parsed['value'],
            default => null,
        };

        return $data;
    }

    protected static function searchableDestinationSelect(
        string $field,
        string $label,
        string $modelClass,
        string $titleColumn,
        string $destinationKind,
    ): Forms\Components\Select {
        return Forms\Components\Select::make($field)
            ->label($label)
            ->searchable()
            ->visible(fn (Get $get): bool => $get('destination_kind') === $destinationKind)
            ->required(fn (Get $get): bool => $get('destination_kind') === $destinationKind)
            ->getSearchResultsUsing(fn (string $search): array => $modelClass::query()
                ->where($titleColumn, 'like', '%' . $search . '%')
                ->limit(20)
                ->pluck($titleColumn, 'id')
                ->all())
            ->getOptionLabelUsing(fn ($value): ?string => $value ? $modelClass::query()->find($value)?->{$titleColumn} : null)
            ->dehydrated(false);
    }

    protected static function resolveDeepLinkFromForm(Get $get): ?string
    {
        return PushDeepLink::build(
            $get('destination_kind'),
            static::destinationValueFromData([
                'destination_kind' => $get('destination_kind'),
                'destination_movie_id' => $get('destination_movie_id'),
                'destination_tv_show_id' => $get('destination_tv_show_id'),
                'destination_article_id' => $get('destination_article_id'),
                'destination_live_id' => $get('destination_live_id'),
                'destination_vj_id' => $get('destination_vj_id'),
            ]),
            $get('custom_deep_link'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function destinationValueFromData(array $data): mixed
    {
        return match ($data['destination_kind'] ?? null) {
            'movie' => $data['destination_movie_id'] ?? null,
            'tv_show' => $data['destination_tv_show_id'] ?? null,
            'article' => $data['destination_article_id'] ?? null,
            'live' => $data['destination_live_id'] ?? null,
            'vj' => $data['destination_vj_id'] ?? null,
            default => null,
        };
    }
}
