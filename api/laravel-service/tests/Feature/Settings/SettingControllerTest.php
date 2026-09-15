<?php

use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

it('lists all settings groups as an authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/settings')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['group', 'label', 'icon', 'fields'],
            ],
        ])
        ->assertJsonCount(17, 'data');
});

it('returns a specific group with merged defaults', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/settings/email')
        ->assertStatus(200)
        ->assertOk()
        ->assertJsonPath('data.group', 'email')
        ->assertJsonPath('data.fields.mail_host', 'smtp.gmail.com')
        ->assertJsonPath('data.fields.mail_port', 587)
        ->assertJsonPath('data.fields.mail_driver', 'smtp');
});

it('masks sensitive fields when reading a group', function () {
    $user = User::factory()->create();

    app(SettingService::class)->set('email', 'mail_password', 'p@ssw0rd');

    $this->actingAs($user, 'api')
        ->getJson('/api/settings/email')
        ->assertOk()
        ->assertJsonPath('data.fields.mail_password', '••••••••');
});

it('returns 404 for an unknown group', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->getJson('/api/settings/not-a-group')
        ->assertStatus(404);
});

it('updates a group fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/settings/email', [
            'fields' => [
                'mail_host' => 'smtp.mailgun.org',
                'mail_from_address' => 'hello@vivavtu.com',
            ],
        ])
        ->assertStatus(200)
        ->assertOk()
        ->assertJsonPath('data.fields.mail_host', 'smtp.mailgun.org')
        ->assertJsonPath('data.fields.mail_driver', 'smtp');

    $this->assertDatabaseHas('settings', [
        'group' => 'email',
        'key' => 'mail_host',
    ]);
});

it('validates invalid field values on update', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
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
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
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
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/settings/email', [
            'fields' => [
                'mail_encryption' => 'wrong-value',
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fields.mail_encryption']);

    $this->actingAs($user, 'api')
        ->postJson('/api/settings/email', [
            'fields' => [
                'mail_encryption' => 'tls',
            ],
        ])
        ->assertStatus(200);
});

it('updates the display screen group', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/settings/display_screen', [
            'fields' => [
                'enable_customer_display' => true,
                'heading_text' => 'Welcome to Viva VTU',
            ],
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.fields.enable_customer_display', true)
        ->assertJsonPath('data.fields.heading_text', 'Welcome to Viva VTU');

    $this->assertDatabaseHas('settings', [
        'group' => 'display_screen',
        'key' => 'enable_customer_display',
        'value' => 'true',
    ]);
});

it('updates the sms settings group', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
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
        ->assertStatus(200)
        ->assertJsonPath('data.fields.sms_service', 'twilio')
        ->assertJsonPath('data.fields.url', 'https://api.twilio.com/sms')
        ->assertJsonPath('data.fields.test_number', '+2348012345678');
});
