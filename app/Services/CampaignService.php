<?php

namespace App\Services;

use App\Jobs\SendCampaignJob;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationRecipient;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CampaignService
{
    public function prepareRecipients(CommunicationCampaign $campaign): Collection
    {
        if ($campaign->send_to_all) {
            return $this->usersQuery($campaign)->get();
        }

        return match ($campaign->audience_type) {
            'active_subscribers' => $this->usersQuery($campaign)
                ->whereRaw("LOWER(COALESCE(plan_status, '')) = ?", ['active'])
                ->get(),
            'expired_subscribers' => $this->usersQuery($campaign)
                ->whereRaw("LOWER(COALESCE(plan_status, '')) = ?", ['expired'])
                ->get(),
            'new_users' => $this->usersQuery($campaign)
                ->where('created_at', '>=', now()->subDays(14))
                ->get(),
            'renters' => $this->usersQuery($campaign)
                ->whereHas('rentals')
                ->orWhereHas('userRentals')
                ->get(),
            'buyers' => $this->usersQuery($campaign)
                ->whereHas('purchases')
                ->orWhereHas('userPurchases')
                ->get(),
            'inactive_watchers' => $this->usersQuery($campaign)
                ->whereDoesntHave('watchHistory', fn (Builder $query) => $query->where('last_watched_at', '>=', now()->subDays(30)))
                ->get(),
            'never_subscribed' => $this->usersQuery($campaign)
                ->whereDoesntHave('subscriptions')
                ->whereDoesntHave('userSubscriptions')
                ->get(),
            default => $this->usersQuery($campaign)->get(),
        };
    }

    public function dispatchRecipients(CommunicationCampaign $campaign): void
    {
        $template = $campaign->template;
        if (! $template) {
            throw new \RuntimeException('Campaign template is required.');
        }

        $campaign->update([
            'status' => 'sending',
            'started_at' => now(),
        ]);

        $users = $this->prepareRecipients($campaign);

        $templateData = (array) data_get($campaign->filters, 'template_data', []);

        foreach ($users as $user) {
            if (! $user->email) {
                continue;
            }

            $recipient = CommunicationRecipient::firstOrCreate([
                'communication_campaign_id' => $campaign->id,
                'email' => $user->email,
            ], [
                'user_id' => $user->id,
                'name' => $user->name,
            ]);

            app(CommunicationService::class)->queueTemplatedEmail(
                to: $user->email,
                templateName: $template->name,
                data: [
                    ...$templateData,
                    'user_name' => $user->name,
                    'email' => $user->email,
                    'unsubscribe_url' => app(CommunicationService::class)->unsubscribeUrlFor($user),
                    'created_at' => now(),
                ],
                userId: $user->id,
                campaignId: $campaign->id,
                recipientId: $recipient->id,
            );
        }

        foreach (($campaign->recipient_emails ?? []) as $email) {
            $recipient = CommunicationRecipient::firstOrCreate([
                'communication_campaign_id' => $campaign->id,
                'email' => $email,
            ]);

            app(CommunicationService::class)->queueTemplatedEmail(
                to: $email,
                templateName: $template->name,
                data: [
                    ...$templateData,
                    'email' => $email,
                    'created_at' => now(),
                ],
                campaignId: $campaign->id,
                recipientId: $recipient->id,
            );
        }

        app(CommunicationService::class)->syncCampaignProgress($campaign->id);
    }

    public function queueTemplateCampaign(
        string $name,
        string $templateName,
        array $templateData,
        string $audienceType = 'selected_users',
        bool $sendToAll = true,
        bool $marketingOnly = true
    ): CommunicationCampaign {
        $template = EmailTemplate::getByName($templateName);

        if (! $template) {
            throw new \RuntimeException("Campaign template [{$templateName}] not found.");
        }

        $campaign = CommunicationCampaign::create([
            'name' => $name,
            'channel' => 'email',
            'email_template_id' => $template->id,
            'created_by' => auth()->id(),
            'status' => 'scheduled',
            'audience_type' => $audienceType,
            'send_to_all' => $sendToAll,
            'marketing_only' => $marketingOnly,
            'filters' => [
                'template_data' => $templateData,
            ],
        ]);

        SendCampaignJob::dispatch($campaign->id)->onQueue('communications');

        return $campaign;
    }

    private function usersQuery(CommunicationCampaign $campaign): Builder
    {
        return User::query()
            ->whereNotNull('email')
            ->when($campaign->marketing_only, fn (Builder $query) => $query->where('marketing_emails_enabled', true));
    }
}
