<?php

namespace App\Services;

use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IoTeCService
{
    private const CACHE_KEY = 'iotec_access_token';

    private string $clientId;

    private string $clientSecret;

    private string $grantType;

    private string $idBaseUrl;

    private string $payBaseUrl;

    private ?string $walletId = null;

    public function __construct(?PaymentGateway $gateway = null)
    {
        if ($gateway && $gateway->slug === 'iotec') {
            $config = $gateway->config;
            $this->clientId = (string) ($config['client_id'] ?? env('IOTEC_CLIENT_ID', ''));
            $this->clientSecret = (string) ($config['client_secret'] ?? env('IOTEC_CLIENT_SECRET', ''));
            $this->grantType = (string) ($config['grant_type'] ?? env('IOTEC_GRANT_TYPE', 'client_credentials'));
            $this->idBaseUrl = rtrim((string) ($config['id_base_url'] ?? env('IOTEC_ID_BASE_URL', 'https://id.iotec.io')), '/');
            $this->payBaseUrl = rtrim((string) ($config['pay_base_url'] ?? env('IOTEC_PAY_BASE_URL', 'https://pay.iotec.io')), '/');
            $walletId = $config['wallet_id'] ?? env('IOTEC_WALLET_ID', '');
            $this->walletId = $walletId !== '' ? trim((string) $walletId) : null;
        } else {
            $this->clientId = (string) env('IOTEC_CLIENT_ID', '');
            $this->clientSecret = (string) env('IOTEC_CLIENT_SECRET', '');
            $this->grantType = (string) env('IOTEC_GRANT_TYPE', 'client_credentials');
            $this->idBaseUrl = rtrim((string) env('IOTEC_ID_BASE_URL', 'https://id.iotec.io'), '/');
            $this->payBaseUrl = rtrim((string) env('IOTEC_PAY_BASE_URL', 'https://pay.iotec.io'), '/');
            $walletId = env('IOTEC_WALLET_ID', '');
            $this->walletId = $walletId !== '' ? trim($walletId) : null;
        }
    }

    /**
     * Normalize Uganda phone to 256XXXXXXXXX. Accepts +256, 256, 0XXXXXXXXX.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($digits, '256')) {
            return $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '256' . substr($digits, 1);
        }
        return '256' . $digits;
    }

    /**
     * Validate Uganda MSISDN (9 digits after 256, so 12 total).
     */
    public static function validatePhone(string $phone): bool
    {
        $normalized = self::normalizePhone($phone);
        return strlen($normalized) === 12 && str_starts_with($normalized, '256') && ctype_digit($normalized);
    }

    /**
     * Mask phone for logs: +256***456
     */
    public static function maskPhone(string $phone): string
    {
        $n = self::normalizePhone($phone);
        if (strlen($n) < 4) {
            return '***';
        }
        return '+' . substr($n, 0, 3) . '***' . substr($n, -3);
    }

    /**
     * Get OAuth2 access token (cached until expiry - 30s buffer).
     */
    public function getAccessToken(): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        $response = Http::asForm()->post($this->idBaseUrl . '/connect/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => $this->grantType,
        ]);

        $data = $response->json();
        if (! $response->successful() || empty($data['access_token'])) {
            Log::warning('ioTec token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $ttl = max(60, (int) ($data['expires_in'] ?? 300) - 30);
        Cache::put(self::CACHE_KEY, $data['access_token'], $ttl);

        return $data['access_token'];
    }

    /**
     * Initiate collection (mobile money prompt). Amount in UGX, min 500.
     * Returns ['request_id' => uuid, 'status' => string] or ['error' => message].
     */
    public function collect(string $transactionRef, float $amount, string $payer, ?string $payerNote = null, ?string $payeeNote = null): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['error' => 'Failed to obtain ioTec access token'];
        }

        $amountInt = (int) round($amount);
        if ($amountInt < 500) {
            return ['error' => 'Amount must be at least 500 UGX'];
        }

        $payerNormalized = self::normalizePhone($payer);
        if (! self::validatePhone($payer)) {
            return ['error' => 'Invalid Uganda phone number'];
        }

        if ($this->walletId === null || $this->walletId === '') {
            return ['error' => 'ioTec Wallet ID is not configured. Please set it in Admin → Payment Gateways → ioTec Pay.'];
        }

        $payload = [
            'amount' => $amountInt,
            'payer' => $payerNormalized,
            'externalId' => $transactionRef,
            'currency' => 'UGX',
        ];
        if ($this->walletId !== null && $this->walletId !== '') {
            $payload['walletId'] = $this->walletId;
        }
        if ($payerNote !== null && $payerNote !== '') {
            $payload['payerNote'] = substr($payerNote, 0, 100);
        }
        if ($payeeNote !== null && $payeeNote !== '') {
            $payload['payeeNote'] = substr($payeeNote, 0, 100);
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->post($this->payBaseUrl . '/api/collections/collect', $payload);

        $data = $response->json();
        if (! $response->successful()) {
            Log::warning('ioTec collect failed', [
                'status' => $response->status(),
                'external_id' => $transactionRef,
                'payer_masked' => self::maskPhone($payer),
            ]);
            return [
                'error' => $data['error'] ?? $data['message'] ?? $response->body() ?: 'Collect request failed',
            ];
        }

        $requestId = $data['id'] ?? null;
        $status = $data['status'] ?? 'Pending';
        if (! $requestId) {
            return ['error' => 'No request id in ioTec response'];
        }

        return [
            'request_id' => $requestId,
            'status' => $status,
            'raw' => $data,
        ];
    }

    /**
     * Get collection status. Returns normalized: pending | success | failed | cancelled | timeout.
     */
    public function getStatus(string $requestId): array
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return ['normalized' => 'failed', 'error' => 'No access token'];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get($this->payBaseUrl . '/api/collections/status/' . $requestId);

        $data = $response->json();
        if (! $response->successful()) {
            return [
                'normalized' => 'failed',
                'error' => $data['message'] ?? $response->body() ?: 'Status request failed',
                'raw' => $data,
            ];
        }

        $status = $data['status'] ?? 'Pending';
        $normalized = $this->mapStatus($status);

        return [
            'normalized' => $normalized,
            'status' => $status,
            'raw' => $data,
        ];
    }

    private function mapStatus(string $status): string
    {
        $s = strtolower($status);
        if ($s === 'success') {
            return 'success';
        }
        if (in_array($s, ['failed', 'cancelled', 'rejected', 'rolledback'], true)) {
            return 'failed';
        }
        if (in_array($s, ['pending', 'senttovendor', 'awaitingapproval', 'scheduled'], true)) {
            return 'pending';
        }
        return 'pending';
    }
}
