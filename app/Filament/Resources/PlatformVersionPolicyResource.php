<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformVersionPolicyResource\Pages;
use App\Models\PlatformVersionPolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformVersionPolicyResource extends Resource
{
    protected static ?string $model = PlatformVersionPolicy::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'App versions';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Platform release')
                ->description('Build numbers drive compatibility. Version names are user-facing labels and are never compared lexically.')
                ->schema([
                    Forms\Components\Select::make('platform')->required()->unique(ignoreRecord: true)->options([
                        'android_mobile' => 'Android mobile',
                        'ios' => 'iOS',
                        'android_tv' => 'Android TV',
                    ])->helperText('Each platform can have only one policy.'),
                    Forms\Components\Select::make('update_type')->required()->live()->options([
                        'optional' => 'Optional above minimum build',
                        'required' => 'Require the latest build after grace period',
                    ])->default('optional'),
                    Forms\Components\TextInput::make('latest_version')->required()->maxLength(32)->helperText('Latest public store version, for example 1.10.0.'),
                    // Use Filament's comparison helper so the sibling state path
                    // is qualified when the form is dehydrated under `data`.
                    Forms\Components\TextInput::make('latest_build')->required()->integer()->minValue(1)->gte('minimum_build')->helperText('Latest Play Store versionCode or App Store build number.'),
                    Forms\Components\TextInput::make('minimum_version')->required()->maxLength(32)->helperText('Oldest user-facing version still supported.'),
                    Forms\Components\TextInput::make('minimum_build')->required()->integer()->minValue(1)->helperText('Clients below this build are always blocked from normal access.'),
                    Forms\Components\TextInput::make('update_url')->url()->maxLength(1024)
                        ->required(fn (Forms\Get $get) => (bool) $get('is_active'))
                        ->helperText('Use the exact Play Store, App Store, or Android TV listing. Required before activation.'),
                    Forms\Components\Toggle::make('prompt_enabled')->default(true)->helperText('Allows an optional prompt when a supported build is older than Latest build.'),
                    Forms\Components\Toggle::make('is_active')->default(true)->live(),
                ])->columns(2),
            Forms\Components\Section::make('Timing and copy')
                ->schema([
                    Forms\Components\DateTimePicker::make('effective_at')->seconds(false)->timezone(config('app.timezone'))->helperText('Before this time the policy is ignored.'),
                    Forms\Components\DateTimePicker::make('grace_period_ends_at')->seconds(false)->timezone(config('app.timezone'))->after('effective_at')->helperText('A latest-build requirement remains optional until this time. Minimum-build enforcement is never bypassed.'),
                    Forms\Components\TextInput::make('title')->maxLength(120),
                    Forms\Components\Textarea::make('message')->maxLength(280)->rows(2),
                    Forms\Components\Textarea::make('release_notes')->rows(5)->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('platform')->badge()->sortable(),
            Tables\Columns\TextColumn::make('latest_version')->label('Latest'),
            Tables\Columns\TextColumn::make('latest_build')->label('Latest build')->numeric(),
            Tables\Columns\TextColumn::make('minimum_version')->label('Minimum'),
            Tables\Columns\TextColumn::make('minimum_build')->label('Minimum build')->numeric(),
            Tables\Columns\TextColumn::make('update_type')->badge()->color(fn (string $state) => $state === 'required' ? 'danger' : 'info'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('effective_at')->dateTime(),
            Tables\Columns\TextColumn::make('revision')->numeric(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()->requiresConfirmation(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformVersionPolicies::route('/'),
            'create' => Pages\CreatePlatformVersionPolicy::route('/create'),
            'edit' => Pages\EditPlatformVersionPolicy::route('/{record}/edit'),
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
