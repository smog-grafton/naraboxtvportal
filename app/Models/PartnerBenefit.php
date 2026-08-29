<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerBenefit extends Model
{
    protected $fillable = ['partner_id', 'benefit_type_id', 'enabled', 'starts_at', 'ends_at', 'configuration'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'configuration' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function benefitType(): BelongsTo
    {
        return $this->belongsTo(PartnerBenefitType::class, 'benefit_type_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(PartnerBenefitClaim::class);
    }

    public function scopeCurrentlyActive(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $at ??= now();

        return $query->where('enabled', true)
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $query) use ($at): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }

    public function resolvedConfiguration(): array
    {
        return array_replace($this->benefitType?->default_configuration ?? [], $this->configuration ?? []);
    }
}
