<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'group' => 'business',
            'key' => $this->faker->unique()->word(),
            'value' => '""',
        ];
    }

    public function forGroup(string $group): static
    {
        return $this->state(fn () => ['group' => $group]);
    }
}
