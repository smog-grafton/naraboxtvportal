<?php

namespace App\Filament\Pages;

use App\Models\FinancialSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class FinancialSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Financial Settings';

    protected static ?string $title = 'Creator Financial Settings';

    protected static ?string $navigationGroup = 'Creator Finance';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.financial-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = FinancialSetting::current();
        $this->form->fill(
            $settings
                ? $settings->only([
                    'commission_rate',
                    'creator_hold_days',
                    'min_withdrawal_amount',
                    'auto_payout_enabled',
                    'unverified_creator_earns',
                    'iotec_disbursement_enabled',
                    'pawapay_disbursement_enabled',
                ])
                : []
        );
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Commission & Hold')
                    ->schema([
                        TextInput::make('commission_rate')
                            ->label('Commission Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(30)
                            ->required()
                            ->suffix('%'),
                        TextInput::make('creator_hold_days')
                            ->label('Creator Hold Days')
                            ->numeric()
                            ->minValue(0)
                            ->default(7)
                            ->required()
                            ->helperText('Days before earnings become available for withdrawal'),
                        TextInput::make('min_withdrawal_amount')
                            ->label('Minimum Withdrawal (UGX)')
                            ->numeric()
                            ->minValue(0)
                            ->default(10000)
                            ->required(),
                    ])->columns(3),
                Section::make('Creator Earnings')
                    ->schema([
                        Toggle::make('unverified_creator_earns')
                            ->label('Unverified creators earn share')
                            ->default(false)
                            ->helperText('If disabled, only verified creators receive earnings'),
                    ]),
                Section::make('Payout Gateways')
                    ->schema([
                        Toggle::make('auto_payout_enabled')
                            ->label('Auto payout enabled')
                            ->default(false),
                        Toggle::make('iotec_disbursement_enabled')
                            ->label('ioTec disbursement enabled')
                            ->default(true)
                            ->helperText('Mobile money and bank payouts via ioTec Pay'),
                        Toggle::make('pawapay_disbursement_enabled')
                            ->label('PawaPay disbursement enabled')
                            ->default(false)
                            ->helperText('Enable when PawaPay payout API is configured'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = FinancialSetting::current();

        if ($settings) {
            $settings->update($data);
        } else {
            FinancialSetting::create($data);
        }

        Notification::make()
            ->title('Financial settings saved.')
            ->success()
            ->send();
    }

}
