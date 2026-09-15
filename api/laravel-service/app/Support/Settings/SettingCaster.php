<?php

namespace App\Support\Settings;

use InvalidArgumentException;

class SettingCaster
{
    public static function toDb(string $type, mixed $value): string
    {
        return match ($type) {
            'string' => json_encode((string) ($value ?? '')),
            'boolean' => $value ? 'true' : 'false',
            'integer' => (string) (int) $value,
            'decimal' => (string) (float) $value,
            'enum' => json_encode((string) ($value ?? '')),
            'array' => json_encode($value ?? []),
            default => throw new InvalidArgumentException("Unsupported setting type [{$type}]"),
        };
    }

    public static function fromDb(string $type, ?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'boolean' => $value === 'true',
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'string', 'enum' => json_decode($value, true),
            'array' => json_decode($value, true),
            default => throw new InvalidArgumentException("Unsupported setting type [{$type}]"),
        };
    }

    public static function mask(string $value): string
    {
        return $value === '' ? '' : '••••••••';
    }
}
