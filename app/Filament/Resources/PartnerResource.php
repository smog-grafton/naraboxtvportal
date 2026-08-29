<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerResource\Pages;
use App\Models\Partner;
use App\Services\AccountSecurityService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PartnerResource extends Resource
{
    protected static ?string $model = Partner::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'Partners';

    protected static ?string $modelLabel = 'Partner';

    protected static ?string $pluralModelLabel = 'Partners';

    protected static ?string $navigationGroup = 'Partner Program';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Partner identity')
                ->schema([
                    Forms\Components\Select::make('user_id')
                        ->label('NaraBox account')
                        ->relationship('user', 'name')
                        ->searchable(['name', 'email'])
                        ->preload()
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('display_name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('referral_code')->required()->maxLength(64)->unique(ignoreRecord: true),
                    Forms\Components\FileUpload::make('profile_image')
                        ->label('Profile photo')
                        ->disk('public')
                        ->directory('partners/profile-images')
                        ->visibility('public')
                        ->image()
                        ->imageEditor()
                        ->imageEditorAspectRatios(['1:1'])
                        ->maxSize(5120)
                        ->helperText('Used in referrer search, public invitations, and social sharing. Upload a square image.'),
                    Forms\Components\Textarea::make('bio')->columnSpanFull(),
                    Forms\Components\KeyValue::make('social_links')->columnSpanFull(),
                ])->columns(2),
            Forms\Components\Section::make('Agreement and earnings')
                ->schema([
                    Forms\Components\Select::make('status')->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ])->required()->default('pending'),
                    Forms\Components\TextInput::make('default_commission_bps')
                        ->label('Default commission (%)')
                        ->numeric()->minValue(0)->maxValue(100)->step(0.01)->required()->default(2000)
                        ->afterStateHydrated(fn ($component, $state) => $component->state(((int) $state) / 100))
                        ->dehydrateStateUsing(fn ($state): int => (int) round(((float) $state) * 100))
                        ->helperText('Enter the human percentage. For example, enter 20 for a 20% commission.'),
                    Forms\Components\TextInput::make('attribution_window_days')->numeric()->minValue(1)->required()->default(30),
                    Forms\Components\TextInput::make('commission_duration_days')->numeric()->minValue(1)->required()->default(180),
                    Forms\Components\TextInput::make('payout_hold_days')->numeric()->minValue(0)->required()->default(7),
                    Forms\Components\TextInput::make('minimum_payout_minor')->label('Minimum payout (UGX)')->numeric()->minValue(0)->required()->default(10000),
                    Forms\Components\DatePicker::make('agreement_start_at')
                        ->timezone(config('app.partner_timezone', 'Africa/Kampala'))
                        ->helperText('Inclusive Uganda calendar date. The partner becomes active from the start of this date.'),
                    Forms\Components\DatePicker::make('agreement_end_at')
                        ->timezone(config('app.partner_timezone', 'Africa/Kampala'))
                        ->helperText('Inclusive Uganda calendar date. The partner remains active through the end of this date.'),
                    Forms\Components\KeyValue::make('rate_overrides')
                        ->label('Transaction-type rate overrides (%)')
                        ->afterStateHydrated(function ($component, $state): void {
                            $component->state(collect($state ?? [])->map(
                                fn ($value) => is_numeric($value) ? ((float) $value) / 100 : $value
                            )->all());
                        })
                        ->dehydrateStateUsing(fn ($state): array => collect($state ?? [])->map(
                            fn ($value) => is_numeric($value) ? (int) round(((float) $value) * 100) : $value
                        )->all())
                        ->helperText('Example: BUY = 15, RENT = 20, SUBSCRIPTION = 10.'),
                    Forms\Components\Textarea::make('notes')->label('Internal contract notes')->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.email')->label('Account')->searchable(),
                Tables\Columns\TextColumn::make('user.account_status')->label('User security')->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'ACTIVE' => 'success', 'PAYMENT_RESTRICTED' => 'warning', 'SUSPENDED', 'BANNED' => 'danger', default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('user.risk_level')->label('Risk')->badge(),
                Tables\Columns\TextColumn::make('referral_code')->copyable()->searchable(),
                Tables\Columns\TextColumn::make('default_commission_bps')->label('Rate')->formatStateUsing(fn ($state) => number_format(((int) $state) / 100, 2).'%'),
                Tables\Columns\TextColumn::make('attributions_count')->counts('attributions')->label('Referred users')->sortable(),
                Tables\Columns\TextColumn::make('lifetime_earnings')
                    ->label('Lifetime earnings')
                    ->getStateUsing(fn (Partner $record): string => static::money(static::canonicalEarnings($record))),
                Tables\Columns\TextColumn::make('available_balance')
                    ->label('Available')
                    ->getStateUsing(fn (Partner $record): string => static::money((int) app(\App\Services\PartnerWalletService::class)->balances($record->user)['available_minor'])),
                Tables\Columns\TextColumn::make('pending_balance')
                    ->label('Pending')
                    ->getStateUsing(fn (Partner $record): string => static::money((int) app(\App\Services\PartnerWalletService::class)->balances($record->user)['pending_minor'])),
                Tables\Columns\TextColumn::make('total_paid')
                    ->label('Total paid')
                    ->getStateUsing(fn (Partner $record): string => static::money((int) app(\App\Services\PartnerWalletService::class)->balances($record->user)['paid_minor'])),
                Tables\Columns\TextColumn::make('last_payout_status')
                    ->label('Last payout')
                    ->getStateUsing(fn (Partner $record): string => $record->withdrawals()->latest('requested_at')->value('status') ?: '—')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'active' => 'success',
                    'suspended' => 'danger',
                    'pending' => 'warning',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('activate')
                    ->label('Activate')->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()
                    ->visible(fn (Partner $record) => $record->status !== 'active')
                    ->action(function (Partner $record): void {
                        $record->update(['status' => 'active', 'approved_at' => now(), 'approved_by' => auth()->id()]);
                        Notification::make()->title('Partner activated')->success()->send();
                    }),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')->icon('heroicon-o-pause-circle')->color('danger')->requiresConfirmation()
                    ->visible(fn (Partner $record) => $record->status === 'active')
                    ->action(function (Partner $record): void {
                        $record->update(['status' => 'suspended', 'suspended_at' => now(), 'suspended_by' => auth()->id()]);
                        Notification::make()->title('Partner suspended')->warning()->send();
                    }),
                Tables\Actions\Action::make('restrict_user_payments')
                    ->label('Restrict User Payments')->icon('heroicon-o-no-symbol')->color('warning')->requiresConfirmation()
                    ->visible(fn (Partner $record) => $record->user?->account_status === 'ACTIVE' && ! $record->user?->isAdmin())
                    ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes')])
                    ->action(function (Partner $record, array $data): void {
                        app(AccountSecurityService::class)->restrictPayments($record->user, $data['reason'], $data['notes'] ?? null, 'MANUAL', auth()->user());
                        Notification::make()->title('Linked user payment-restricted')->warning()->send();
                    }),
                Tables\Actions\Action::make('suspend_user')
                    ->label('Suspend User')->icon('heroicon-o-pause-circle')->color('danger')->requiresConfirmation()
                    ->visible(fn (Partner $record) => $record->user
                        && UserResource::canSuspendOrBan($record->user)
                        && ! in_array($record->user->account_status, ['SUSPENDED', 'BANNED'], true))
                    ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes'), Forms\Components\DateTimePicker::make('expires_at')->required()->minDate(now())])
                    ->action(function (Partner $record, array $data): void {
                        app(AccountSecurityService::class)->suspend($record->user, $data['reason'], $data['notes'] ?? null, $data['expires_at'], auth()->user());
                        Notification::make()->title('Linked user suspended')->warning()->send();
                    }),
                Tables\Actions\Action::make('ban_user')
                    ->label('Ban User')->icon('heroicon-o-shield-exclamation')->color('danger')->requiresConfirmation()
                    ->visible(fn (Partner $record) => $record->user
                        && UserResource::canSuspendOrBan($record->user)
                        && $record->user->account_status !== 'BANNED')
                    ->form([Forms\Components\Textarea::make('reason')->required(), Forms\Components\Textarea::make('notes')])
                    ->action(function (Partner $record, array $data): void {
                        app(AccountSecurityService::class)->ban($record->user, $data['reason'], $data['notes'] ?? null, null, auth()->user());
                        Notification::make()->title('Linked user banned')->danger()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartners::route('/'),
            'create' => Pages\CreatePartner::route('/create'),
            'edit' => Pages\EditPartner::route('/{record}/edit'),
        ];
    }

    private static function canonicalEarnings(Partner $partner): int
    {
        return (int) $partner->earnings()
            ->whereNotIn('status', ['reversed'])
            ->get(['partner_amount_minor', 'partner_amount'])
            ->sum(fn ($earning): int => (int) $earning->partner_amount_minor !== 0 || (float) $earning->partner_amount == 0.0
                ? (int) $earning->partner_amount_minor
                : (int) round((float) $earning->partner_amount));
    }

    private static function money(int $amount): string
    {
        return 'UGX '.number_format($amount, 0);
    }
}
