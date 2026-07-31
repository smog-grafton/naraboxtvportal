<?php

namespace App\Filament\Resources\Concerns;

use App\Models\VideoSource;
use App\Services\NbxVideoSourceService;

trait ManagesTeleObVideoSources
{
    private function createOrUpdateTeleObVideoSource(?VideoSource $record, array $data, string $assetType): VideoSource
    {
        $owner = $this->getOwnerRecord();
        $telegramUrl = trim((string) ($data['url'] ?? $record?->url ?? ''));

        if ($telegramUrl === '') {
            throw new \RuntimeException('Paste a Telegram message URL first.');
        }

        $data = array_merge($data, [
            'url' => $telegramUrl,
            'portal_source_type' => 'tele_ob',
        ]);

        return app(NbxVideoSourceService::class)->submitTelegram($owner, $data, $assetType);
    }
}
