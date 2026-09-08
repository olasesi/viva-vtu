<?php

namespace App\Services\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * AidaPay reseller API adapter.
 *
 * API reference: https://www.aidapay.ng/api/v1
 *  - POST /buy       => { recipient, provider_code, account_pin, ref, amount?, package_code?, isPorted? }
 *  - GET  /transaction/{hash|ref}  => status: Completed | Processing | Pending | Refund | Cancelled
 *  - GET  /validation/{provider_code}/{recipient}
 *  - GET  /service/{slug}, /packages/{provider_code}, /my_account, /pricing
 *
 * AidaPay acknowledges buys as "Processing" (never delivery-confirmed), so a
 * purchase response is reported as ambiguous (code '999') and resolved later
 * via requery or webhook to avoid double delivery on failover.
 */
class AidaPayProvider
{
    use NormalizesProviderCodes;

    protected Client $client;

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;

        $this->client = new Client([
            'base_uri' => $config['base_url'] ?? 'https://www.aidapay.ng/api/v1',
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
        return 'aidapay';
    }

    public function name(): string
    {
        return 'AidaPay';
    }

    public function isSuccessful(array $response): bool
    {
        return ($response['code'] ?? null) === '000';
    }

    public function purchaseAirtime(array $params): array
    {
        return $this->buy([
            'recipient' => $params['phone_number'],
            'provider_code' => $this->code('airtime', $params['network']),
            'amount' => $params['amount'],
            'ref' => $params['request_id'],
        ]);
    }

    public function purchaseData(array $params): array
    {
        return $this->buy([
            'recipient' => $params['phone_number'],
            'provider_code' => $this->code('data', $params['network']),
            'amount' => $params['amount'],
            'package_code' => $params['plan'] ?? null,
            'ref' => $params['request_id'],
        ]);
    }

    public function purchaseElectricity(array $params): array
    {
        return $this->buy([
            'recipient' => $params['meter_number'],
            'provider_code' => $this->code('electricity', $params['disco']),
            'amount' => $params['amount'],
            'ref' => $params['request_id'],
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

        return $this->buy([
            'recipient' => $params['smartcard_number'],
            'provider_code' => $this->code('cable', $params['cable']),
            'amount' => $params['amount'],
            'package_code' => $params['package'] ?? null,
            'ref' => $params['request_id'],
        ]);
    }

    public function purchaseExamPins(array $params): array
    {
        return $this->buy([
            'recipient' => $params['recipient'] ?? $params['phone_number'] ?? '',
            'provider_code' => $this->code('education', $params['exam_type']),
            'amount' => $params['amount'],
            'package_code' => $params['variation_code'] ?? $params['package_code'] ?? null,
            'ref' => $params['request_id'],
        ]);
    }

    public function purchaseStreaming(array $params): array
    {
        return $this->buy([
            'recipient' => $params['recipient'] ?? $params['phone_number'] ?? '',
            'provider_code' => $this->code('streaming', $params['platform']),
            'amount' => $params['amount'] ?? null,
            'package_code' => $params['plan'] ?? null,
            'ref' => $params['request_id'],
        ]);
    }

    public function verifyCustomer(array $params): ?array
    {
        $providerCode = $params['provider_code']
            ?? $params['serviceID']
            ?? $params['cable']
            ?? $params['disco']
            ?? null;

        $recipient = $params['recipient']
            ?? $params['billersCode']
            ?? $params['smartcard_number']
            ?? $params['meter_number']
            ?? null;

        if (! $providerCode || ! $recipient) {
            return ['code' => 'REJECTED', 'response_message' => 'Missing recipient details for verification'];
        }

        try {
            $response = $this->client->get('/validation/'.rawurlencode($providerCode).'/'.rawurlencode($recipient));
            $body = json_decode($response->getBody()->getContents(), true);

            if (($body['success'] ?? false) === true && ($body['data']['verified'] ?? false)) {
                return ['code' => '000', 'response_message' => 'Verified', 'content' => $body['data'] ?? []];
            }

            return [
                'code' => 'REJECTED',
                'response_message' => $body['data']['message'] ?? $body['message'] ?? 'Customer verification failed',
                'content' => $body['data'] ?? [],
            ];
        } catch (GuzzleException $e) {
            Log::error('AidaPay verification failed', ['error' => $e->getMessage()]);

            return ['code' => '999', 'response_message' => 'Service temporarily unavailable. Please try again.'];
        }
    }

    public function requery(string $requestId): ?array
    {
        try {
            $response = $this->client->get('/transaction/'.rawurlencode($requestId));
            $body = json_decode($response->getBody()->getContents(), true);

            if (($body['success'] ?? false) !== true) {
                return ['code' => '999', 'response_message' => $body['message'] ?? 'Transaction not found'];
            }

            $data = $body['data'] ?? [];
            $status = $data['status'] ?? 'Processing';
            $message = isset($data['status']) && $status ? $status : 'Transaction '.$requestId;

            return match (strtolower((string) $status)) {
                'completed' => ['code' => '000', 'response_message' => $message, 'content' => $data],
                'refund', 'cancelled' => ['code' => 'REFUNDED', 'response_message' => $status.' - transaction was not delivered', 'content' => $data],
                default => ['code' => '999', 'response_message' => $status, 'content' => $data],
            };
        } catch (GuzzleException $e) {
            Log::error('AidaPay requery failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);

            return ['code' => '999', 'response_message' => 'Service temporarily unavailable. Please try again.'];
        }
    }

    protected function buy(array $fields): array
    {
        $payload = [
            'recipient' => $fields['recipient'],
            'provider_code' => $fields['provider_code'],
            'account_pin' => (string) ($this->config['account_pin'] ?? ''),
            'ref' => $fields['ref'],
        ];

        if (isset($fields['amount']) && $fields['amount'] !== null) {
            $payload['amount'] = (string) $fields['amount'];
        }

        if (! empty($fields['package_code'])) {
            $payload['package_code'] = $fields['package_code'];
        }

        try {
            $response = $this->client->post('/buy', ['json' => $payload]);
            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('AidaPay purchase', [
                'payload' => $payload,
                'response' => $body,
            ]);

            if (($body['success'] ?? false) === true) {
                $data = $body['data']['transaction_data'] ?? $body['data'] ?? [];

                return [
                    'code' => '999',
                    'response_message' => $body['data']['message'] ?? 'Transaction sent for processing',
                    'content' => $data,
                ];
            }

            return [
                'code' => 'REJECTED',
                'response_message' => $body['message'] ?? 'Provider rejected transaction',
                'content' => $body,
            ];
        } catch (GuzzleException $e) {
            Log::error('AidaPay purchase failed', [
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return [
                'code' => '999',
                'response_message' => 'Service temporarily unavailable. Please try again.',
            ];
        }
    }
}
