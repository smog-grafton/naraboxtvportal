<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceWindowResource\Pages;
use App\Models\MaintenanceWindow;
use App\Services\PlatformOperationsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenanceWindowResource extends Resource
{
    protected static ?string $model = MaintenanceWindow::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Maintenance';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $features = collect(PlatformOperationsService::FEATURES)->mapWithKeys(fn ($feature) => [$feature => str($feature)->replace('_', ' ')->title()])->all();

        return $form->schema([
            Forms\Components\Placeholder::make('starter_record')
                ->label('Record type')
                ->content(fn (?MaintenanceWindow $record) => $record?->preset_key
                    ? 'Reusable starter — edit the message, platforms, schedule, and Active switch whenever needed.'
                    : 'Custom maintenance window.'),
            Forms\Components\Section::make('Maintenance experience')
                ->description('Active full maintenance blocks every non-exempt request on the selected platforms. Partial maintenance blocks only selected features.')
                ->schema([
                    Forms\Components\TextInput::make('title')->required()->maxLength(120),
                    Forms\Components\Select::make('mode')->required()->live()->options([
                        'full' => 'Full platform maintenance',
                        'partial' => 'Partial feature maintenance',
                        'read_only' => 'Read-only mode',
                    ])->default('partial'),
                    Forms\Components\Textarea::make('message')->required()->maxLength(280)->rows(2)->columnSpanFull(),
                    Forms\Components\RichEditor::make('details')->columnSpanFull(),
                    Forms\Components\Textarea::make('reason')->rows(2)->columnSpanFull()->helperText('Internal reason retained in the audit trail; do not put secrets here.'),
                ])->columns(2),
            Forms\Components\Section::make('Isolation and schedule')
                ->schema([
                    Forms\Components\Select::make('platforms')->multiple()->required()->options([
                        'all' => 'All platforms', 'android_mobile' => 'Android mobile', 'ios' => 'iOS',
                        'android_tv' => 'Android TV', 'nextjs_web' => 'Next.js web',
                    ])->default(['all'])->helperText('Review this carefully: All platforms includes the website.'),
                    Forms\Components\Select::make('affected_features')->multiple()->options($features)
                        ->required(fn (Forms\Get $get) => $get('mode') === 'partial')
                        ->visible(fn (Forms\Get $get) => $get('mode') === 'partial'),
                    Forms\Components\Select::make('available_features')->multiple()->options($features)
                        ->helperText('Shown to users as features that still work.'),
                    Forms\Components\DateTimePicker::make('starts_at')->seconds(false)->timezone(config('app.timezone'))->helperText('Leave empty to start immediately when Active is enabled.'),
                    Forms\Components\DateTimePicker::make('ends_at')->seconds(false)->timezone(config('app.timezone'))->after('starts_at')->helperText('Leave empty for indefinite maintenance.'),
                    Forms\Components\TextInput::make('priority')->numeric()->minValue(0)->maxValue(65535)->default(0),
                ])->columns(2),
            Forms\Components\Section::make('Activation')
                ->schema([
                    Forms\Components\Toggle::make('is_active')->label('Active')->default(false)->helperText('The schedule is enforced only while Active is enabled.'),
                    Forms\Components\Toggle::make('allow_read_only')->label('Allow read-only browsing')->default(false)->helperText('GET requests may continue during full maintenance; writes and restricted features remain blocked.'),
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
            Tables\Columns\TextColumn::make('mode')->badge()->color(fn (string $state) => $state === 'full' ? 'danger' : ($state === 'read_only' ? 'warning' : 'info')),
            Tables\Columns\TextColumn::make('platforms')->badge(),
            Tables\Columns\TextColumn::make('affected_features')->badge()->limitList(3),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('starts_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('ends_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('revision')->numeric(),
        ])->defaultSort('priority', 'desc')->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make()->requiresConfirmation(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenanceWindows::route('/'),
            'create' => Pages\CreateMaintenanceWindow::route('/create'),
            'edit' => Pages\EditMaintenanceWindow::route('/{record}/edit'),
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
