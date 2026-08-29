<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\IpActivitiesRelationManager;
use App\Models\ProtectedPayer;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AccountSecurityService;
use App\Services\IdentityNormalizer;
use App\Services\SecurityEventService;
use App\Services\SecurityRuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\Select::make('role_id')
                            ->label('Role')
                            ->relationship('role', 'display_name')
                            ->required()
                            ->disabled(fn (?User $record) => $record?->isAdmin() && User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->where('account_status', 'ACTIVE')->count() <= 1)
                            ->dehydrated()
                            ->preload(),
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('Email Verified At'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Account Security')
                    ->schema([
                        Forms\Components\TextInput::make('account_status')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('risk_level')->disabled()->dehydrated(false),
                        Forms\Components\Textarea::make('security_reason')->disabled()->dehydrated(false),
                        Forms\Components\Textarea::make('security_notes')->disabled()->dehydrated(false),
                        Forms\Components\DateTimePicker::make('status_started_at')->disabled()->dehydrated(false),
                        Forms\Components\DateTimePicker::make('status_expires_at')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('registration_ip')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('last_login_ip')->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('last_payment_ip')->disabled()->dehydrated(false),
                        Forms\Components\Placeholder::make('google_identity')->content(fn (?User $record) => $record?->socialAccounts()->where('provider', 'google')->value('provider_user_id') ?: '—'),
                        Forms\Components\Placeholder::make('payments_10m')->content(fn (?User $record) => $record?->paymentAttempts()->where('occurred_at', '>=', now()->subMinutes(10))->count() ?? 0),
                        Forms\Components\Placeholder::make('payments_hour')->content(fn (?User $record) => $record?->paymentAttempts()->where('occurred_at', '>=', now()->subHour())->count() ?? 0),
                        Forms\Components\Placeholder::make('payments_today')->content(fn (?User $record) => $record?->paymentAttempts()->whereDate('occurred_at', today())->count() ?? 0),
                        Forms\Components\Placeholder::make('pending_transactions')->content(fn (?User $record) => $record?->paymentTransactions()->where('status', 'PENDING')->count() ?? 0),
                        Forms\Components\Placeholder::make('failed_transactions')->content(fn (?User $record) => $record?->paymentTransactions()->where('status', 'FAILED')->count() ?? 0),
                        Forms\Components\Placeholder::make('successful_transactions')->content(fn (?User $record) => $record?->paymentTransactions()->where('status', 'SUCCESS')->count() ?? 0),
                        Forms\Components\Placeholder::make('unique_payers')->content(fn (?User $record) => $record?->paymentAttempts()->whereNotNull('payer_phone')->distinct()->count('payer_phone') ?? 0),
                        Forms\Components\Placeholder::make('security_events')->content(fn (?User $record) => $record?->securityEvents()->count() ?? 0),
                    ])->columns(3)->collapsed(),

                Forms\Components\Section::make('Password')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn ($livewire) => $livewire instanceof \App\Filament\Resources\UserResource\Pages\CreateUser)
                            ->dehydrated(fn ($state) => filled($state))
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->maxLength(255)
                            ->helperText('Leave blank to keep current password when editing.'),
                    ]),

                Forms\Components\Section::make('Subscription')
                    ->schema([
                        Forms\Components\Select::make('plan')
                            ->options(fn () => ['FREE' => 'FREE'] + SubscriptionPlan::where('is_active', true)->pluck('name', 'name')->toArray())
                            ->searchable()
                            ->required()
                            ->default('FREE')
                            ->helperText('Use plans from subscription_plans table (Daily Access, Weekly Access, etc.)'),
                        Forms\Components\Select::make('plan_status')
                            ->options([
                                'ACTIVE' => 'ACTIVE',
                                'EXPIRED' => 'EXPIRED',
                                'NONE' => 'NONE',
                            ])
                            ->required()
                            ->default('NONE'),
                        Forms\Components\DatePicker::make('renewal_date')
                            ->label('Renewal Date'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Avatar')
                    ->schema([
                        Forms\Components\TextInput::make('avatar')
                            ->label('Avatar URL')
                            ->url()
                            ->maxLength(255),
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
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role.display_name')
                    ->label('Role')
                    ->sortable()
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Administrator' => 'danger',
                        'Video Jockey' => 'warning',
                        'Customer' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('account_status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ACTIVE' => 'success', 'PAYMENT_RESTRICTED' => 'warning', 'SUSPENDED', 'BANNED' => 'danger', default => 'gray',
                    })->sortable(),
                Tables\Columns\TextColumn::make('risk_level')
                    ->badge()->color(fn (?string $state): string => match ($state) {
                        'CRITICAL' => 'danger', 'HIGH' => 'warning', 'ELEVATED' => 'info', default => 'gray'
                    }),
                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'FREE' => 'gray',
                        'PRO' => 'info',
                        'ELITE' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('plan_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ACTIVE' => 'success',
                        'EXPIRED' => 'danger',
                        'NONE' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('renewal_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('account_status')->options(array_combine(
                    ['ACTIVE', 'PAYMENT_RESTRICTED', 'SUSPENDED', 'BANNED'],
                    ['Active', 'Payment restricted', 'Suspended', 'Banned']
                )),
                Tables\Filters\SelectFilter::make('risk_level')->options(array_combine(
                    ['NORMAL', 'WATCH', 'ELEVATED', 'HIGH', 'CRITICAL'],
                    ['Normal', 'Watch', 'Elevated', 'High', 'Critical']
                )),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('restrict_payments')
                    ->label('Restrict Payments')->icon('heroicon-o-no-symbol')->color('warning')->requiresConfirmation()
                    ->visible(fn (User $record) => $record->account_status === 'ACTIVE' && ! $record->isAdmin())
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(),
                        Forms\Components\Textarea::make('notes'),
                    ])->action(function (User $record, array $data): void {
                        app(AccountSecurityService::class)->restrictPayments($record, $data['reason'], $data['notes'] ?? null, 'MANUAL', auth()->user());
                        Notification::make()->title('Payments restricted')->warning()->send();
                    }),
                Tables\Actions\Action::make('suspend_user')
                    ->label('Suspend')->icon('heroicon-o-pause-circle')->color('danger')->requiresConfirmation()
                    ->visible(fn (User $record) => ! in_array($record->account_status, ['SUSPENDED', 'BANNED'], true)
                        && static::canSuspendOrBan($record))
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes'),
                        Forms\Components\DateTimePicker::make('expires_at')->required()->minDate(now()),
                    ])->action(function (User $record, array $data): void {
                        app(AccountSecurityService::class)->suspend($record, $data['reason'], $data['notes'] ?? null, $data['expires_at'], auth()->user());
                        Notification::make()->title('User suspended and sessions revoked')->warning()->send();
                    }),
                Tables\Actions\Action::make('ban_user')
                    ->label('Ban')->icon('heroicon-o-shield-exclamation')->color('danger')->requiresConfirmation()
                    ->visible(fn (User $record) => $record->account_status !== 'BANNED'
                        && static::canSuspendOrBan($record))
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes'),
                        Forms\Components\DateTimePicker::make('expires_at')->helperText('Leave blank for a permanent ban.'),
                    ])->action(function (User $record, array $data): void {
                        app(AccountSecurityService::class)->ban($record, $data['reason'], $data['notes'] ?? null, $data['expires_at'] ?? null, auth()->user());
                        Notification::make()->title('User and persistent identities banned')->danger()->send();
                    }),
                Tables\Actions\Action::make('restore_user')
                    ->label('Restore Active')->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()
                    ->visible(fn (User $record) => $record->account_status !== 'ACTIVE')
                    ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes')])
                    ->action(function (User $record, array $data): void {
                        app(AccountSecurityService::class)->activate($record, $data['reason'], $data['notes'] ?? null, auth()->user());
                        Notification::make()->title('Account restored')->success()->send();
                    }),
                Tables\Actions\Action::make('force_logout')
                    ->label('Force Logout')->icon('heroicon-o-arrow-right-start-on-rectangle')->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes')])
                    ->action(function (User $record, array $data): void {
                        app(AccountSecurityService::class)->revokeSessions($record);
                        app(SecurityEventService::class)->record('FORCE_LOGOUT', ['user_id' => $record->id, 'risk_level' => 'HIGH', 'reason' => $data['reason'], 'actor_user_id' => auth()->id(), 'metadata' => ['notes' => $data['notes'] ?? null]]);
                        Notification::make()->title('All sessions and API tokens revoked')->success()->send();
                    }),
                Tables\Actions\Action::make('block_email')
                    ->label('Block Exact Email')->icon('heroicon-o-envelope')->requiresConfirmation()
                    ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes')])
                    ->action(function (User $record, array $data): void {
                        app(SecurityRuleService::class)->storeIdentity('EMAIL', $record->email, 'BLOCK_LOGIN', $data['reason'], ['notes' => $data['notes'] ?? null, 'created_by' => auth()->id(), 'risk_level' => 'CRITICAL']);
                        Notification::make()->title('Exact email blocked persistently')->success()->send();
                    }),
                Tables\Actions\Action::make('block_google')
                    ->label('Block Google Identity')->icon('heroicon-o-identification')->requiresConfirmation()
                    ->visible(fn (User $record) => $record->socialAccounts()->where('provider', 'google')->exists())
                    ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes')])
                    ->action(function (User $record, array $data): void {
                        $sub = $record->socialAccounts()->where('provider', 'google')->value('provider_user_id');
                        app(SecurityRuleService::class)->storeIdentity('GOOGLE_SUB', $sub, 'BLOCK_LOGIN', $data['reason'], ['notes' => $data['notes'] ?? null, 'created_by' => auth()->id(), 'risk_level' => 'CRITICAL']);
                        app(AccountSecurityService::class)->revokeSessions($record);
                        Notification::make()->title('Google identity blocked')->success()->send();
                    }),
                Tables\Actions\Action::make('block_ip')
                    ->label('Block Recent IP')->icon('heroicon-o-globe-alt')->color('warning')->requiresConfirmation()
                    ->visible(fn (User $record) => filled($record->last_payment_ip ?: $record->last_login_ip ?: $record->registration_ip))
                    ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes'), Forms\Components\DateTimePicker::make('expires_at')->required()->default(now()->addHour())])
                    ->action(function (User $record, array $data): void {
                        $ip = $record->last_payment_ip ?: $record->last_login_ip ?: $record->registration_ip;
                        app(SecurityRuleService::class)->storeIdentity('IP', $ip, 'BLOCK_PAYMENT', $data['reason'], ['notes' => $data['notes'] ?? null, 'created_by' => auth()->id(), 'expires_at' => $data['expires_at'], 'risk_level' => 'HIGH']);
                        Notification::make()->title('IP temporarily blocked from payments')->success()->send();
                    }),
                Tables\Actions\Action::make('protect_payer')
                    ->label('Protect Recent Payer')->icon('heroicon-o-phone-x-mark')->color('warning')->requiresConfirmation()
                    ->visible(fn (User $record) => $record->paymentTransactions()->whereNotNull('payer_phone')->exists())
                    ->form([
                        Forms\Components\Select::make('reason')->options(['OWNER_REPORTED_UNAUTHORIZED' => 'Owner reported unauthorized', 'SUSPECTED_ABUSE_TARGET' => 'Suspected abuse target', 'CONFIRMED_ABUSE_TARGET' => 'Confirmed abuse target', 'FRAUD_INVESTIGATION' => 'Fraud investigation', 'ADMIN_BLOCK' => 'Admin block'])->required(),
                        Forms\Components\Textarea::make('notes'),
                    ])->action(function (User $record, array $data): void {
                        $phone = $record->paymentTransactions()->whereNotNull('payer_phone')->latest()->value('payer_phone');
                        ProtectedPayer::updateOrCreate(['normalized_phone' => app(IdentityNormalizer::class)->phone($phone)], ['status' => 'ACTIVE', 'reason' => $data['reason'], 'notes' => $data['notes'] ?? null, 'created_by' => auth()->id()]);
                        Notification::make()->title('Payer protected from future collection requests')->success()->send();
                    }),
            ])
            // Security and financial evidence is intentionally not bulk-deletable.
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            IpActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canSuspendOrBan(User $record): bool
    {
        if ($record->getKey() === auth()->id()) {
            return false;
        }

        return ! $record->isAdmin()
            || User::whereHas('role', fn ($query) => $query->where('name', 'admin'))
                ->where('account_status', 'ACTIVE')
                ->count() > 1;
    }
}
