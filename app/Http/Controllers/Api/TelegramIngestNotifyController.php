<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelegramImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramIngestNotifyController extends Controller
{
    public function notify(Request $request): JsonResponse
    {
        $token = (string) config('services.telegram.ingest_notify_token', '');
        $provided = (string) $request->bearerToken();
        if ($token === '' || $provided === '' || ! hash_equals($token, $provided)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized.',
            ], 401);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'max:32'],
            'telegram' => ['required', 'array'],
            'telegram.telegram_chat_id' => ['nullable'],
            'telegram.telegram_message_id' => ['nullable'],
            'telegram.telegram_channel' => ['nullable', 'string', 'max:255'],
            'telegram.title_guess' => ['nullable', 'string', 'max:255'],
            'telegram.vj_guess' => ['nullable', 'string', 'max:255'],
            'telegram.episode_guess' => ['nullable'],
            'telegram.telegram_date' => ['nullable', 'string'],
            'cdn_response' => ['required', 'array'],
            'cdn_response.asset_id' => ['required', 'uuid'],
            'cdn_response.source_id' => ['required'],
        ]);

        $telegram = $validated['telegram'];
        $cdnResponse = $validated['cdn_response'];
        $chatId = isset($telegram['telegram_chat_id']) ? (string) $telegram['telegram_chat_id'] : null;
        $messageId = isset($telegram['telegram_message_id']) ? (string) $telegram['telegram_message_id'] : null;

        $payload = [
            'telegram_chat_id' => $chatId,
            'telegram_message_id' => $messageId,
            'telegram_channel' => $telegram['telegram_channel'] ?? null,
            'title_guess' => $telegram['title_guess'] ?? null,
            'vj_guess' => $telegram['vj_guess'] ?? null,
            'episode_guess' => isset($telegram['episode_guess']) ? (string) $telegram['episode_guess'] : null,
            'cdn_asset_id' => (string) $cdnResponse['asset_id'],
            'cdn_source_id' => (int) $cdnResponse['source_id'],
            'status' => (string) $validated['status'],
            'raw_metadata' => [
                'telegram' => $telegram,
                'cdn_response' => $cdnResponse,
            ],
        ];

        $import = TelegramImport::updateOrCreate(
            [
                'telegram_chat_id' => $chatId,
                'telegram_message_id' => $messageId,
            ],
            $payload
        );

        Log::info('Telegram ingest notify recorded', [
            'telegram_import_id' => $import->id,
            'cdn_asset_id' => $import->cdn_asset_id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $import->id,
                'cdn_asset_id' => $import->cdn_asset_id,
                'cdn_source_id' => $import->cdn_source_id,
            ],
        ], 200);
    }
}
