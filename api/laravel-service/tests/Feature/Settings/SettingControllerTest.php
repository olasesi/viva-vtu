<?php

use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function regularUser(): User
{
    return User::factory()->create(['role' => 'user']);
}

beforeEach(function () {
    app(SettingService::class)->update('email', [
        'mail_host' => 'smtp.gmail.com',
        'mail_port' => 587,
    ]);
});

it('requires authentication to view settings', function () {
    $this->getJson('/api/settings')->assertStatus(401);
});

it('requires authentication to update settings', function () {
    $this->postJson('/api/settings/email', [
        'fields' => ['mail_host' => 'smtp.gmail.com'],
    ])->assertStatus(401);
});

it('requires authentication to view the public settings', function () {
    $this->getJson('/api/settings/public')->assertOk();
});

it('lists all settings groups as an admin', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'api')
        ->getJson('/api/settings')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['group', 'label', 'icon', 'fields'],
            ],
        ])
        ->assertJsonCount(23, 'data');
});

it('forbids non-admin users from reading settings', function () {
    $user = regularUser();

    $this->actingAs($user, 'api')
        ->getJson('/api/settings')
        ->assertStatus(403);
});

it('forbids non-admin users from updating settings', function () {
    $user = regularUser();

    $this->actingAs($user, 'api')
        ->postJson('/api/settings/email', [
            'fields' => ['mail_host' => 'smtp.gmail.com'],
        ])
        ->assertStatus(403);
});

it('returns a specific group with merged defaults', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'api')
        ->getJson('/api/settings/email')
        ->assertOk()
        ->assertJsonPath('data.group', 'email')
        ->assertJsonPath('data.fields.mail_host', 'smtp.gmail.com')
        ->assertJsonPath('data.fields.mail_port', 587)
        ->assertJsonPath('data.fields.mail_driver', 'smtp');
});

it('masks sensitive fields when reading a group', function () {
    $admin = adminUser();

    app(SettingService::class)->set('email', 'mail_password', 'p@ssw0rd');

    $this->actingAs($admin, 'api')
        ->getJson('/api/settings/email')
        ->assertOk()
        ->assertJsonPath('data.fields.mail_password', '••••••••');
});

it('returns 404 for an unknown group', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'api')
        ->getJson('/api/settings/not-a-group')
        ->assertStatus(404);
});

it('exposes the field schema for a group', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'api')
        ->getJson('/api/settings/api/schema')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.group', 'api')
        ->assertJsonStructure([
            'data' => [
                'fields' => [
                    'aida_secret_key' => ['label', 'type', 'default', 'sensitive'],
                    'provider_mode' => ['label', 'type', 'default', 'options'],
                ],
            ],
        ])
        ->assertJsonPath('data.fields.aida_secret_key.sensitive', true);
});

it('only exposes whitelisted groups through the public endpoint', function () {
    $app = app()->make(SettingService::class);
    $app->set('api', 'aida_secret_key', 'super-secret');

    $response = $this->getJson('/api/settings/public')
        ->assertOk()
        ->assertJsonPath('success', true);

    $data = $response->json('data');

    expect(array_keys($data))->toContain('business')
        ->toContain('themes')
        ->toContain('support')
        ->not->toContain('api')
        ->not->toContain('email')
        ->not->toContain('security');
});

it('updates a group fields', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'api')
        ->postJson('/api/settings/email', [
            'fields' => [
                'mail_host' => 'smtp.mailgun.org',
                'mail_from_address' => 'hello@vivavtu.com',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.fields.mail_host', 'smtp.mailgun.org')
        ->assertJsonPath('data.fields.mail_driver', 'smtp');

    $this->assertDatabaseHas('settings', [
        'group' => 'email',
        'key' => 'mail_host',
    ]);
});

it('does not overwrite a sensitive value when the masked placeholder is submitted', function () {
    $admin = adminUser();
    $service = app(SettingService::class);

    $service->set('email', 'mail_password', 'p@ssw0rd');

    $this->actingAs($admin, 'api')
        ->postJson('/api/settings/email', [
            'fields' => [
                'mail_password' => '••••••••',
            ],
        ])
        ->assertOk();

    expect($service->get('email', 'mail_password'))->toBe('p@ssw0rd');

    $this->actingAs($admin, 'api')
        ->postJson('/api/settings/email', [
            'fields' => [
                'mail_password' => 'new-pass-123',
            ],
        ])
        ->assertOk();

    expect($service->get('email', 'mail_password'))->toBe('new-pass-123');
});

it('validates invalid field values on update', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'api')
        ->postJson('/api/settings/email', [
            'fields' => [
                'mail_port' => 'not-a-port',
                'mail_from_address' => 'not-an-email',
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fields.mail_port', 'fields.mail_from_address']);
});

it('rejects unknown fields within a group', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'api')
        ->postJson('/api/settings/email', [
            'fields' => [
                'mail_host' => 'smtp.gmail.com',
                'totally_unknown' => 'nope',
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fields.totally_unknown']);
});

it('enforces enum options on enum-typed fields', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'api')
        ->postJson('/api/settings/email', [
            'fields' => [
                'mail_encryption' => 'wrong-value',
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fields.mail_encryption']);

    $this->actingAs($admin, 'api')
        ->postJson('/api/settings/email', [
            'fields' => [
                'mail_encryption' => 'tls',
            ],
        ])
        ->assertStatus(200);
});

it('updates the display screen group', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'api')
        ->postJson('/api/settings/display_screen', [
            'fields' => [
                'enable_customer_display' => true,
                'heading_text' => 'Welcome to Viva VTU',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.fields.enable_customer_display', true)
        ->assertJsonPath('data.fields.heading_text', 'Welcome to Viva VTU');

    $this->assertDatabaseHas('settings', [
        'group' => 'display_screen',
        'key' => 'enable_customer_display',
        'value' => 'true',
    ]);
});

it('updates the VTU api group', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'api')
        ->postJson('/api/settings/api', [
            'fields' => [
                'provider_mode' => 'aida',
                'aida_base_url' => 'https://api.aidapay.com',
                'max_requests_per_second' => 8,
                'easy_access_enabled' => true,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.fields.provider_mode', 'aida')
        ->assertJsonPath('data.fields.max_requests_per_second', 8)
        ->assertJsonPath('data.fields.easy_access_enabled', true);

    expect(app(SettingService::class)->get('api', 'provider_mode'))->toBe('aida')
        ->and(app(SettingService::class)->get('api', 'max_requests_per_second'))->toBe(8)
        ->and(app(SettingService::class)->get('api', 'easy_access_enabled'))->toBeTrue();
});

it('updates the sms settings group', function () {
    $admin = adminUser();

    $this->actingAs($admin, 'api')
        ->postJson('/api/settings/sms', [
            'fields' => [
                'sms_service' => 'twilio',
                'url' => 'https://api.twilio.com/sms',
                'send_to_parameter' => 'To',
                'message_parameter' => 'Body',
                'request_method' => 'POST',
                'data_parameter_type' => 'form_data',
                'test_number' => '+2348012345678',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.fields.sms_service', 'twilio')
        ->assertJsonPath('data.fields.url', 'https://api.twilio.com/sms')
        ->assertJsonPath('data.fields.test_number', '+2348012345678');
});
