<?php

namespace App\Services\Providers;

interface ProviderContract
{
    public function slug(): string;

    public function name(): string;

    /**
     * Purchase methods never throw at the router boundary: a network/unknown
     * failure is reported as code '999', a definitive provider rejection is
     * any other non-'000' code.
     */
    public function purchaseAirtime(array $params): array;

    public function purchaseData(array $params): array;

    public function purchaseElectricity(array $params): array;

    public function purchaseCable(array $params): array;

    /**
     * Verify a customer/biller before debiting (meter, smartcard, phone).
     */
    public function verifyCustomer(array $params): ?array;

    /**
     * Requery a previously submitted order by its request_id.
     */
    public function requery(string $requestId): ?array;

    /**
     * True when the provider response is a definitive success.
     */
    public function isSuccessful(array $response): bool;
}
