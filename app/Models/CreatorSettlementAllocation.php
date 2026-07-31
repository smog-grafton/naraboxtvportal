<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorSettlementAllocation extends Model
{
    protected $fillable = [
        'settlement_id', 'user_id', 'wallet_id', 'content_type', 'content_id',
        'qualified_metric', 'amount_minor', 'eligibility_status',
        'idempotency_key', 'calculation_snapshot',
    ];

    protected function casts(): array
    {
        return ['calculation_snapshot' => 'array'];
    }
}
