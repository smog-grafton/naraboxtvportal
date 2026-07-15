<?php

namespace App\Services;

use App\Jobs\SendTemplatedEmailJob;
use App\Models\CommunicationCampaign;
use App\Mail\RenderedTemplateMail;
use App\Models\CommunicationLog;
use App\Models\CommunicationRecipient;
use App\Models\EmailTemplate;
use App\Models\SmtpSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CommunicationService
{
    public function queueTemplatedEmail(
        string $to,
        string $templateName,
        array $data = [],
        ?int $userId = null,
        ?int $campaignId = null,
        ?int $recipientId = null
    ): CommunicationLog {
        $rendered = $this->renderTemplate($templateName, $data);

        $log = CommunicationLog::create([
            'communication_campaign_id' => $campaignId,
            'communication_recipient_id' => $recipientId,
            'user_id' => $userId,
            'channel' => 'email',
            'recipient' => $to,
            'subject' => $rendered['subject'],
            'template_name' => $templateName,
            'status' => 'pending',
        ]);

        SendTemplatedEmailJob::dispatch(
            to: $to,
            templateName: $templateName,
            data: $data,
            userId: $userId,
            campaignId: $campaignId,
            recipientId: $recipientId,
            logId: $log->id,
        )->onQueue('communications');

        return $log;
    }

    public function deliverTemplatedEmail(
        string $to,
        string $templateName,
        array $data = [],
        ?int $userId = null,
        ?int $campaignId = null,
        ?int $recipientId = null,
        ?int $logId = null
    ): CommunicationLog {
        $rendered = $this->renderTemplate($templateName, $data);

        $log = $logId
            ? CommunicationLog::query()->findOrFail($logId)
            : CommunicationLog::create([
                'communication_campaign_id' => $campaignId,
                'communication_recipient_id' => $recipientId,
                'user_id' => $userId,
                'channel' => 'email',
                'recipient' => $to,
                'subject' => $rendered['subject'],
                'template_name' => $templateName,
                'status' => 'pending',
            ]);

        try {
            $this->applySmtpConfig();

            Mail::to($to)->send(new RenderedTemplateMail(
                $rendered['subject'],
                $rendered['body']
            ));

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
                'provider_response' => ['mailer' => config('mail.default')],
                'error_message' => null,
            ]);

            if ($recipientId) {
                CommunicationRecipient::query()->whereKey($recipientId)->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('communication.email_failed', [
                'recipient' => $to,
                'template' => $templateName,
                'error' => $exception->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'failed_at' => now(),
            ]);

            if ($recipientId) {
                CommunicationRecipient::query()->whereKey($recipientId)->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => $exception->getMessage(),
                ]);
            }
        }

        if ($campaignId) {
            $this->syncCampaignProgress($campaignId);
        }

        return $log->fresh();
    }

    public function renderTemplate(string $templateName, array $data = []): array
    {
        $template = EmailTemplate::getByName($templateName);

        if (! $template) {
            throw new \RuntimeException("Email template [{$templateName}] not found.");
        }

        return $template->render($this->normalizeData($data));
    }

    public function unsubscribeUrlFor(User $user): string
    {
        return rtrim((string) config('app.url'), '/') . '/api/v1/notifications/preferences/unsubscribe?token=' . $user->marketing_opt_in_token;
    }

    public function normalizeData(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $data[$key] = $value->format('M d, Y H:i');
            }
        }

        return $data;
    }

    private function applySmtpConfig(): void
    {
        $smtpSettings = SmtpSetting::getActive();

        if ($smtpSettings) {
            $smtpSettings->applyToConfig();
        }
    }

    public function syncCampaignProgress(int $campaignId): void
    {
        $campaign = CommunicationCampaign::query()->find($campaignId);

        if (! $campaign) {
            return;
        }

        $totalRecipients = $campaign->recipients()->count();
        $successCount = $campaign->recipients()->where('status', 'sent')->count();
        $failureCount = $campaign->recipients()->where('status', 'failed')->count();

        if ($totalRecipients === 0) {
            $campaign->update([
                'success_count' => 0,
                'failure_count' => 0,
            ]);

            return;
        }

        $pendingCount = max(0, $totalRecipients - ($successCount + $failureCount));

        $campaign->update([
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ]);

        if ($pendingCount > 0 || in_array($campaign->status, ['completed', 'failed'], true)) {
            return;
        }

        $finalStatus = $successCount > 0 ? 'completed' : 'failed';

        $campaign->update([
            'status' => $finalStatus,
            'completed_at' => now(),
            'last_error' => $failureCount > 0 && $successCount === 0 ? 'All campaign deliveries failed.' : $campaign->last_error,
        ]);

        app(AdminAlertService::class)->queue(
            type: 'campaign_completed',
            title: $failureCount > 0 ? 'Campaign finished with failures' : 'Campaign completed',
            message: "Campaign {$campaign->name} finished. Sent: {$successCount}. Failed: {$failureCount}.",
            payload: [
                'campaign_id' => $campaign->id,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ],
        );

        if ($failureCount > 0 && $failureCount >= max(3, (int) ceil($totalRecipients * 0.25))) {
            app(AdminAlertService::class)->queue(
                type: 'campaign_failures_high',
                title: 'Campaign failure threshold reached',
                message: "Campaign {$campaign->name} recorded {$failureCount} failed deliveries out of {$totalRecipients}.",
                payload: [
                    'campaign_id' => $campaign->id,
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'total_recipients' => $totalRecipients,
                ],
            );
        }
    }
}
