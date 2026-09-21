<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Settings\SettingCaster;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

class SettingService
{
    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('settings.groups', []);
    }

    public function groups(): array
    {
        return array_keys($this->config);
    }

    public function hasGroup(string $group): bool
    {
        return array_key_exists($group, $this->config);
    }

    public function fields(string $group): array
    {
        if (! $this->hasGroup($group)) {
            throw new InvalidArgumentException("Settings group [{$group}] does not exist.");
        }

        return $this->config[$group]['fields'] ?? [];
    }

    public function definition(string $group, string $key): ?array
    {
        $fields = $this->fields($group);

        return $fields[$key] ?? null;
    }

    public function default(string $group, string $key): mixed
    {
        if (! $this->hasField($group, $key)) {
            throw new InvalidArgumentException("Setting key [{$key}] does not exist in group [{$group}].");
        }

        return $this->fields($group)[$key]['default'] ?? null;
    }

    public function rules(string $group): array
    {
        $rules = [];

        foreach ($this->fields($group) as $key => $field) {
            $rules[$key] = $field['rules'] ?? '';
        }

        return $rules;
    }

    public function sensitiveFields(string $group): array
    {
        return collect($this->fields($group))
            ->filter(fn ($field) => ($field['sensitive'] ?? false) === true)
            ->keys()
            ->all();
    }

    public function defaultsMergedWithStored(string $group): array
    {
        $fields = $this->fields($group);

        $merged = [];

        foreach ($fields as $key => $field) {
            $stored = Setting::group($group)->forKey($key)->first();

            $merged[$key] = $stored
                ? SettingCaster::fromDb($field['type'], $this->decryptIfNeeded($group, $key, $stored->value))
                : ($field['default'] ?? null);
        }

        return $merged;
    }

    public function all(?string $group = null): array
    {
        if ($group !== null) {
            return $this->defaultsMergedWithStored($group);
        }

        $all = [];

        foreach ($this->groups() as $slug) {
            $all[$slug] = $this->defaultsMergedWithStored($slug);
        }

        return $all;
    }

    public function get(string $group, string $key): mixed
    {
        if (! $this->hasField($group, $key)) {
            throw new InvalidArgumentException("Setting key [{$key}] does not exist in group [{$group}].");
        }

        $stored = Setting::group($group)->forKey($key)->first();

        if (! $stored) {
            return $this->fields($group)[$key]['default'] ?? null;
        }

        $decoded = $this->decryptIfNeeded($group, $key, $stored->value);

        return SettingCaster::fromDb($this->fields($group)[$key]['type'], $decoded);
    }

    public function set(string $group, string $key, mixed $value): void
    {
        if (! $this->hasField($group, $key)) {
            throw new InvalidArgumentException("Setting key [{$key}] does not exist in group [{$group}].");
        }

        $field = $this->fields($group)[$key];

        $storedValue = SettingCaster::toDb($field['type'], $value);

        if (($field['sensitive'] ?? false) === true) {
            $storedValue = Crypt::encryptString($storedValue);
        }

        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $storedValue],
        );
    }

    public function update(string $group, array $values): void
    {
        if (! $this->hasGroup($group)) {
            throw new InvalidArgumentException("Settings group [{$group}] does not exist.");
        }

        foreach ($values as $key => $value) {
            $this->set($group, $key, $value);
        }
    }

    public function expose(string $group): array
    {
        if (! $this->hasGroup($group)) {
            throw new InvalidArgumentException("Settings group [{$group}] does not exist.");
        }

        $merged = $this->defaultsMergedWithStored($group);
        $fields = $this->fields($group);

        $exposed = [];

        foreach ($merged as $key => $value) {
            $isSensitive = ($fields[$key]['sensitive'] ?? false) === true;

            $exposed[$key] = $isSensitive && $value !== null && $value !== ''
                ? SettingCaster::mask((string) $value)
                : $value;
        }

        return [
            'group' => $group,
            'label' => $this->config[$group]['label'] ?? $group,
            'icon' => $this->config[$group]['icon'] ?? null,
            'fields' => $exposed,
        ];
    }

    protected function hasField(string $group, string $key): bool
    {
        if (! $this->hasGroup($group)) {
            return false;
        }

        return array_key_exists($key, $this->fields($group));
    }

    protected function decryptIfNeeded(string $group, string $key, string $value): string
    {
        $isSensitive = ($this->definition($group, $key)['sensitive'] ?? false) === true;

        if (! $isSensitive) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function schema(string $group): array
    {
        if (! $this->hasGroup($group)) {
            throw new InvalidArgumentException("Settings group [{$group}] does not exist.");
        }

        $available = ['label', 'type', 'default', 'options', 'sensitive'];

        $fields = [];

        foreach ($this->fields($group) as $key => $definition) {
            $fields[$key] = collect($definition)
                ->only($available)
                ->all();
        }

        return [
            'group' => $group,
            'label' => $this->config[$group]['label'] ?? $group,
            'icon' => $this->config[$group]['icon'] ?? null,
            'fields' => $fields,
        ];
    }

    public function publicGroups(): array
    {
        return (array) config('settings.public_groups', []);
    }

    public function publicSettings(): array
    {
        $settings = [];

        foreach ($this->publicGroups() as $group) {
            if ($this->hasGroup($group)) {
                $settings[$group] = $this->expose($group);
            }
        }

        return $settings;
    }
}
