<?php

namespace App\Observers;

use App\Models\CreatorApplication;

class CreatorApplicationObserver
{
    public function updated(CreatorApplication $application): void
    {
        // Identity approval has side effects (claim linking, permissions, role,
        // verification and audit). It is intentionally performed only through
        // CreatorIdentityService from an explicit administrator action.
    }
}
