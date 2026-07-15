<?php

namespace App\Jobs;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationLog;
use App\Models\CommunicationRecipient;
use App\Services\CampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $campaignId)
    {
        $this->onQueue('communications');
    }

    public function handle(CampaignService $campaignService): void
    {
        $campaign = CommunicationCampaign::query()->find($this->campaignId);

        if (! $campaign) {
            return;
        }

        $campaignService->dispatchRecipients($campaign);
    }

    public function failed(\Throwable $exception): void
    {
        CommunicationCampaign::query()->whereKey($this->campaignId)->update([
            'status' => 'failed',
            'last_error' => $exception->getMessage(),
        ]);

        CommunicationLog::create([
            'communication_campaign_id' => $this->campaignId,
            'channel' => 'email',
            'recipient' => 'campaign',
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'failed_at' => now(),
        ]);
    }
}
