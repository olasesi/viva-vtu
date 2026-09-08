<?php

namespace App\Services\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * EasyAccess adapter (best-effort).
 *
 * EasyAccess's public API spec is rendered client-side on their docs page and
 * is not publicly documented, so endpoint paths are fully config-driven under
 * aggregators.providers.easyaccess.endpoints. Responses are normalised from
 * both `{ success, data }` and `{ code, content }` envelopes. Keep this
 * provider disabled until verified against their live API.
 */
class EasyAccessProvider
{
    protected Client $client;

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;

        $this->client = new Client([
            'base_uri' => $config['base_url'] ?? 'https://easyaccessapi.com.ng',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.($config['api_token'] ?? ''),
            ],
        ]);
    }

    public function slug(): string
    {
        return 'easyaccess';
    }

    public function name(): string
    {
        return 'EasyAccess';
    }

    public function isSuccessful(array $response): bool
    {
        return ($response['code'] ?? null) === '000';
    }

    public function purchaseAirtime(array $params): array
    {
        return $this->send('airtime', [
            'network' => $params['network'],
            'phone_number' => $params['phone_number'],
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ]);
    }

    public function purchaseData(array $params): array
    {
        return $this->send('data', [
            'network' => $params['network'],
            'phone_number' => $params['phone_number'],
            'plan' => $params['plan'],
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ]);
    }

    public function purchaseElectricity(array $params): array
    {
        return $this->send('electricity', [
            'disco' => $params['disco'],
            'meter_number' => $params['meter_number'],
            'meter_type' => $params['meter_type'] ?? 'prepaid',
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ]);
    }

    public function purchaseCable(array $params): array
    {
        if (($params['action'] ?? null) === 'validate') {
            return $this->verifyCustomer([
                'serviceID' => $params['cable'],
                'billersCode' => $params['smartcard_number'],
            ]) ?? ['code' => '999', 'response_message' => 'Service temporarily unavailable. Please try again.'];
        }

        return $this->send('cable', [
            'cable' => $params['cable'],
            'smartcard_number' => $params['smartcard_number'],
            'package' => $params['package'],
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ]);
    }

    public function purchaseExamPins(array $params): array
    {
        return $this->send('exam', [
            'exam_type' => $params['exam_type'],
            'phone_number' => $params['recipient'] ?? $params['phone_number'] ?? '',
            'quantity' => $params['quantity'] ?? 1,
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ]);
    }

    public function purchaseStreaming(array $params): array
    {
        return $this->send('streaming', [
            'platform' => $params['platform'],
            'plan' => $params['plan'] ?? null,
            'recipient' => $params['recipient'] ?? $params['phone_number'] ?? '',
            'amount' => $params['amount'] ?? null,
            'request_id' => $params['request_id'],
        ]);
    }

    public function verifyCustomer(array $params): ?array
    {
        return $this->get('verify', $params, 'customer verification');
    }

    public function requery(string $requestId): ?array
    {
        return $this->get('requery', ['request_id' => $requestId], 'status check');
    }

    protected function send(string $endpoint, array $payload): array
    {
        try {
            $response = $this->client->post($this->endpoint($endpoint), ['json' => $payload]);
            $body = json_decode($response->getBody()->getContents(), true);

            Log::info("EasyAccess {$endpoint} purchase", [
                'payload' => $payload,
                'response' => $body,
            ]);

            return $this->normalize($body);
        } catch (GuzzleException $e) {
            Log::error("EasyAccess {$endpoint} purchase failed", [
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return ['code' => '999', 'response_message' => 'Service temporarily unavailable. Please try again.'];
        }
    }

    protected function get(string $endpoint, array $query, string $label): ?array
    {
        try {
            $response = $this->client->get($this->endpoint($endpoint), ['query' => $query]);
            $body = json_decode($response->getBody()->getContents(), true);

            Log::info("EasyAccess {$label}", [
                'response_code' => $body['code'] ?? null,
                'response' => $body,
            ]);

            return $this->normalize($body);
        } catch (GuzzleException $e) {
            Log::error("EasyAccess {$label} failed", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return ['code' => '999', 'response_message' => 'Service temporarily unavailable. Please try again.'];
        }
    }

    protected function endpoint(string $key): string
    {
        return (string) ($this->config['endpoints'][$key] ?? '/api/'.$key);
    }

    protected function normalize(array $response): array
    {
        $code = $response['code'] ?? null;

        if (($response['success'] ?? false) === true || $code === '000') {
            $status = strtolower((string) ($response['status'] ?? $response['content']['status'] ?? ''));

            if (in_array($status, ['pending', 'processing', 'submitted'], true)) {
                return ['code' => '999', 'response_message' => 'Transaction sent for processing', 'content' => $response['content'] ?? $response['data'] ?? $response];
            }

            return ['code' => '000', 'response_message' => 'SUCCESS', 'content' => $response['content'] ?? $response['data'] ?? []];
        }

        if ($code !== null && $code !== '000') {
            return ['code' => $code, 'response_message' => $response['response_message'] ?? $response['message'] ?? 'Provider error', 'content' => $response];
        }

        return ['code' => '999', 'response_message' => $response['message'] ?? 'Transaction sent for processing', 'content' => $response['data'] ?? $response];
    }
}
