<?php

namespace App\Support\Phones;

class NigerianPhoneNumber
{
    public static function normalize(string $phone): ?string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        if (str_starts_with($cleaned, '234') && strlen($cleaned) === 13) {
            return '0'.substr($cleaned, 3);
        }

        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 11) {
            return $cleaned;
        }

        if (strlen($cleaned) === 10 && str_starts_with($cleaned, '7')) {
            return '0'.$cleaned;
        }

        if (strlen($cleaned) === 10 && str_starts_with($cleaned, '80')) {
            return '0'.$cleaned;
        }

        return null;
    }

    public static function isValid(string $phone): bool
    {
        $normalized = self::normalize($phone);

        return $normalized !== null
            && preg_match('/^0(70|80|81|90|91)\d{8}$/', $normalized) === 1;
    }

    public static function networkFor(string $phone): ?string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        $prefix = substr($normalized, 0, 4);

        foreach (config('phones.networks') as $network => $prefixes) {
            if (in_array($prefix, $prefixes, true)) {
                return $network;
            }
        }

        return null;
    }

    public static function validateForNetwork(string $phone, string $declaredNetwork): array
    {
        $declared = self::normalizeNetwork($declaredNetwork);
        $normalized = self::normalize($phone);

        if ($normalized === null || ! self::isValid($phone)) {
            return [
                'valid' => false,
                'normalized' => $normalized,
                'network' => null,
                'declared_network' => $declared,
                'network_match' => null,
                'reason' => 'Invalid phone number. Expected a valid Nigerian mobile number.',
            ];
        }

        $detected = self::networkFor($normalized);

        if ($detected === null) {
            return [
                'valid' => true,
                'normalized' => $normalized,
                'network' => null,
                'declared_network' => $declared,
                'network_match' => null,
                'reason' => 'Validation successful',
            ];
        }

        if ($declared !== null && $declared !== $detected) {
            return [
                'valid' => false,
                'normalized' => $normalized,
                'network' => $detected,
                'declared_network' => $declared,
                'network_match' => false,
                'reason' => sprintf(
                    'Number %s looks like a %s line but you selected %s.',
                    $normalized,
                    self::displayName($detected),
                    self::displayName($declared)
                ),
            ];
        }

        return [
            'valid' => true,
            'normalized' => $normalized,
            'network' => $detected,
            'declared_network' => $declared,
            'network_match' => $declared === null ? null : true,
            'reason' => 'Validation successful',
        ];
    }

    public static function normalizeNetwork(string $network): ?string
    {
        $network = strtolower(trim($network));

        if ($network === '') {
            return null;
        }

        return in_array($network, array_keys(config('phones.networks')), true) ? $network : null;
    }

    public static function displayName(string $network): string
    {
        return match (self::normalizeNetwork($network)) {
            'mtn' => 'MTN',
            'glo' => 'Glo',
            'airtel' => 'Airtel',
            '9mobile' => '9mobile',
            default => $network,
        };
    }
}
