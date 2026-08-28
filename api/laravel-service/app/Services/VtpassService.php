<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VtpassService
{
    protected Client $client;
    protected string $baseUrl;
    protected string $apiKey;
    protected string $secretKey;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->baseUrl = config('vtpass.base_url', 'https://vtpass.com/api');
        $this->apiKey = config('vtpass.api_key', '');
        $this->secretKey = config('vtpass.secret_key', '');
        $this->username = config('vtpass.username', '');
        $this->password = config('vtpass.password', '');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 30,
            'retry' => 3,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("{$this->username}:{$this->password}"),
            ],
        ]);
    }

    public function verifyCustomer(array $params): ?array
    {
        try {
            $response = $this->client->post('/merchant-verify', [
                'json' => $params,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('VTPass customer verification', [
                'params' => $params,
                'response_code' => $body['code'] ?? null,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('VTPass customer verification failed', [
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function purchaseAirtime(array $params): ?array
    {
        $payLoad = [
            'serviceID' => $params['network'],
            'amount' => $params['amount'],
            'phone' => $params['phone_number'],
            'request_id' => $params['request_id'],
        ];

        try {
            $response = $this->client->post('/pay', [
                'json' => $payLoad,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('VTPass airtime purchase', [
                'payload' => $payLoad,
                'response' => $body,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('VTPass airtime purchase failed', [
                'payload' => $payLoad,
                'error' => $e->getMessage(),
            ]);
            return [
                'code' => '999',
                'response_message' => 'Service temporarily unavailable. Please try again.',
            ];
        }
    }

    public function purchaseData(array $params): ?array
    {
        $payLoad = [
            'serviceID' => $params['network'],
            'billersCode' => $params['phone_number'],
            'variation_code' => $params['plan'],
            'amount' => $params['amount'],
            'phone' => $params['phone_number'],
            'request_id' => $params['request_id'],
        ];

        try {
            $response = $this->client->post('/pay', [
                'json' => $payLoad,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('VTPass data purchase', [
                'payload' => $payLoad,
                'response' => $body,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('VTPass data purchase failed', [
                'payload' => $payLoad,
                'error' => $e->getMessage(),
            ]);
            return [
                'code' => '999',
                'response_message' => 'Service temporarily unavailable. Please try again.',
            ];
        }
    }

    public function purchaseElectricity(array $params): ?array
    {
        $payLoad = [
            'serviceID' => $params['disco'],
            'billersCode' => $params['meter_number'],
            'variation_code' => $params['meter_type'] === 'prepaid' ? 'prepaid' : 'postpaid',
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ];

        try {
            $response = $this->client->post('/pay', [
                'json' => $payLoad,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('VTPass electricity purchase', [
                'payload' => $payLoad,
                'response' => $body,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('VTPass electricity purchase failed', [
                'payload' => $payLoad,
                'error' => $e->getMessage(),
            ]);
            return [
                'code' => '999',
                'response_message' => 'Service temporarily unavailable. Please try again.',
            ];
        }
    }

    public function purchaseCable(array $params): ?array
    {
        $payLoad = [
            'serviceID' => $params['cable'],
            'billersCode' => $params['smartcard_number'],
            'variation_code' => $params['package'],
            'amount' => $params['amount'],
            'request_id' => $params['request_id'],
        ];

        if ($params['action'] === 'validate') {
            return $this->verifyCustomer([
                'serviceID' => $params['cable'],
                'billersCode' => $params['smartcard_number'],
            ]);
        }

        try {
            $response = $this->client->post('/pay', [
                'json' => $payLoad,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('VTPass cable purchase', [
                'payload' => $payLoad,
                'response' => $body,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('VTPass cable purchase failed', [
                'payload' => $payLoad,
                'error' => $e->getMessage(),
            ]);
            return [
                'code' => '999',
                'response_message' => 'Service temporarily unavailable. Please try again.',
            ];
        }
    }

    public function checkStatus(string $requestId): ?array
    {
        try {
            $response = $this->client->get("/requery/{$requestId}");

            $body = json_decode($response->getBody()->getContents(), true);

            Log::info('VTPass status check', [
                'request_id' => $requestId,
                'response' => $body,
            ]);

            return $body;
        } catch (GuzzleException $e) {
            Log::error('VTPass status check failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getServiceCategories(): ?array
    {
        $cacheKey = 'vtpass_service_categories';

        return Cache::remember($cacheKey, 3600, function () {
            try {
                $response = $this->client->get('/service-categories');
                $body = json_decode($response->getBody()->getContents(), true);

                Log::info('VTPass service categories fetched');

                return $body;
            } catch (GuzzleException $e) {
                Log::error('VTPass service categories failed', [
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    public function getServiceProducts(string $serviceId): ?array
    {
        $cacheKey = "vtpass_service_products_{$serviceId}";

        return Cache::remember($cacheKey, 3600, function () use ($serviceId) {
            try {
                $response = $this->client->get("/service-categories/{$serviceId}");
                $body = json_decode($response->getBody()->getContents(), true);

                Log::info('VTPass service products fetched', [
                    'service_id' => $serviceId,
                ]);

                return $body;
            } catch (GuzzleException $e) {
                Log::error('VTPass service products failed', [
                    'service_id' => $serviceId,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }
}
