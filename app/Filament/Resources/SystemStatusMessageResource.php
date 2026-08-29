<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemStatusMessageResource\Pages;
use App\Models\SystemStatusMessage;
use App\Services\PlatformOperationsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SystemStatusMessageResource extends Resource
{
    protected static ?string $model = SystemStatusMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Service advisories';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('starter_record')
                ->label('Record type')
                ->content(fn (?SystemStatusMessage $record) => $record?->preset_key
                    ? 'Reusable starter — edit and activate it instead of creating the same advisory again.'
                    : 'Custom service advisory.'),
            Forms\Components\Section::make('User message')
                ->description('Keep the Home message short. Put investigation notes and technical context in Details.')
                ->schema([
                    Forms\Components\Select::make('message_template')
                        ->label('Quick template')
                        ->dehydrated(false)
                        ->live()
                        ->options([
                            'video_sources' => 'Some video sources are unavailable',
                            'network_connectivity' => 'Network connectivity issues',
                            'server_migration' => 'Moving to stronger servers',
                            'high_load' => 'Heavy user traffic',
                            'security_response' => 'Security response in progress',
                            'development_notice' => 'NaraBox TV development-stage notice',
                            'custom' => 'Custom message',
                        ])
                        ->afterStateUpdated(function (?string $state, Forms\Set $set): void {
                            $template = match ($state) {
                                'video_sources' => ['Some video sources are unavailable', 'A few titles may not play while we restore their video sources.', 'degraded', ['playback']],
                                'network_connectivity' => ['Connectivity issues', 'Streaming may start slowly or reconnect while network service stabilises.', 'degraded', ['catalogue', 'playback']],
                                'server_migration' => ['Moving to stronger servers', 'Some features may be briefly limited while NaraBox TV capacity is upgraded.', 'maintenance', ['catalogue', 'playback']],
                                'high_load' => ['Heavy traffic', 'More viewers than usual may cause slower loading while capacity scales up.', 'degraded', ['catalogue', 'playback']],
                                'security_response' => ['Security response in progress', 'Some services are temporarily restricted while we protect NaraBox TV.', 'critical', ['authentication', 'payments']],
                                'development_notice' => ['NaraBox TV is still growing', 'You may notice brief changes while we improve the streaming experience.', 'advisory', ['catalogue', 'playback']],
                                default => null,
                            };
                            if (! $template) {
                                return;
                            }
                            [$title, $message, $severity, $services] = $template;
                            $set('title', $title);
                            $set('message', $message);
                            $set('severity', $severity);
                            $set('affected_services', $services);
                        })
                        ->helperText('Presets are editable and do not activate until you save the record.'),
                    Forms\Components\TextInput::make('title')->required()->maxLength(120),
                    Forms\Components\Select::make('severity')->required()->options([
                        'operational' => 'Operational — all systems normal',
                        'advisory' => 'Advisory — information only',
                        'degraded' => 'Degraded — service works with limitations',
                        'critical' => 'Critical — major feature unavailable',
                        'maintenance' => 'Maintenance — planned or active work',
                    ])->default('advisory'),
                    Forms\Components\Textarea::make('message')->required()->maxLength(280)->rows(2)->columnSpanFull(),
                    Forms\Components\RichEditor::make('details')->columnSpanFull(),
                ])->columns(2),
            Forms\Components\Section::make('Targeting')
                ->schema([
                    Forms\Components\Select::make('platforms')->multiple()->required()->options([
                        'all' => 'All platforms',
                        'android_mobile' => 'Android mobile',
                        'ios' => 'iOS',
                        'android_tv' => 'Android TV',
                        'nextjs_web' => 'Next.js web',
                    ])->default(['all'])->helperText('Choose All platforms by itself, or select an exact combination.'),
                    Forms\Components\Select::make('affected_services')->multiple()->options(
                        collect(PlatformOperationsService::FEATURES)->mapWithKeys(fn ($feature) => [$feature => str($feature)->replace('_', ' ')->title()])->all()
                    ),
                    Forms\Components\DateTimePicker::make('starts_at')->seconds(false)->timezone(config('app.timezone')),
                    Forms\Components\DateTimePicker::make('ends_at')->seconds(false)->timezone(config('app.timezone'))->after('starts_at'),
                    Forms\Components\TextInput::make('priority')->numeric()->minValue(0)->maxValue(65535)->default(0),
                    Forms\Components\TextInput::make('revision')->numeric()->disabled()->dehydrated(false)->helperText('Increases automatically whenever this message changes.'),
                ])->columns(2),
            Forms\Components\Section::make('Presentation')
                ->schema([
                    Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
                    Forms\Components\Toggle::make('is_dismissible')->label('Dismissible')->default(true)->helperText('Critical and maintenance messages are always non-dismissible in clients.'),
                    Forms\Components\Toggle::make('show_on_home')->default(true),
                    Forms\Components\Toggle::make('show_globally')->default(false),
                    Forms\Components\TextInput::make('cta_label')->maxLength(60),
                    Forms\Components\TextInput::make('cta_url')->maxLength(1024)->helperText('HTTPS URL or an app route handled by each client.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('preset_key')->label('Type')->badge()
                ->formatStateUsing(fn (?string $state) => $state ? 'Preset' : 'Custom')
                ->color(fn (?string $state) => $state ? 'info' : 'gray'),
            Tables\Columns\TextColumn::make('severity')->badge()->color(fn (string $state) => match ($state) {
                'operational' => 'success', 'critical' => 'danger', 'degraded' => 'warning', 'maintenance' => 'info', default => 'gray',
            }),
            Tables\Columns\TextColumn::make('platforms')->badge(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('revision')->numeric(),
        ])->defaultSort('priority', 'desc')->filters([
            Tables\Filters\SelectFilter::make('severity')->options([
                'operational' => 'Operational', 'advisory' => 'Advisory', 'degraded' => 'Degraded', 'critical' => 'Critical', 'maintenance' => 'Maintenance',
            ]),
            Tables\Filters\TernaryFilter::make('is_active'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()->requiresConfirmation(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemStatusMessages::route('/'),
            'create' => Pages\CreateSystemStatusMessage::route('/create'),
            'edit' => Pages\EditSystemStatusMessage::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isAdmin() === true;
    }
}
