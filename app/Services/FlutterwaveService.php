<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveService
{
    private string $publicKey;
    private string $secretKey;
    private string $encryptionKey;
    private string $baseUrl;
    private bool $isLive;

    public function __construct()
    {
        $this->publicKey = config('services.flutterwave.public_key', env('FLW_PUBLIC_KEY'));
        $this->secretKey = config('services.flutterwave.secret_key', env('FLW_SECRET_KEY'));
        $this->encryptionKey = config('services.flutterwave.encryption_key', env('FLW_ENCRYPTION_KEY'));
        $this->isLive = config('services.flutterwave.env', env('FLW_ENV', 'live')) === 'live';
        $this->baseUrl = $this->isLive 
            ? 'https://api.flutterwave.com/v3' 
            : 'https://api.flutterwave.com/v3';
    }

    /**
     * Initialize a payment transaction
     * 
     * @param array $data Payment data
     * @return array
     */
    public function initiatePayment(array $data): array
    {
        try {
            $payload = [
                'tx_ref' => $data['tx_ref'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'UGX',
                'redirect_url' => $data['redirect_url'],
                'payment_options' => $data['payment_options'] ?? 'card,mobilemoneyuganda',
                'customer' => [
                    'email' => $data['customer']['email'],
                    'phonenumber' => $data['customer']['phone'] ?? null,
                    'name' => $data['customer']['name'],
                ],
                'customizations' => [
                    'title' => $data['customizations']['title'] ?? 'NaraBox Payment',
                    'description' => $data['customizations']['description'] ?? 'Payment for NaraBox content',
                    'logo' => $data['customizations']['logo'] ?? null,
                ],
                'meta' => $data['meta'] ?? [],
            ];

            // Remove null values
            $payload = array_filter($payload, fn($value) => $value !== null);
            if (isset($payload['customer']['phonenumber']) && $payload['customer']['phonenumber'] === null) {
                unset($payload['customer']['phonenumber']);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/payments', $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['status']) && $responseData['status'] === 'success') {
                return [
                    'success' => true,
                    'data' => $responseData['data'],
                    'link' => $responseData['data']['link'] ?? null,
                    'message' => $responseData['message'] ?? 'Payment initiated successfully',
                ];
            }

            Log::error('Flutterwave payment initiation failed', [
                'response' => $responseData,
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'message' => $responseData['message'] ?? 'Failed to initiate payment',
                'data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave service error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify a transaction
     * 
     * @param string $transactionId Flutterwave transaction ID
     * @return array
     */
    public function verifyTransaction(string $transactionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/transactions/' . $transactionId . '/verify');

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['status']) && $responseData['status'] === 'success') {
                $transactionData = $responseData['data'];
                $transactionStatus = $transactionData['status'] ?? 'unknown';
                
                return [
                    'success' => true,
                    'verified' => $transactionStatus === 'successful',
                    'status' => $transactionStatus,
                    'amount' => $transactionData['amount'] ?? null,
                    'currency' => $transactionData['currency'] ?? null,
                    'tx_ref' => $transactionData['tx_ref'] ?? null,
                    'flw_ref' => $transactionData['flw_ref'] ?? null,
                    'data' => $transactionData,
                ];
            }
            
            // If response is successful but status is not 'success', check if transaction exists but failed
            if ($response->successful() && isset($responseData['status'])) {
                $transactionData = $responseData['data'] ?? null;
                if ($transactionData) {
                    $transactionStatus = $transactionData['status'] ?? 'unknown';
                    return [
                        'success' => true,
                        'verified' => $transactionStatus === 'successful',
                        'status' => $transactionStatus,
                        'amount' => $transactionData['amount'] ?? null,
                        'currency' => $transactionData['currency'] ?? null,
                        'tx_ref' => $transactionData['tx_ref'] ?? null,
                        'flw_ref' => $transactionData['flw_ref'] ?? null,
                        'data' => $transactionData,
                    ];
                }
            }

            Log::error('Flutterwave transaction verification failed', [
                'transaction_id' => $transactionId,
                'response' => $responseData,
            ]);

            return [
                'success' => false,
                'verified' => false,
                'message' => $responseData['message'] ?? 'Failed to verify transaction',
                'data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave verification error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'verified' => false,
                'message' => 'Verification service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify transaction by tx_ref
     * 
     * @param string $txRef Transaction reference
     * @return array
     */
    public function verifyByTxRef(string $txRef): array
    {
        try {
            // Flutterwave v3 uses /transactions/verify_by_reference endpoint
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/transactions/verify_by_reference?tx_ref=' . urlencode($txRef));

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['status']) && $responseData['status'] === 'success') {
                $transactionData = $responseData['data'];
                
                // Handle both single transaction and array of transactions
                if (isset($transactionData[0])) {
                    $transactionData = $transactionData[0];
                }
                
                $transactionStatus = $transactionData['status'] ?? 'unknown';
                
                return [
                    'success' => true,
                    'verified' => $transactionStatus === 'successful',
                    'status' => $transactionStatus,
                    'amount' => $transactionData['amount'] ?? null,
                    'currency' => $transactionData['currency'] ?? null,
                    'tx_ref' => $transactionData['tx_ref'] ?? $txRef,
                    'flw_ref' => $transactionData['flw_ref'] ?? null,
                    'data' => $transactionData,
                ];
            }

            Log::warning('Flutterwave tx_ref verification returned non-success', [
                'tx_ref' => $txRef,
                'response' => $responseData,
            ]);

            return [
                'success' => false,
                'verified' => false,
                'message' => $responseData['message'] ?? 'Transaction not found or not successful',
                'data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Flutterwave tx_ref verification error', [
                'tx_ref' => $txRef,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'verified' => false,
                'message' => 'Verification service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get public key for frontend
     * 
     * @return string
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}

