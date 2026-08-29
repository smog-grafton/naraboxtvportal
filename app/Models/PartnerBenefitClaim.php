<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerBenefitClaim extends Model
{
    protected $fillable = [
        'partner_id', 'user_id', 'partner_benefit_id', 'movie_id',
        'transaction_id', 'status', 'claimed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(PartnerBenefit::class, 'partner_benefit_id');
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'transaction_id');
    }
}
