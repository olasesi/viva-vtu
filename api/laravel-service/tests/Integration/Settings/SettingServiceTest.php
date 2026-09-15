<?php

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SettingService::class);
});

it('persists a key-value pair and reads it back', function () {
    $this->service->set('business', 'business_name', 'Viva VTU Lagos');

    expect(Setting::where('group', 'business')->where('key', 'business_name')->exists())->toBeTrue()
        ->and($this->service->get('business', 'business_name'))->toBe('Viva VTU Lagos');
});

it('updates an existing key instead of duplicating rows', function () {
    $this->service->set('email', 'mail_host', 'smtp.mailgun.org');
    $this->service->set('email', 'mail_host', 'smtp.gmail.com');

    expect(Setting::where('group', 'email')->where('key', 'mail_host')->count())->toBe(1)
        ->and($this->service->get('email', 'mail_host'))->toBe('smtp.gmail.com');
});

it('bulk updates a group and merges with defaults', function () {
    $this->service->update('email', [
        'mail_host' => 'smtp.gmail.com',
        'mail_port' => 587,
        'mail_username' => 'noreply@vivavtu.com',
    ]);

    $group = $this->service->all('email');

    expect($group['mail_host'])->toBe('smtp.gmail.com')
        ->and($group['mail_port'])->toBe(587)
        ->and($group['mail_username'])->toBe('noreply@vivavtu.com')
        ->and($group['mail_driver'])->toBe('smtp');
});

it('stores sensitive values encrypted at rest', function () {
    $this->service->set('email', 'mail_password', 'very-secret-pass');

    $stored = Setting::where('group', 'email')->where('key', 'mail_password')->first();

    expect($stored->value)->not->toBe('"very-secret-pass"')
        ->and($this->service->get('email', 'mail_password'))->toBe('very-secret-pass');
});

it('returns all group settings and a single group', function () {
    $this->service->update('business', ['business_name' => 'ACME Nigeria']);

    $all = $this->service->all();

    expect($all)->toHaveKey('business')
        ->and($all)->toHaveKey('sms');

    $business = $this->service->all('business');
    expect($business['business_name'])->toBe('ACME Nigeria');
});

it('fallback to default when a key is not stored', function () {
    expect($this->service->get('dashboard', 'default_datatable_page_entries'))->toBe(25);
});

it('throws an exception for unknown groups and keys', function () {
    expect(fn () => $this->service->set('nope', 'x', 'y'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $this->service->set('business', 'not_a_field', 'y'))
        ->toThrow(InvalidArgumentException::class);
});

it('masks sensitive values when exposing all settings', function () {
    $this->service->set('email', 'mail_password', 'super-secret');

    $exposed = $this->service->expose('email');

    expect($exposed['fields']['mail_password'])->toBe('••••••••');
});

it('round-trips an image path stored as an upload', function () {
    $this->service->set('display_screen', 'carousel_image_1', 'uploads/settings/carousel_1.jpg');

    expect($this->service->get('display_screen', 'carousel_image_1'))
        ->toBe('uploads/settings/carousel_1.jpg');
});

it('stores boolean and integer values in typed form', function () {
    $this->service->set('display_screen', 'enable_customer_display', true);
    $this->service->set('dashboard', 'default_datatable_page_entries', 50);

    expect(Setting::where('group', 'display_screen')->where('key', 'enable_customer_display')->first()->value)
        ->toBe('true')
        ->and($this->service->get('dashboard', 'default_datatable_page_entries'))->toBe(50);
});
