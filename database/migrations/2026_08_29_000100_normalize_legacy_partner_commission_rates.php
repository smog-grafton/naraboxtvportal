<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original admin field exposed a basis-point column directly.
        // Values such as "20" were therefore saved as 20 bps (0.20%) even
        // though the administrator entered a 20 percent agreement. Normalize
        // the legacy human-percentage range once; future forms dehydrate the
        // percentage to canonical basis points before saving.
        if (Schema::hasTable('partners') && Schema::hasColumn('partners', 'default_commission_bps')) {
            DB::table('partners')
                ->whereBetween('default_commission_bps', [1, 100])
                ->update(['default_commission_bps' => DB::raw('default_commission_bps * 100')]);

            if (Schema::hasColumn('partners', 'rate_overrides')) {
                DB::table('partners')->select(['id', 'rate_overrides'])->orderBy('id')->chunkById(200, function ($partners): void {
                    foreach ($partners as $partner) {
                        $overrides = is_string($partner->rate_overrides)
                            ? json_decode($partner->rate_overrides, true)
                            : $partner->rate_overrides;
                        if (! is_array($overrides)) {
                            continue;
                        }

                        $changed = false;
                        foreach ($overrides as $type => $value) {
                            if (is_numeric($value) && (float) $value >= 1 && (float) $value <= 100) {
                                $overrides[$type] = (int) round((float) $value * 100);
                                $changed = true;
                            }
                        }

                        if ($changed) {
                            DB::table('partners')->where('id', $partner->id)->update([
                                'rate_overrides' => json_encode($overrides, JSON_THROW_ON_ERROR),
                            ]);
                        }
                    }
                });
            }
        }

        if (Schema::hasTable('partner_campaigns') && Schema::hasColumn('partner_campaigns', 'commission_bps')) {
            DB::table('partner_campaigns')
                ->whereBetween('commission_bps', [1, 100])
                ->update(['commission_bps' => DB::raw('commission_bps * 100')]);
        }
    }

    public function down(): void
    {
        // Deliberately irreversible: dividing every current rate would corrupt
        // correctly stored values created after this migration.
    }
};
