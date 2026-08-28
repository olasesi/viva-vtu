<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class FlutterwaveService
{
    protected Client $client;
    protected string $secretKey;
    protected string $publicKey;
    protected string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = config('services.flutterwave.secret_key', '');
        $this->publicKey = config('services.flutterwave.public_key', '');
        $this->webhookSecret = config('services.flutterwave.webhook_secret', '');

        $this->client = new Client([
            'base_uri' => config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3'),
            'timeout' => 30,
            'headers' => [
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function initializePayment(float $amount, string $currency, string $email, string $txRef, array $metadata = []): ?array
    {
        $payLoad = [
            'tx_ref' => $txRef,
            'amount' => (string) $amount,
            'currency' => $currency,
            'redirect_url' => $metadata['redirect_url'] ?? route('webhook.flutterwave.callback'),
            'customer' => [
                'email' => $email,
            ],
            'meta' => array_merge($metadata, [
                'consumer_id' => $metadata['user_id'] ?? null,
            ]),
            'customizations' => [
                'title' => 'VivaVTU Payment',
                'description' => 'Wallet Funding',
            ],
        ];

        try {
            $response = $this->client->post('/payments', [
                'json' => $payLoad,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('Flutterwave payment initialized', [
                'email' => $email,
                'amount' => $amount,
                'tx_ref' => $txRef,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('Flutterwave payment initialization failed', [
                'email' => $email,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return [
                'status' => 'error',
                'message' => 'Payment initialization failed',
            ];
        }
    }

    public function verifyTransaction(string $transactionId): ?array
    {
        try {
            $response = $this->client->get("/transactions/{$transactionId}/verify");

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('Flutterwave transaction verified', [
                'transaction_id' => $transactionId,
                'status' => $body['data']['status'] ?? null,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('Flutterwave transaction verification failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function initiateTransfer(float $amount, string $currency, string $bankCode, string $accountNumber, string $accountName): ?array
    {
        $payLoad = [
            'account_bank' => $bankCode,
            'account_number' => $accountNumber,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => 'FWT-' . strtoupper(uniqid()),
            'beneficiary_name' => $accountName,
            'narration' => 'Wallet withdrawal',
        ];

        try {
            $response = $this->client->post('/transfers', [
                'json' => $payLoad,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('Flutterwave transfer initiated', [
                'amount' => $amount,
                'account_number' => $accountNumber,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('Flutterwave transfer initiation failed', [
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function verifyWebhookSignature(array $payload, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }

        $hash = hash_hmac('sha256', json_encode($payload), $this->webhookSecret);

        return hash_equals($hash, $signature);
    }

    public function getBanks(string $country = 'NG'): ?array
    {
        try {
            $response = $this->client->get("/banks/{$country}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Flutterwave banks fetch failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
