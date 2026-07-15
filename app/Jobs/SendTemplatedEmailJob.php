<?php

namespace App\Jobs;

use App\Models\CommunicationLog;
use App\Services\CommunicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTemplatedEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $to,
        public string $templateName,
        public array $data = [],
        public ?int $userId = null,
        public ?int $campaignId = null,
        public ?int $recipientId = null,
        public ?int $logId = null,
    ) {
        $this->onQueue('communications');
    }

    public function handle(CommunicationService $communicationService): void
    {
        $communicationService->deliverTemplatedEmail(
            to: $this->to,
            templateName: $this->templateName,
            data: $this->data,
            userId: $this->userId,
            campaignId: $this->campaignId,
            recipientId: $this->recipientId,
            logId: $this->logId,
        );
    }

    public function failed(\Throwable $exception): void
    {
        if ($this->logId) {
            CommunicationLog::query()->whereKey($this->logId)->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'failed_at' => now(),
            ]);
        }
    }
}
