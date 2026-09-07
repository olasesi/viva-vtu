<?php

namespace App\Services\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VtpassProvider implements ProviderContract
{
    protected Client $client;

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;

        $this->client = new Client([
            'base_uri' => $config['base_url'] ?? 'https://vtpass.com/api',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic '.base64_encode(
                    ($config['username'] ?? '').':'.($config['password'] ?? '')
                ),
            ],
        ]);
    }

    public function slug(): string
    {
        return 'vtpass';
    }

    public function name(): string
    {
        return 'VTPass';
    }

    public function isSuccessful(array $response): bool
    {
        return isset($response['code']) && $response['code'] === '000';
    }

    public function purchaseAirtime(array $params): array
    {
        $payload = [
            'serviceID' => $params['network'],
            'amount' => $params['amount'],
            'phone' => $params['phone_number'],
            'request_id' => $params['request_id'],
        ];

        return $this->send('/pay', $payload, 'airtime');
    }

    public function purchaseData(array $params): array
    {
        $payload = [
            'serviceID' => $params['network'],
            'billersCode' => $params['phone_number'],
            'variation_code' => $params['plan'],
            'amount' => $params['amount'],
            'phone' => $params['phone_number'],
            'request_id' => $params['request_id'],
        ];

        return $this->send('/pay', $payload, 'data');
    }

    public function purchaseElectricity(array $params): array
    {
        $payload = [
            'serviceID' => $params['disco'],
            'billersCode' => $params['meter_number'],
            'variation_code' => $params['meter_type'] === 'prepaid' ? 'prepaid' : 'postpaid',
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ];

        return $this->send('/pay', $payload, 'electricity');
    }

    public function purchaseCable(array $params): array
    {
        if (($params['action'] ?? null) === 'validate') {
            $verification = $this->verifyCustomer([
                'serviceID' => $params['cable'],
                'billersCode' => $params['smartcard_number'],
            ]);

            return $verification ?? ['code' => '999', 'response_message' => 'Service temporarily unavailable. Please try again.'];
        }

        $payload = [
            'serviceID' => $params['cable'],
            'billersCode' => $params['smartcard_number'],
            'variation_code' => $params['package'],
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ];

        return $this->send('/pay', $payload, 'cable');
    }

    public function verifyCustomer(array $params): ?array
    {
        return $this->get('/merchant-verify', $params, 'customer verification');
    }

    public function requery(string $requestId): ?array
    {
        return $this->get("/requery/{$requestId}", [], 'status check');
    }

    public function getServiceCategories(): ?array
    {
        return Cache::remember('vtpass_service_categories', 3600, function () {
            return $this->get('/service-categories', [], 'service categories');
        });
    }

    public function getServiceProducts(string $serviceId): ?array
    {
        $cacheKey = "vtpass_service_products_{$serviceId}";

        return Cache::remember($cacheKey, 3600, function () use ($serviceId) {
            return $this->get("/service-categories/{$serviceId}", [], 'service products');
        });
    }

    protected function send(string $endpoint, array $payload, string $label): array
    {
        try {
            $response = $this->client->post($endpoint, ['json' => $payload]);
            $body = json_decode($response->getBody()->getContents(), true);

            Log::info("VTPass {$label} purchase", [
                'payload' => $payload,
                'response' => $body,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error("VTPass {$label} purchase failed", [
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return [
                'code' => '999',
                'response_message' => 'Service temporarily unavailable. Please try again.',
            ];
        }
    }

    protected function get(string $endpoint, array $query, string $label): ?array
    {
        try {
            $response = $this->client->get($endpoint, ['query' => $query]);
            $body = json_decode($response->getBody()->getContents(), true);

            Log::info("VTPass {$label}", [
                'response_code' => $body['code'] ?? null,
                'response' => $body,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error("VTPass {$label} failed", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return ['code' => '999', 'response_message' => 'Service temporarily unavailable. Please try again.'];
        }
    }
}
