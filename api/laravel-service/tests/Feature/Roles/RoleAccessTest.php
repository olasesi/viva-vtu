<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('treats an uppercase admin role as administrative', function () {
    $admin = User::factory()->create(['role' => 'ADMIN']);

    $this->actingAs($admin, 'api')
        ->getJson('/api/settings')
        ->assertOk();
});

it('treats a mixed-case admin role as administrative', function () {
    $admin = User::factory()->create(['role' => 'Admin']);

    $this->actingAs($admin, 'api')
        ->getJson('/api/settings')
        ->assertOk();
});

it('forbids resellers from administrative endpoints regardless of casing', function () {
    $reseller = User::factory()->create(['role' => 'RESELLER']);

    $this->actingAs($reseller, 'api')
        ->getJson('/api/settings')
        ->assertForbidden();
});

it('forbids agents from administrative endpoints', function () {
    $agent = User::factory()->create(['role' => 'agent']);

    $this->actingAs($agent, 'api')
        ->getJson('/api/settings')
        ->assertForbidden();
});

it('forbids merchants from administrative endpoints despite mixed casing', function () {
    $merchant = User::factory()->create(['role' => 'Merchant']);

    $this->actingAs($merchant, 'api')
        ->postJson('/api/settings/email', ['mail_host' => 'smtp.example.com'])
        ->assertForbidden();
});
