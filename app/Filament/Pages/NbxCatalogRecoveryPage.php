<?php

namespace App\Filament\Pages;

use App\Services\NbxCatalogRecoveryService;
use App\Services\ResyncRunTracker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class NbxCatalogRecoveryPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'NBX Catalog Recovery';

    protected static ?string $title = 'NBX Catalog Recovery';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 91;

    protected static string $view = 'filament.pages.nbx-catalog-recovery';

    public int $tier1Eligible = 0;

    public int $tier2Eligible = 0;

    /** @var array<string,mixed>|null */
    public ?array $latestTier1 = null;

    /** @var array<string,mixed>|null */
    public ?array $latestTier2 = null;

    public function mount(): void
    {
        $this->refreshCounts();
    }

    public function refreshCounts(): void
    {
        $recovery = app(NbxCatalogRecoveryService::class);
        $this->tier1Eligible = $recovery->eligibleForRediscoveryQuery()->count();
        $this->tier2Eligible = $recovery->eligibleForRecreationQuery()->count();
        $this->latestTier1 = ResyncRunTracker::latest('tier1');
        $this->latestTier2 = ResyncRunTracker::latest('tier2');
    }

    public function startTier1(): void
    {
        try {
            Artisan::call('nbx:resync-catalog', ['--tier' => 1]);
            Notification::make()
                ->success()
                ->title('Re-discovery queued')
                ->body(trim(Artisan::output()) ?: 'Tier 1 resync was queued.')
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()->danger()->title('Re-discovery could not be queued')->body($exception->getMessage())->persistent()->send();
        }
        $this->refreshCounts();
    }

    public function startTier2(): void
    {
        try {
            Artisan::call('nbx:resync-catalog', ['--tier' => 2, '--no-interaction' => true]);
            Notification::make()
                ->success()
                ->title('Re-fetch & re-transcode queued')
                ->body(trim(Artisan::output()) ?: 'Tier 2 resync was queued.')
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()->danger()->title('Re-fetch could not be queued')->body($exception->getMessage())->persistent()->send();
        }
        $this->refreshCounts();
    }
}
