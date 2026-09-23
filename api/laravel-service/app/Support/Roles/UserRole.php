<?php

namespace App\Support\Roles;

class UserRole
{
    public static function all(): array
    {
        return config('roles.roles', ['user']);
    }

    public static function default(): string
    {
        $default = strtolower(trim((string) config('roles.default', 'user')));

        return in_array($default, static::all(), true) ? $default : 'user';
    }

    public static function isAdmin(?string $role): bool
    {
        return static::normalize($role) === 'admin';
    }

    public static function normalize(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        return in_array($role, static::all(), true) ? $role : static::default();
    }
}
