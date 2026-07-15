<?php

namespace App\Filament\Resources\Concerns;

use App\Jobs\TelegramToContaboImportJob;
use App\Models\VideoSource;
use App\Services\TelebotClientService;

trait ManagesTeleObVideoSources
{
    private function createOrUpdateTeleObVideoSource(?VideoSource $record, array $data, string $assetType): VideoSource
    {
        $owner = $this->getOwnerRecord();
        $telegramUrl = trim((string) ($data['url'] ?? $record?->url ?? ''));

        if ($telegramUrl === '') {
            throw new \RuntimeException('Paste a Telegram message URL first.');
        }

        $telebot = app(TelebotClientService::class);
        if (! $telebot->isConfigured()) {
            throw new \RuntimeException($telebot->configurationError());
        }

        $this->ensureTeleObPortalCapacity($record);

        $metadata = array_merge((array) ($record?->metadata ?? []), (array) ($data['metadata'] ?? []), [
            'provider' => 'tele_ob',
            'storage_target' => 'contabo_object_storage',
            'fetch_status' => 'queued',
            'fetch_mode' => 'tele_ob_queue',
            'telegram_status' => 'telegram_submitted',
            'telegram_url' => $telegramUrl,
            'source_url' => $telegramUrl,
            'asset_type' => $assetType,
            'last_message' => 'Telegram to Contabo import queued.',
            'queued_at' => now()->toDateTimeString(),
            'last_synced_at' => now()->toDateTimeString(),
        ]);

        $payload = [
            'type' => 'tele_ob',
            'url' => $telegramUrl,
            'file_path' => null,
            'quality' => (string) ($data['quality'] ?? $record?->quality ?? 'auto'),
            'format' => (string) ($data['format'] ?? $record?->format ?? 'mp4'),
            'file_size' => null,
            'duration_seconds' => $data['duration_seconds'] ?? $record?->duration_seconds,
            'is_primary' => (bool) ($data['is_primary'] ?? $record?->is_primary ?? false),
            'is_active' => false,
            'metadata' => $metadata,
        ];

        if ($record) {
            $record->update($payload);
            $videoSource = $record->fresh();
        } else {
            $videoSource = $owner->videoSources()->create($payload);
        }

        TelegramToContaboImportJob::dispatch($videoSource->id)->onQueue('tele-ob-imports');

        return $videoSource;
    }

    private function ensureTeleObPortalCapacity(?VideoSource $record = null): void
    {
        $limit = max(1, (int) config('services.telebot.max_portal_objects', 3));

        $query = VideoSource::query()
            ->where('type', 'tele_ob')
            ->whereIn('metadata->fetch_status', ['queued', 'processing', 'downloading'])
            ->where('is_active', false);

        if ($record?->id) {
            $query->whereKeyNot($record->id);
        }

        $activeCount = $query->count();

        if ($activeCount >= $limit) {
            throw new \RuntimeException('Tele-OB already has ' . $activeCount . ' active portal object(s). Wait for one to finish before adding another Telegram import.');
        }
    }
}
