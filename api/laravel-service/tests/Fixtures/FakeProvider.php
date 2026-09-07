<?php

namespace Tests\Fixtures;

use App\Services\Providers\ProviderContract;

class FakeProvider implements ProviderContract
{
    public static array $calls = [];

    protected string $mode;

    public function __construct(array $config = [])
    {
        $this->mode = $config['fake_mode'] ?? 'success';
    }

    public function slug(): string
    {
        return 'fake';
    }

    public function name(): string
    {
        return 'Fake';
    }

    public function isSuccessful(array $response): bool
    {
        return ($response['code'] ?? null) === '000';
    }

    public function purchaseAirtime(array $params): array
    {
        return $this->record($params);
    }

    public function purchaseData(array $params): array
    {
        return $this->record($params);
    }

    public function purchaseElectricity(array $params): array
    {
        return $this->record($params);
    }

    public function purchaseCable(array $params): array
    {
        return $this->record($params);
    }

    public function verifyCustomer(array $params): ?array
    {
        return ['code' => '000', 'content' => ['Customer_Name' => 'JOHN DOE']];
    }

    public function requery(string $requestId): ?array
    {
        return $this->respond();
    }

    protected function record(array $params): array
    {
        static::$calls[] = $params;

        return $this->respond();
    }

    protected function respond(): array
    {
        return match ($this->mode) {
            'definitive' => ['code' => 'INVALID_AMOUNT', 'response_message' => 'Invalid amount for variant'],
            'ambiguous' => ['code' => '999', 'response_message' => 'Service temporarily unavailable'],
            default => [
                'code' => '000',
                'response_message' => 'SUCCESS',
                'content' => [
                    'transactions' => [
                        'transactionId' => 'TXN-FAKE-001',
                        'token' => '1234-5678-9012-3456',
                        'units' => 50,
                    ],
                ],
            ],
        };
    }
}
