<?php

namespace App\Jobs;

use App\Models\AdminActivityAlert;
use App\Services\AdminAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAdminAlertJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $alertId)
    {
        $this->onQueue('communications');
    }

    public function handle(AdminAlertService $adminAlertService): void
    {
        $alert = AdminActivityAlert::query()->find($this->alertId);

        if (! $alert) {
            return;
        }

        $adminAlertService->deliver($alert);
    }
}
