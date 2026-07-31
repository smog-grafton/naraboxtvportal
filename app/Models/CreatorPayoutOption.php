<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatorPayoutOption extends Model
{
    protected $fillable = [
        'key',
        'label',
        'category',
        'automation_type',
        'adapter',
        'supported_countries',
        'supported_currencies',
        'minimum_amount_minor',
        'maximum_amount_minor',
        'fee_minor',
        'is_enabled',
        'is_configured',
        'configuration_status',
        'configuration_notes',
        'sort_order',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'supported_countries' => 'array',
            'supported_currencies' => 'array',
            'is_enabled' => 'boolean',
            'is_configured' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function configurationIsComplete(): bool
    {
        if ($this->automation_type !== 'automatic') {
            return true;
        }

        if ($this->key !== 'iotec') {
            return (bool) $this->is_configured;
        }

        $gateway = PaymentGateway::query()->where('slug', 'iotec')->first();
        $config = $gateway?->config ?? [];

        return filled($config['client_id'] ?? env('IOTEC_CLIENT_ID'))
            && filled($config['client_secret'] ?? env('IOTEC_CLIENT_SECRET'))
            && filled($config['wallet_id'] ?? env('IOTEC_WALLET_ID'));
    }

    public function refreshConfigurationStatus(): void
    {
        $configured = $this->configurationIsComplete();
        $this->forceFill([
            'is_configured' => $configured,
            'configuration_status' => $configured ? 'configured' : 'incomplete',
            'is_enabled' => $configured ? $this->is_enabled : false,
            'configuration_notes' => $configured
                ? null
                : 'Add the provider credentials and wallet identifier before enabling automated payouts.',
        ])->saveQuietly();
    }

    protected static function booted(): void
    {
        static::saving(function (CreatorPayoutOption $option): void {
            if ($option->is_enabled && $option->automation_type === 'automatic' && ! $option->configurationIsComplete()) {
                throw ValidationException::withMessages([
                    'is_enabled' => ['This automated payout option cannot be enabled until its required credentials are configured.'],
                ]);
            }
        });
    }
}
