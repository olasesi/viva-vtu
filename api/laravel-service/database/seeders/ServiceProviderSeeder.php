<?php

namespace Database\Seeders;

use App\Models\ServiceProvider;
use Illuminate\Database\Seeder;

class ServiceProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'name' => 'VTPass',
                'slug' => 'vtpass',
                'base_url' => 'https://vtpass.com/api',
                'api_key' => env('VTPASS_API_KEY', ''),
                'auth_string' => null,
                'is_active' => true,
                'config' => json_encode([
                    'timeout' => 30,
                    'retry' => 3,
                ]),
            ],
            [
                'name' => 'Paystack',
                'slug' => 'paystack',
                'base_url' => 'https://api.paystack.co',
                'api_key' => env('PAYSTACK_SECRET_KEY', ''),
                'auth_string' => null,
                'is_active' => true,
                'config' => json_encode([
                    'currency' => 'NGN',
                ]),
            ],
            [
                'name' => 'Flutterwave',
                'slug' => 'flutterwave',
                'base_url' => 'https://api.flutterwave.com/v3',
                'api_key' => env('FLUTTERWAVE_SECRET_KEY', ''),
                'auth_string' => null,
                'is_active' => true,
                'config' => json_encode([
                    'currency' => 'NGN',
                ]),
            ],
        ];

        foreach ($providers as $provider) {
            ServiceProvider::updateOrCreate(
                ['slug' => $provider['slug']],
                $provider
            );
        }
    }
}
