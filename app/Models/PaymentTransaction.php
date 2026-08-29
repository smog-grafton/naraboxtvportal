<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'payment_gateway_id',
        'gateway_code',
        'type',
        'transactionable_type',
        'transactionable_id',
        'subscription_plan_id',
        'transaction_ref',
        'amount',
        'status',
        'failure_reason',
        'gateway_transaction_id',
        'external_reference',
        'provider_code',
        'payer_phone',
        'payment_ip',
        'device_id',
        'risk_level',
        'security_flags',
        'idempotency_key',
        'access_granted_at',
        'gateway_response',
        'raw_request',
        'raw_response',
        'raw_callback',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'raw_request' => 'array',
            'raw_response' => 'array',
            'raw_callback' => 'array',
            'meta' => 'array',
            'security_flags' => 'array',
            'access_granted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentTransaction $transaction): void {
            if (app()->runningInConsole() || ! app()->bound('request')) {
                return;
            }
            $request = request();
            $transaction->payer_phone ??= $request->attributes->get('security_payer_phone');
            $transaction->payment_ip ??= $request->attributes->get('security_ip_address');
            $transaction->device_id ??= $request->attributes->get('security_device_id');
            $transaction->idempotency_key ??= $request->header('Idempotency-Key')
                ? mb_substr(trim((string) $request->header('Idempotency-Key')), 0, 128)
                : null;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'transaction_id');
    }

    public function partnerEarning(): HasOne
    {
        return $this->hasOne(PartnerEarning::class, 'transaction_id');
    }
}
