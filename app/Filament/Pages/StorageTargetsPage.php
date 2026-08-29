<?php

namespace App\Filament\Pages;

use App\Services\Storage\AutomaticStorageSelector;
use Filament\Pages\Page;

class StorageTargetsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Storage Targets';

    protected static ?string $title = 'Storage Targets';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 90;

    protected static string $view = 'filament.pages.storage-targets';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTargets(): array
    {
        return app(AutomaticStorageSelector::class)->statusForAllTargets();
    }
}
