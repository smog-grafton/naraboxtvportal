<?php

namespace App\Observers;

use App\Filament\Resources\CreatorApplicationResource;
use App\Models\CreatorApplication;

class CreatorApplicationObserver
{
    private static bool $handlingApproval = false;

    /**
     * When status changes to 'approved' (e.g. via Edit form instead of Approve button),
     * ensure VJ/MediaLibrary profile is created and user role is upgraded.
     */
    public function updated(CreatorApplication $application): void
    {
        if (self::$handlingApproval) {
            return;
        }

        if ($application->status !== 'approved') {
            return;
        }

        if (!$application->wasChanged('status') || $application->getOriginal('status') === 'approved') {
            return;
        }

        self::$handlingApproval = true;
        try {
            CreatorApplicationResource::approveApplication($application);
        } finally {
            self::$handlingApproval = false;
        }
    }
}
