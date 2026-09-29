<?php

namespace App\Services\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class RechargeProvider implements ProviderContract
{
    protected Client $client;

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;

        $options = [
            'base_uri' => rtrim($config['base_url'] ?? 'https://nigeria.recharge.com.ng/api', '/').'/',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Token '.($config['api_token'] ?? ''),
            ],
        ];

        if (isset($config['handler'])) {
            $options['handler'] = $config['handler'];
        }

        $this->client = new Client($options);
    }

    public function slug(): string
    {
        return 'recharge';
    }

    public function name(): string
    {
        return 'Recharge.com.ng';
    }

    public function isSuccessful(array $response): bool
    {
        $codes = $this->config['success_codes'] ?? ['000', '0', '200'];
        $code = $response['code'] ?? $response['status'] ?? null;

        if ($code === null) {
            return false;
        }

        return in_array((string) $code, array_map('strval', $codes), true);
    }

    public function purchaseAirtime(array $params): array
    {
        return $this->send($this->endpoint('airtime'), [
            'network' => $params['network'],
            'amount' => $params['amount'],
            'phone' => $params['phone_number'],
            'request_id' => $params['request_id'],
        ], 'airtime');
    }

    public function purchaseData(array $params): array
    {
        return $this->send($this->endpoint('data'), [
            'network' => $params['network'],
            'plan' => $params['plan'],
            'amount' => $params['amount'],
            'phone' => $params['phone_number'],
            'request_id' => $params['request_id'],
        ], 'data');
    }

    public function purchaseElectricity(array $params): array
    {
        return $this->send($this->endpoint('electricity'), [
            'disco' => $params['disco'],
            'meter_number' => $params['meter_number'],
            'meter_type' => $params['meter_type'] === 'prepaid' ? 'prepaid' : 'postpaid',
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ], 'electricity');
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

        return $this->send($this->endpoint('cable'), [
            'cable' => $params['cable'],
            'smartcard_number' => $params['smartcard_number'],
            'package' => $params['package'],
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ], 'cable');
    }

    public function purchaseExamPins(array $params): array
    {
        return $this->send($this->endpoint('exam'), [
            'exam_type' => $params['exam_type'],
            'quantity' => $params['quantity'] ?? 1,
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ], 'exam pins');
    }

    public function purchaseStreaming(array $params): array
    {
        return $this->send($this->endpoint('streaming'), [
            'platform' => $params['platform'],
            'plan' => $params['plan'],
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ], 'streaming');
    }

    public function verifyCustomer(array $params): ?array
    {
        return $this->send($this->endpoint('verify'), $params, 'customer verification');
    }

    public function requery(string $requestId): ?array
    {
        return $this->get($this->endpoint('requery'), ['request_id' => $requestId], 'status check');
    }

    protected function endpoint(string $key): string
    {
        $path = $this->config['endpoints'][$key] ?? ('/'.$key);

        return ltrim($path, '/');
    }

    protected function send(string $endpoint, array $payload, string $label): array
    {
        try {
            $response = $this->client->post($endpoint, ['json' => $payload]);
            $body = json_decode($response->getBody()->getContents(), true);

            Log::info("Recharge {$label} purchase", [
                'payload' => $payload,
                'response' => $body,
            ]);

            return is_array($body) ? $body : ['code' => '999', 'response_message' => 'Invalid provider response'];
        } catch (GuzzleException $e) {
            Log::error("Recharge {$label} purchase failed", [
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

            Log::info("Recharge {$label}", [
                'response_code' => $body['code'] ?? null,
                'response' => $body,
            ]);

            return is_array($body) ? $body : null;
        } catch (GuzzleException $e) {
            Log::error("Recharge {$label} failed", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return ['code' => '999', 'response_message' => 'Service temporarily unavailable. Please try again.'];
        }
    }
}
