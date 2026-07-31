<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorPermissionResource\Pages;
use App\Models\CreatorPermission;
use App\Models\User;
use App\Services\CreatorAuditService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CreatorPermissionResource extends Resource
{
    protected static ?string $model = CreatorPermission::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Creator Management';

    protected static ?string $navigationLabel = 'Creator Permissions';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        $presetOptions = [
            'applicant' => 'Applicant — uploads and review submissions',
            'standard' => 'Standard creator — publishing through review',
            'monetized' => 'Monetized creator — pricing and withdrawals',
            'publisher' => 'Trusted publisher — may publish without review',
            'custom' => 'Custom capabilities',
        ];

        return $form->schema([
            Forms\Components\Section::make('Creator access')
                ->description('One permission record controls a creator account. Suspension and revocation override every capability.')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('Creator account')
                        ->options(fn () => User::query()
                            ->where(fn (Builder $query) => $query
                                ->whereHas('creatorApplication')
                                ->orWhereHas('vjProfile')
                                ->orWhereHas('mediaLibraryProfile'))
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (User $user) => [$user->id => "{$user->name} · {$user->email}"]))
                        ->searchable()
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('creator_application_id')
                        ->label('Application')
                        ->relationship('application', 'display_name')
                        ->searchable()
                        ->preload(),
                    Forms\Components\Select::make('permission_preset')
                        ->options($presetOptions)
                        ->default('standard')
                        ->live()
                        ->afterStateUpdated(function (?string $state, Forms\Set $set): void {
                            if ($state && $state !== 'custom') {
                                $set('capabilities', config("creator.permission_presets.{$state}", []));
                            }
                        })
                        ->required(),
                    Forms\Components\TextInput::make('creator_share_bps_override')
                        ->label('Creator share override')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(10000)
                        ->suffix('basis points')
                        ->helperText('Leave blank to use the global creator share. 7000 = 70%.'),
                ])->columns(2),
            Forms\Components\Section::make('Capabilities')
                ->description('Capabilities are grouped by job. High-risk publishing and finance permissions are never granted implicitly.')
                ->schema([
                    Forms\Components\CheckboxList::make('capabilities')
                        ->options(CreatorPermission::capabilityOptions())
                        ->columns(2)
                        ->bulkToggleable()
                        ->required(),
                ]),
            Forms\Components\Section::make('Identity and restrictions')
                ->schema([
                    Forms\Components\Toggle::make('identity_verified')
                        ->helperText('Set through the secure creator application approval flow.'),
                    Forms\Components\DateTimePicker::make('withdrawal_hold_until'),
                    Forms\Components\Toggle::make('is_suspended')->live(),
                    Forms\Components\Toggle::make('is_revoked')->live(),
                    Forms\Components\Textarea::make('restriction_reason')
                        ->required(fn (Forms\Get $get) => (bool) $get('is_suspended') || (bool) $get('is_revoked'))
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Creator')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('permission_preset')->label('Preset')->badge(),
                Tables\Columns\TextColumn::make('capability_count')
                    ->label('Capabilities')
                    ->getStateUsing(fn (CreatorPermission $record) => count($record->normalizedCapabilities()))
                    ->badge(),
                Tables\Columns\IconColumn::make('identity_verified')->label('Verified')->boolean(),
                Tables\Columns\IconColumn::make('publishing_enabled')->label('Direct publish')->boolean(),
                Tables\Columns\IconColumn::make('monetization_enabled')->label('Monetize')->boolean(),
                Tables\Columns\IconColumn::make('withdrawals_enabled')->label('Withdraw')->boolean(),
                Tables\Columns\TextColumn::make('access_status')
                    ->label('Access')
                    ->getStateUsing(fn (CreatorPermission $record) => $record->is_revoked
                        ? 'revoked'
                        : ($record->is_suspended ? 'suspended' : 'active'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('creator_share_bps_override')
                    ->label('Share override')
                    ->formatStateUsing(fn ($state) => $state === null ? 'Global' : number_format($state / 100, 2).'%')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('permission_preset')->options([
                    'applicant' => 'Applicant',
                    'standard' => 'Standard',
                    'monetized' => 'Monetized',
                    'publisher' => 'Trusted publisher',
                    'custom' => 'Custom',
                ]),
                Tables\Filters\TernaryFilter::make('identity_verified')->label('Identity verified'),
                Tables\Filters\TernaryFilter::make('is_suspended')->label('Suspended'),
                Tables\Filters\TernaryFilter::make('is_revoked')->label('Revoked'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function (CreatorPermission $record): void {
                        app(CreatorAuditService::class)->record(
                            'creator.permissions_updated',
                            auth()->user(),
                            $record->user,
                            $record,
                            [],
                            $record->toArray()
                        );
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreatorPermissions::route('/'),
            'create' => Pages\CreateCreatorPermission::route('/create'),
            'edit' => Pages\EditCreatorPermission::route('/{record}/edit'),
        ];
    }
}
