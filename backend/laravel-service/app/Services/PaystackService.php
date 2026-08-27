<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected Client $client;
    protected string $secretKey;
    protected string $publicKey;
    protected string $webhookSecret;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key', '');
        $this->publicKey = config('services.paystack.public_key', '');
        $this->webhookSecret = config('services.paystack.webhook_secret', '');

        $this->client = new Client([
            'base_uri' => config('services.paystack.base_url', 'https://api.paystack.co'),
            'timeout' => 30,
            'headers' => [
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function initializeTransaction(float $amount, string $email, array $metadata = []): ?array
    {
        $payLoad = [
            'amount' => (int) ($amount * 100),
            'email' => $email,
            'currency' => 'NGN',
            'metadata' => array_merge($metadata, [
                'cancel_action' => 'redirect',
            ]),
        ];

        try {
            $response = $this->client->post('/transaction/initialize', [
                'json' => $payLoad,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('Paystack transaction initialized', [
                'email' => $email,
                'amount' => $amount,
                'reference' => $body['data']['reference'] ?? null,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('Paystack transaction initialization failed', [
                'email' => $email,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return [
                'status' => false,
                'message' => 'Payment initialization failed',
            ];
        }
    }

    public function verifyTransaction(string $reference): ?array
    {
        try {
            $response = $this->client->get("/transaction/verify/{$reference}");

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('Paystack transaction verified', [
                'reference' => $reference,
                'status' => $body['data']['status'] ?? null,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('Paystack transaction verification failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function createRecipient(string $name, string $bankCode, string $accountNumber): ?array
    {
        $payLoad = [
            'type' => 'nuban',
            'name' => $name,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => 'NGN',
        ];

        try {
            $response = $this->client->post('/transferrecipient', [
                'json' => $payLoad,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('Paystack recipient created', [
                'name' => $name,
                'account_number' => $accountNumber,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('Paystack recipient creation failed', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function initiateTransfer(float $amount, string $recipientCode, string $reason = ''): ?array
    {
        $payLoad = [
            'source' => 'balance',
            'amount' => (int) ($amount * 100),
            'recipient' => $recipientCode,
            'reason' => $reason ?: 'Transfer',
            'currency' => 'NGN',
        ];

        try {
            $response = $this->client->post('/transfer', [
                'json' => $payLoad,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('Paystack transfer initiated', [
                'amount' => $amount,
                'recipient' => $recipientCode,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('Paystack transfer initiation failed', [
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

        $computedSignature = hash_hmac('sha512', json_encode($payload), $this->webhookSecret);

        return hash_equals($computedSignature, $signature);
    }

    public function getBanks(): ?array
    {
        try {
            $response = $this->client->get('/bank');
            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::error('Paystack banks fetch failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
