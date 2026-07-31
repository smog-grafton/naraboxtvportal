<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreatorSettlement extends Model
{
    protected $fillable = [
        'period', 'settlement_type', 'eligible_revenue_minor', 'deductions_minor',
        'creator_pool_bps', 'creator_pool_minor', 'metric',
        'total_qualified_metric', 'creator_count', 'allocated_minor',
        'discrepancy_minor', 'status', 'calculation_snapshot', 'admin_notes',
        'failure_reason', 'cancelled_at', 'cancelled_by',
        'generated_at', 'approved_at', 'approved_by', 'finalized_at',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'calculation_snapshot' => 'array',
            'generated_at' => 'datetime',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CreatorSettlementAllocation::class, 'settlement_id');
    }

    protected static function booted(): void
    {
        static::updating(function (CreatorSettlement $settlement) {
            if ($settlement->getOriginal('status') === 'finalized') {
                throw new \LogicException('Finalized creator settlements are immutable; post an adjustment instead.');
            }
        });

        static::deleting(function (CreatorSettlement $settlement) {
            if ($settlement->status === 'finalized') {
                throw new \LogicException('Finalized creator settlements cannot be deleted.');
            }
        });
    }
}
