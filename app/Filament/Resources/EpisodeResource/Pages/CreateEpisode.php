<?php

namespace App\Filament\Resources\EpisodeResource\Pages;

use App\Events\EpisodePublished;
use App\Filament\Resources\EpisodeResource;
use App\Services\CampaignService;
use Filament\Resources\Pages\CreateRecord;

class CreateEpisode extends CreateRecord
{
    protected static string $resource = EpisodeResource::class;

    protected function afterCreate(): void
    {
        event(new EpisodePublished($this->record));

        $formState = $this->form->getState();
        if (!empty($formState['send_email_on_save'])) {
            app(CampaignService::class)->queueTemplateCampaign(
                name: 'New episode: ' . $this->record->title,
                templateName: 'new_episode_added',
                templateData: [
                    'episode_title' => $this->record->title,
                    'watch_url' => rtrim((string) config('app.url'), '/') . '/watch/show/' . $this->record->season?->tv_show_id,
                    'created_at' => now(),
                ],
            );
        }
    }
}
