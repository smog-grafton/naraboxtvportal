<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentGatewayResource\Pages;
use App\Models\PaymentGateway;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PaymentGatewayResource extends Resource
{
    protected static ?string $model = PaymentGateway::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Payment Gateways';
    protected static ?string $modelLabel = 'Payment Gateway';
    protected static ?string $pluralModelLabel = 'Payment Gateways';
    protected static ?string $navigationGroup = 'Payment Management';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, $state) => $set('slug', Str::slug($state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, $state) => $set('code', $state)),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->afterStateHydrated(function (Forms\Set $set, Forms\Get $get, $state) {
                                if (blank($state) && filled($get('slug'))) {
                                    $set('code', $get('slug'));
                                }
                            })
                            ->maxLength(255),
                        Forms\Components\TextInput::make('display_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo')
                            ->disk('public')
                            ->directory('payment-gateways')
                            ->image()
                            ->imageEditor()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->helperText('PNG, JPG or WebP. Max 2MB.'),
                        Forms\Components\Select::make('type')
                            ->options([
                                'AUTOMATIC' => 'Automatic',
                                'MANUAL' => 'Manual',
                            ])
                            ->required()
                            ->default('AUTOMATIC')
                            ->live(),
                        Forms\Components\Textarea::make('description')
                            ->rows(3),
                        Forms\Components\TextInput::make('helper_text')
                            ->maxLength(255)
                            ->placeholder('Short hint shown in checkout'),
                    ])->columns(2),

                Forms\Components\Section::make('Manual Payment Instructions')
                    ->schema([
                        Forms\Components\RichEditor::make('instructions')
                            ->label('Payment Instructions')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                            ])
                            ->visible(fn (Forms\Get $get) => $get('type') === 'MANUAL'),
                        Forms\Components\KeyValue::make('payment_details')
                            ->label('Payment Details')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'MANUAL')
                            ->helperText('For manual gateways, add payment details like phone numbers or bank account info. Use JSON format.'),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'MANUAL'),

                Forms\Components\Section::make('Automatic Gateway Configuration')
                    ->schema([
                        Forms\Components\KeyValue::make('config')
                            ->label('Configuration')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->helperText('API keys, credentials, etc. (will be encrypted)')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && ! in_array($get('slug'), ['flutterwave', 'iotec', 'pawapay'], true)),
                        // Flutterwave-specific fields
                        Forms\Components\TextInput::make('config.public_key')
                            ->label('Public Key')
                            ->placeholder('FLWPUBK-...')
                            ->helperText('Flutterwave public key')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'flutterwave'),
                        Forms\Components\TextInput::make('config.secret_key')
                            ->label('Secret Key')
                            ->type('password')
                            ->placeholder('FLWSECK-...')
                            ->helperText('Flutterwave secret key (hidden for security)')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'flutterwave'),
                        Forms\Components\TextInput::make('config.encryption_key')
                            ->label('Encryption Key')
                            ->type('password')
                            ->placeholder('Encryption key')
                            ->helperText('Flutterwave encryption key')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'flutterwave'),
                        Forms\Components\Select::make('config.env')
                            ->label('Environment')
                            ->options([
                                'live' => 'Live',
                                'test' => 'Test',
                            ])
                            ->default('live')
                            ->helperText('Flutterwave environment mode')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'flutterwave'),
                        Forms\Components\TextInput::make('config.currency')
                            ->label('Currency')
                            ->default('UGX')
                            ->helperText('Default currency for Flutterwave payments')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'flutterwave'),
                        // ioTec-specific fields (client_secret encrypted at rest)
                        Forms\Components\TextInput::make('config.client_id')
                            ->label('Client ID')
                            ->placeholder('pay-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')
                            ->helperText('ioTec Pay client ID')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'iotec'),
                        Forms\Components\TextInput::make('config.client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->placeholder('Leave blank to keep existing')
                            ->helperText('ioTec Pay client secret (stored encrypted). Leave blank when editing to keep current value.')
                            ->dehydrated(fn ($state) => filled($state))
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'iotec'),
                        Forms\Components\TextInput::make('config.grant_type')
                            ->label('Grant Type')
                            ->default('client_credentials')
                            ->helperText('OAuth2 grant type')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'iotec'),
                        Forms\Components\TextInput::make('config.id_base_url')
                            ->label('Identity Base URL')
                            ->placeholder('https://id.iotec.io')
                            ->default('https://id.iotec.io')
                            ->helperText('ioTec identity server URL for token')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'iotec'),
                        Forms\Components\TextInput::make('config.pay_base_url')
                            ->label('Pay API Base URL')
                            ->placeholder('https://pay.iotec.io')
                            ->default('https://pay.iotec.io')
                            ->helperText('ioTec Pay API base URL for collect/status')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'iotec'),
                        Forms\Components\TextInput::make('config.wallet_id')
                            ->label('Wallet ID')
                            ->placeholder('5e83b187-801e-410e-b76e-f491928547e0')
                            ->helperText('ioTec Pay wallet UUID where collections are credited (required for collect). Find it in your ioTec Pay portal.')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'iotec'),
                        // PawaPay-specific fields
                        Forms\Components\Select::make('config.environment')
                            ->label('PawaPay Environment')
                            ->options([
                                'sandbox' => 'Sandbox',
                                'production' => 'Production',
                            ])
                            ->default('sandbox')
                            ->helperText('This is informational in Filament. Runtime env comes from .env (PAWAPAY_ENV).')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'pawapay'),
                        Forms\Components\TextInput::make('config.base_url')
                            ->label('PawaPay Base URL')
                            ->placeholder('https://api.sandbox.pawapay.io')
                            ->default('https://api.sandbox.pawapay.io')
                            ->helperText('Informational only. Runtime value comes from .env (PAWAPAY_BASE_URL).')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'pawapay'),
                        Forms\Components\TextInput::make('config.default_currency')
                            ->label('Default Currency')
                            ->default('UGX')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'pawapay'),
                        Forms\Components\TagsInput::make('config.providers')
                            ->label('Supported Providers')
                            ->default(['MTN_MOMO_UGA', 'AIRTEL_OAPI_UGA'])
                            ->helperText('Provider codes exposed in checkout.')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'pawapay'),
                        Forms\Components\Placeholder::make('pawapay_callback_deposit')
                            ->label('Deposit Callback URL')
                            ->content(fn () => rtrim((string) config('app.url'), '/') . '/api/v1/webhooks/pawapay/deposits')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'pawapay'),
                        Forms\Components\Placeholder::make('pawapay_callback_refund')
                            ->label('Refund Callback URL')
                            ->content(fn () => rtrim((string) config('app.url'), '/') . '/api/v1/webhooks/pawapay/refunds')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'pawapay'),
                        Forms\Components\Placeholder::make('pawapay_token_note')
                            ->label('API Token Source')
                            ->content('Set PAWAPAY_API_TOKEN in .env. Do not store real tokens in database config.')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC' && $get('slug') === 'pawapay'),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('type') === 'AUTOMATIC'),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->size(40),
                Tables\Columns\TextColumn::make('display_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'AUTOMATIC',
                        'warning' => 'MANUAL',
                    ]),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'AUTOMATIC' => 'Automatic',
                        'MANUAL' => 'Manual',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentGateways::route('/'),
            'create' => Pages\CreatePaymentGateway::route('/create'),
            'edit' => Pages\EditPaymentGateway::route('/{record}/edit'),
        ];
    }
}
