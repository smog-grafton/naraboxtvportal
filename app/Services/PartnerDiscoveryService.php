<?php

namespace App\Services;

use App\Models\Partner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PartnerDiscoveryService
{
    /**
     * Return active referrers for the registration combobox. An empty query is
     * ranked only by recent acquisition performance so old lifetime totals do
     * not permanently control the suggestions.
     *
     * @return Collection<int, Partner>
     */
    public function discover(?string $search = null, int $limit = 3): Collection
    {
        $search = trim((string) $search);
        $limit = max(1, min(20, $limit));
        $query = $this->activeQuery()
            ->leftJoin('users', 'users.id', '=', 'partners.user_id')
            ->select('partners.*');

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $contains = '%'.$this->escapeLike($needle).'%';
            $prefix = $this->escapeLike($needle).'%';
            $slug = (string) str($search)->slug();

            $query->where(function (Builder $query) use ($contains): void {
                $query->whereRaw("LOWER(partners.display_name) LIKE ? ESCAPE '\\\\'", [$contains])
                    ->orWhereRaw("LOWER(partners.referral_code) LIKE ? ESCAPE '\\\\'", [$contains])
                    ->orWhereRaw("LOWER(partners.slug) LIKE ? ESCAPE '\\\\'", [$contains])
                    ->orWhereRaw("LOWER(users.name) LIKE ? ESCAPE '\\\\'", [$contains]);
            })->orderByRaw(
                'CASE
                    WHEN LOWER(partners.display_name) = ? THEN 0
                    WHEN LOWER(partners.referral_code) = ? THEN 1
                    WHEN LOWER(partners.slug) = ? THEN 2
                    WHEN LOWER(partners.display_name) LIKE ? ESCAPE \'\\\\\' THEN 3
                    WHEN LOWER(partners.referral_code) LIKE ? ESCAPE \'\\\\\' THEN 4
                    WHEN LOWER(partners.slug) LIKE ? ESCAPE \'\\\\\' THEN 5
                    WHEN LOWER(users.name) LIKE ? ESCAPE \'\\\\\' THEN 6
                    ELSE 7
                END',
                [$needle, $needle, $slug, $prefix, $prefix, $prefix, $prefix]
            )->orderBy('partners.display_name');
        } else {
            $registrationsFrom = now()->subDays(30);
            $visitsFrom = now()->subDays(14);
            $paymentsFrom = now()->subDays(30);

            $query->withCount([
                'attributions as recent_registrations_count' => fn (Builder $query) => $query
                    ->where('attributed_at', '>=', $registrationsFrom),
                'referralEvents as recent_visits_count' => fn (Builder $query) => $query
                    ->where('event_type', 'visit')
                    ->where('occurred_at', '>=', $visitsFrom),
                'earnings as recent_conversions_count' => fn (Builder $query) => $query
                    ->where('status', '!=', 'reversed')
                    ->where('earned_at', '>=', $paymentsFrom),
            ])->withSum([
                'earnings as recent_revenue_minor' => fn (Builder $query) => $query
                    ->where('status', '!=', 'reversed')
                    ->where('earned_at', '>=', $paymentsFrom),
            ], 'commissionable_amount_minor')
                ->orderByRaw('(
                    (recent_conversions_count * 1000000)
                    + (recent_registrations_count * 10000)
                    + LEAST(COALESCE(recent_revenue_minor, 0), 999999)
                    + LEAST(recent_visits_count, 9999)
                ) DESC')
                ->orderBy('partners.display_name');
        }

        return $query->limit($limit)->get();
    }

    /**
     * Referral codes are never silently corrected. The exact match and nearby
     * candidates are returned separately so a client must ask the viewer to
     * confirm a suggestion.
     *
     * @return array{input: string, exact_match: ?Partner, suggestions: Collection<int, Partner>}
     */
    public function assistReferralCode(string $input, int $limit = 3): array
    {
        $input = trim($input);
        $normalized = mb_strtoupper($input);
        $limit = max(1, min(5, $limit));

        if ($normalized === '') {
            return ['input' => '', 'exact_match' => null, 'suggestions' => collect()];
        }

        $exact = $this->activeQuery()
            ->whereRaw('LOWER(referral_code) = ?', [mb_strtolower($input)])
            ->first();
        if ($exact) {
            return ['input' => $input, 'exact_match' => $exact, 'suggestions' => collect()];
        }

        $length = mb_strlen($normalized);
        $maximumDistance = max(1, min(3, (int) ceil($length * 0.3)));
        $candidates = $this->activeQuery()
            ->whereRaw('CHAR_LENGTH(referral_code) BETWEEN ? AND ?', [max(1, $length - 3), $length + 3])
            ->select('partners.*')
            ->limit(1000)
            ->get()
            ->map(function (Partner $partner) use ($normalized): array {
                $candidate = mb_strtoupper((string) $partner->referral_code);

                return [
                    'partner' => $partner,
                    'distance' => levenshtein($normalized, $candidate),
                    'prefix' => str_starts_with($candidate, mb_substr($normalized, 0, min(2, mb_strlen($normalized)))) ? 0 : 1,
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['distance'] <= $maximumDistance)
            ->sortBy(fn (array $candidate): array => [$candidate['distance'], $candidate['prefix'], $candidate['partner']->display_name])
            ->take($limit)
            ->pluck('partner')
            ->values();

        return ['input' => $input, 'exact_match' => null, 'suggestions' => $candidates];
    }

    public function activeQuery(): Builder
    {
        $timezone = (string) config('app.partner_timezone', 'Africa/Kampala');
        $today = CarbonImmutable::now($timezone)->startOfDay();

        return Partner::query()
            ->active()
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('agreement_start_at')
                    ->orWhere('agreement_start_at', '<=', $today->endOfDay()->utc());
            })
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('agreement_end_at')
                    ->orWhere('agreement_end_at', '>=', $today->utc());
            });
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
