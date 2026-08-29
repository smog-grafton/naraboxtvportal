<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerApplicationService
{
    /**
     * Create the pending partner profile for an existing NaraBox account.
     *
     * A partner is still a normal user. This records interest only; approval
     * remains an explicit admin action that changes the existing profile to
     * active.
     */
    public function apply(User $user, array $attributes = []): Partner
    {
        return DB::transaction(function () use ($user, $attributes): Partner {
            $partner = Partner::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($partner) {
                if ($partner->status === 'pending') {
                    $partner->fill(array_filter([
                        'display_name' => $this->displayName($user, $attributes),
                        'bio' => $this->optionalText($attributes['bio'] ?? null),
                    ], static fn ($value) => $value !== null));
                    $partner->save();
                }

                return $partner;
            }

            $displayName = $this->displayName($user, $attributes);
            $slug = $this->uniqueSlug($displayName, $user->id);

            return Partner::create([
                'user_id' => $user->id,
                'display_name' => $displayName,
                'slug' => $slug,
                'referral_code' => $this->uniqueReferralCode(),
                'bio' => $this->optionalText($attributes['bio'] ?? null),
                'status' => 'pending',
                'notes' => 'Partner interest submitted through the NaraBox account flow.',
            ]);
        });
    }

    private function displayName(User $user, array $attributes): string
    {
        $name = trim((string) ($attributes['display_name'] ?? $user->name ?? 'NaraBox Partner'));

        return Str::limit($name !== '' ? $name : 'NaraBox Partner', 255, '');
    }

    private function optionalText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : Str::limit($value, 5000, '');
    }

    private function uniqueSlug(string $displayName, int $userId): string
    {
        $base = Str::slug($displayName) ?: 'partner';
        $base = Str::limit($base, 62, '');
        $slug = $base.'-'.$userId;
        $suffix = 1;

        while (Partner::query()->where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 58, '').'-'.$userId.'-'.(++$suffix);
        }

        return $slug;
    }

    private function uniqueReferralCode(): string
    {
        do {
            $code = 'NBX-'.strtoupper(Str::random(8));
        } while (Partner::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
