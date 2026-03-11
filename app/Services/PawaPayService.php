<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PawaPayService
{
    public function initiateDeposit(
        string $depositId,
        string $phone,
        string $provider,
        string $amount,
        string $currency,
        ?string $clientReferenceId = null,
        array $metadata = []
    ): array {
        $payload = [
            'depositId' => $depositId,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'payer' => [
                'type' => 'MMO',
                'accountDetails' => [
                    'phoneNumber' => preg_replace('/\D+/', '', $phone),
                    'provider' => strtoupper($provider),
                ],
            ],
        ];

        if ($clientReferenceId) {
            $payload['clientReferenceId'] = $clientReferenceId;
        }
        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        /** @var Response $response */
        $response = $this->client()->post('/v2/deposits', $payload);
        $body = $response->json();

        return [
            'ok' => $response->successful(),
            'status_code' => $response->status(),
            'payload' => $payload,
            'body' => is_array($body) ? $body : ['raw' => $response->body()],
            'normalized_status' => $this->normalizeStatus($this->extractProviderStatus(is_array($body) ? $body : [])),
        ];
    }

    public function checkDepositStatus(string $depositId): array
    {
        /** @var Response $response */
        $response = $this->client()->get('/v2/deposits/' . $depositId);
        $body = $response->json();

        return [
            'ok' => $response->successful(),
            'status_code' => $response->status(),
            'body' => is_array($body) ? $body : ['raw' => $response->body()],
            'normalized_status' => $this->normalizeStatus($this->extractProviderStatus(is_array($body) ? $body : [])),
        ];
    }

    public function normalizeStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'COMPLETED' => 'SUCCESS',
            'FAILED', 'REJECTED' => 'FAILED',
            'DUPLICATE_IGNORED', 'ACCEPTED', 'SUBMITTED', 'ENQUEUED', 'PROCESSING' => 'PENDING',
            default => 'PENDING',
        };
    }

    public function extractFailureReason(array $payload): ?string
    {
        if (isset($payload['data']['failureReason']['failureMessage']) && is_string($payload['data']['failureReason']['failureMessage'])) {
            return $payload['data']['failureReason']['failureMessage'];
        }
        if (isset($payload['data']['failureReason']['failureCode']) && is_string($payload['data']['failureReason']['failureCode'])) {
            return $payload['data']['failureReason']['failureCode'];
        }
        if (isset($payload['failureReason']['failureMessage']) && is_string($payload['failureReason']['failureMessage'])) {
            return $payload['failureReason']['failureMessage'];
        }
        if (isset($payload['failureReason']['failureCode']) && is_string($payload['failureReason']['failureCode'])) {
            return $payload['failureReason']['failureCode'];
        }

        return null;
    }

    public function extractProviderStatus(array $payload): ?string
    {
        if (isset($payload['data']['status']) && is_string($payload['data']['status'])) {
            return $payload['data']['status'];
        }
        if (isset($payload['status']) && is_string($payload['status'])) {
            return $payload['status'];
        }

        return null;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.pawapay.base_url'), '/'))
            ->withToken((string) config('services.pawapay.api_token'))
            ->acceptJson()
            ->timeout(20);
    }
}

