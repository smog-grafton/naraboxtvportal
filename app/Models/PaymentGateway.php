<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PaymentGateway extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'code',
        'type',
        'display_name',
        'logo_path',
        'description',
        'helper_text',
        'instructions',
        'payment_details',
        'config',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'payment_details' => 'array',
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function getCodeAttribute($value): string
    {
        return $value ?: $this->slug;
    }

    /**
     * Get config with decrypted client_secret for iotec gateway.
     */
    public function getConfigAttribute($value): array
    {
        $config = is_string($value) ? json_decode($value, true) ?? [] : ($value ?? []);
        if (! is_array($config)) {
            return [];
        }
        if ($this->slug === 'iotec' && ! empty($config['client_secret_encrypted'])) {
            try {
                $config['client_secret'] = Crypt::decryptString($config['client_secret_encrypted']);
            } catch (\Throwable $e) {
                // Leave client_secret unset if decryption fails
            }
            unset($config['client_secret_encrypted']);
        }
        return $config;
    }

    /**
     * Set config with encrypted client_secret for iotec gateway.
     * When client_secret is empty on edit, preserve existing encrypted value.
     */
    public function setConfigAttribute($value): void
    {
        $config = is_array($value) ? $value : (is_string($value) ? json_decode($value, true) ?? [] : []);
        if ($this->slug === 'iotec') {
            if (! empty($config['client_secret'])) {
                $config['client_secret_encrypted'] = Crypt::encryptString($config['client_secret']);
                unset($config['client_secret']);
            } elseif (isset($this->attributes['config'])) {
                $existing = is_string($this->attributes['config']) ? json_decode($this->attributes['config'], true) ?? [] : [];
                if (! empty($existing['client_secret_encrypted'])) {
                    $config['client_secret_encrypted'] = $existing['client_secret_encrypted'];
                }
            }
        }
        $this->attributes['config'] = json_encode($config);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
