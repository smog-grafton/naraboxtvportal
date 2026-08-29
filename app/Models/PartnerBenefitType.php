<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerBenefitType extends Model
{
    public const FIRST_MOVIE_FREE = 'FIRST_MOVIE_FREE';
    public const FIRST_PAYMENT_DISCOUNT = 'FIRST_PAYMENT_DISCOUNT';

    protected $fillable = ['code', 'name', 'description', 'globally_enabled', 'default_configuration'];

    protected function casts(): array
    {
        return [
            'globally_enabled' => 'boolean',
            'default_configuration' => 'array',
        ];
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(PartnerBenefit::class, 'benefit_type_id');
    }
}
