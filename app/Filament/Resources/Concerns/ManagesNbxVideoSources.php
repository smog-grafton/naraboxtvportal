<?php

namespace App\Filament\Resources\Concerns;

use App\Models\VideoSource;
use App\Services\NbxVideoSourceService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;

trait ManagesNbxVideoSources
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function nbxProcessingFields(): array
    {
        return [
            Forms\Components\Select::make('nbx_storage_target')
                ->label('Storage target')
                ->options(['contabo' => 'Contabo Object Storage', 'public' => 'Local/Public Disk'])
                ->default('contabo'),
            Forms\Components\Select::make('nbx_retention_policy')
                ->label('Original retention')
                ->options([
                    'optimized_only' => 'Keep optimized MP4 only',
                    'retain_original' => 'Keep original and optimized MP4',
                ])
                ->default('optimized_only')
                ->helperText('The original is removed only after the optimized MP4 is uploaded and its byte size is verified.'),
            Forms\Components\Select::make('nbx_processing_preset')
                ->label('Processing preset')
                ->options([
                    'automatic' => 'Automatic',
                    'faststart_only' => 'Compatible remux / fast start',
                    'balanced_720p' => 'Balanced 720p',
                    'smaller_720p' => 'Smaller 720p',
                    'keep_source_resolution' => 'Keep source resolution',
                    'custom' => 'Custom',
                ])
                ->default('automatic'),
            Forms\Components\Select::make('nbx_max_resolution')
                ->label('Maximum resolution')
                ->options(['480' => '480p', '720' => '720p', '1080' => '1080p'])
                ->default('720'),
            Forms\Components\Toggle::make('nbx_faststart')->label('Create compatible fast-start MP4')->default(true),
            Forms\Components\Toggle::make('nbx_compress_enabled')->label('Compress before publication')->default(false),
            Forms\Components\TextInput::make('nbx_crf')->label('Video CRF')->numeric()->minValue(18)->maxValue(32)->default(23),
            Forms\Components\Select::make('nbx_encoder_preset')
                ->label('Encoder speed')
                ->options([
                    'veryfast' => 'Very fast', 'faster' => 'Faster', 'fast' => 'Fast',
                    'medium' => 'Medium', 'slow' => 'Slow',
                ])->default('medium'),
            Forms\Components\Select::make('nbx_audio_bitrate')
                ->label('AAC audio bitrate')
                ->options(['64k' => '64 kbps', '96k' => '96 kbps', '128k' => '128 kbps', '160k' => '160 kbps', '192k' => '192 kbps'])
                ->default('128k'),
            Forms\Components\Toggle::make('nbx_hls_480p')->label('Generate HLS 480p')->default(false),
            Forms\Components\Toggle::make('nbx_hls_720p')->label('Generate HLS 720p')->default(false),
            Forms\Components\Toggle::make('nbx_hls_1080p')->label('Generate HLS 1080p')->default(false),
            Forms\Components\Toggle::make('nbx_allow_downloads')->label('Allow optimized MP4 downloads')->default(true),
            Forms\Components\Toggle::make('nbx_allow_hls_streaming')->label('Allow HLS streaming')->default(true),
        ];
    }

    /**
     * @return array<int, \Filament\Tables\Actions\Action>
     */
    protected function nbxTableActions(): array
    {
        $managed = fn (VideoSource $record): bool => in_array($record->type, ['nbx-engine', 'tele_ob'], true)
            && ! empty($record->metadata['nbx_job_id']);

        return [
            Tables\Actions\Action::make('nbx_reconcile')
                ->label('Verify storage & repair URL')
                ->icon('heroicon-o-shield-check')
                ->visible($managed)
                ->requiresConfirmation()
                ->modalHeading('Verify existing files and repair publication')
                ->modalDescription('Checks the existing NBX storage package and restores verified public URLs. It does not reprocess, copy, or delete the video.')
                ->action(fn (VideoSource $record) => $this->runNbxAction($record, 'reconcile')),
            Tables\Actions\Action::make('nbx_retry')
                ->label('Retry NBX')
                ->icon('heroicon-o-arrow-path')
                ->visible($managed)
                ->requiresConfirmation()
                ->action(fn (VideoSource $record) => $this->runNbxAction($record, 'retry')),
            Tables\Actions\Action::make('nbx_reprocess')
                ->label('Reprocess')
                ->icon('heroicon-o-cog-6-tooth')
                ->visible($managed)
                ->requiresConfirmation()
                ->modalDescription('Uses the processing settings saved in this source. This is the only edit workflow that starts processing again.')
                ->action(function (VideoSource $record): void {
                    $metadata = (array) ($record->metadata ?? []);
                    $draft = (array) ($metadata['processing_config']['draft'] ?? $metadata['processing_config']['last_request'] ?? []);
                    $this->runNbxAction($record, 'reprocess', $this->nbxActionOptions($draft));
                }),
            Tables\Actions\Action::make('nbx_faststart')
                ->label('Generate fast-start MP4')
                ->visible($managed)
                ->requiresConfirmation()
                ->action(fn (VideoSource $record) => $this->runNbxAction($record, 'generate_faststart', ['faststart' => true])),
            Tables\Actions\Action::make('nbx_compress')
                ->label('Compress')
                ->visible($managed)
                ->requiresConfirmation()
                ->action(fn (VideoSource $record) => $this->runNbxAction($record, 'compress', ['faststart' => true, 'compress_enabled' => true])),
            Tables\Actions\Action::make('nbx_hls')
                ->label('Generate HLS')
                ->visible($managed)
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Toggle::make('hls_480p')->label('480p')->default(true),
                    Forms\Components\Toggle::make('hls_720p')->label('720p')->default(false),
                    Forms\Components\Toggle::make('hls_1080p')->label('1080p')->default(false),
                ])
                ->action(fn (VideoSource $record, array $data) => $this->runNbxAction($record, 'generate_hls', [
                    'allow_hls_streaming' => true,
                    'hls' => [
                        '480p' => (bool) ($data['hls_480p'] ?? false),
                        '720p' => (bool) ($data['hls_720p'] ?? false),
                        '1080p' => (bool) ($data['hls_1080p'] ?? false),
                    ],
                ])),
            Tables\Actions\Action::make('nbx_delete_original')
                ->label('Delete original')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->visible(fn (VideoSource $record): bool => $managed($record)
                    && (bool) ($record->metadata['original_retained'] ?? false)
                    && collect($record->metadata['outputs'] ?? [])->contains(
                        fn (mixed $output): bool => is_array($output)
                            && ($output['role'] ?? null) === 'playback_progressive'
                            && (bool) ($output['verified'] ?? false)
                    ))
                ->requiresConfirmation()
                ->modalDescription('NBX will delete the original only after independently verifying the optimized MP4 object and its byte size.')
                ->action(fn (VideoSource $record) => $this->runNbxAction($record, 'delete_original')),
        ];
    }

    private function runNbxAction(VideoSource $record, string $operation, array $options = []): void
    {
        try {
            app(NbxVideoSourceService::class)->runExplicitAction($record, $operation, $options);
            $reconciled = $operation === 'reconcile';
            Notification::make()->success()
                ->title($reconciled ? 'Storage verification finished' : 'NBX action accepted')
                ->body($reconciled ? 'Existing files were checked and verified URLs were synchronized.' : ucwords(str_replace('_', ' ', $operation)).' was accepted.')
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()->danger()->title('NBX action failed')->body($exception->getMessage())->send();
        }
    }

    private function nbxActionOptions(array $request): array
    {
        return [
            'storage_target' => $request['storage_target'] ?? 'contabo',
            'faststart' => (bool) ($request['faststart'] ?? true),
            'compress_enabled' => (bool) ($request['compress_enabled'] ?? $request['compression'] ?? false),
            'retention_policy' => $request['retention_policy'] ?? 'optimized_only',
            'allow_downloads' => (bool) ($request['allow_downloads'] ?? true),
            'allow_hls_streaming' => (bool) ($request['allow_hls_streaming'] ?? true),
            'hls' => $request['hls'] ?? [
                '480p' => (bool) ($request['hls_480p'] ?? false),
                '720p' => (bool) ($request['hls_720p'] ?? false),
                '1080p' => (bool) ($request['hls_1080p'] ?? false),
            ],
            'max_resolution' => (int) ($request['max_resolution'] ?? 720),
            'processing_preset' => $request['processing_preset'] ?? 'automatic',
            'crf' => (int) ($request['crf'] ?? 23),
            'encoder_preset' => $request['encoder_preset'] ?? 'medium',
            'audio_bitrate' => $request['audio_bitrate'] ?? '128k',
        ];
    }
}
