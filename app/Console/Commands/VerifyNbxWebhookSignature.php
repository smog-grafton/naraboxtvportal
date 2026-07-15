<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifyNbxWebhookSignature extends Command
{
    protected $signature = 'nbx:verify-webhook
        {--job-id=manual-test : Sample NBX job id}
        {--event=job.completed : Sample NBX event name}';

    protected $description = 'Generate and verify NBX webhook signing headers for manual callback testing';

    public function handle(): int
    {
        $secret = trim((string) config('services.nbx_engine.webhook_secret', ''));
        if ($secret === '') {
            $this->error('NBX_ENGINE_WEBHOOK_SECRET is not configured.');

            return self::FAILURE;
        }

        $event = (string) $this->option('event');
        $jobId = (string) $this->option('job-id');
        $payload = [
            'event' => $event,
            'nbx_job_id' => $jobId,
            'job_id' => $jobId,
            'status' => 'completed',
            'source' => [
                'nbx_job_id' => $jobId,
                'status' => 'completed',
            ],
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (! is_string($body)) {
            $this->error('Could not encode sample payload.');

            return self::FAILURE;
        }

        $timestamp = (string) time();
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        $expected = substr($signature, 7);
        $verified = hash_equals($expected, hash_hmac('sha256', $timestamp . '.' . $body, $secret));

        $this->info($verified ? 'NBX webhook signature verification OK.' : 'NBX webhook signature verification FAILED.');
        $this->line('X-NBX-Event: ' . $event);
        $this->line('X-NBX-Job-Id: ' . $jobId);
        $this->line('X-NBX-Timestamp: ' . $timestamp);
        $this->line('X-NBX-Signature: ' . $signature);
        $this->line('Payload: ' . $body);

        return $verified ? self::SUCCESS : self::FAILURE;
    }
}
