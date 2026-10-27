<?php

use App\Models\RecurringPurchase;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\TransactionUpdate;
use App\Services\RecurringPurchaseService;
use App\Services\TransactionPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\FakeProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    FakeProvider::$calls = [];
});

function routeRecurringPurchasesThroughFakeProvider(string $mode = 'success'): void
{
    config([
        'aggregators.providers.fake' => [
            'class' => FakeProvider::class,
            'label' => 'Fake',
            'enabled' => true,
            'fake_mode' => $mode,
        ],
        'aggregators.routing' => array_fill_keys(['airtime', 'data', 'electricity', 'cable', 'education', 'streaming'], ['fake']),
    ]);
}

function dataRecurringPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Monthly 1GB',
        'category' => 'data',
        'frequency' => 'monthly',
        'interval' => 1,
        'payload' => [
            'phone_number' => '08031234567',
            'amount' => 1000,
            'network' => 'mtn',
            'plan' => 'gift-1gb',
        ],
    ], $overrides);
}

function dueRecurring(User $user, array $attributes = []): RecurringPurchase
{
    return RecurringPurchase::create(array_merge([
        'user_id' => $user->id,
        'name' => 'Monthly 1GB',
        'category' => 'data',
        'payload' => [
            'phone_number' => '08031234567',
            'amount' => 1000,
            'network' => 'mtn',
            'plan' => 'gift-1gb',
        ],
        'amount' => 1000,
        'frequency' => 'monthly',
        'interval' => 1,
        'status' => 'active',
        'next_run_at' => now()->subMinute(),
    ], $attributes));
}

it('requires authentication to manage recurring purchases', function () {
    $this->getJson('/api/recurring-purchases')->assertStatus(401);
    $this->postJson('/api/recurring-purchases', dataRecurringPayload())->assertStatus(401);
});

it('creates a recurring purchase and computes the next run date', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/recurring-purchases', dataRecurringPayload())
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.category', 'data')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.payload.plan', 'gift-1gb');

    $nextRunAt = Carbon::parse($response->json('data.next_run_at'));

    expect($nextRunAt->greaterThan(now()->addDays(27)))->toBeTrue()
        ->and($nextRunAt->lessThan(now()->addDays(32)))->toBeTrue();

    $this->assertDatabaseHas('recurring_purchases', [
        'user_id' => $user->id,
        'category' => 'data',
        'amount' => 1000,
        'status' => 'active',
    ]);
});

it('honours an explicit future start date', function () {
    $user = User::factory()->create();
    $startAt = now()->addDays(5)->startOfMinute();

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/recurring-purchases', dataRecurringPayload([
            'start_at' => $startAt->toDateTimeString(),
        ]))
        ->assertStatus(201);

    expect(Carbon::parse($response->json('data.next_run_at'))->equalTo($startAt))->toBeTrue();
});

it('rejects a start date in the past', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/recurring-purchases', dataRecurringPayload([
            'start_at' => now()->subDay()->toDateTimeString(),
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start_at']);
});

it('validates category specific payloads', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/recurring-purchases', dataRecurringPayload([
            'payload' => ['phone_number' => '08031234567', 'amount' => 1000, 'network' => 'mtn'],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payload.plan']);

    $this->actingAs($user, 'api')
        ->postJson('/api/recurring-purchases', dataRecurringPayload([
            'category' => 'electricity',
            'payload' => ['amount' => 1000],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payload.meter_number', 'payload.meter_type', 'payload.disco']);
});

it('rejects unsupported categories and frequencies', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'api')
        ->postJson('/api/recurring-purchases', dataRecurringPayload(['category' => 'crypto']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category']);

    $this->actingAs($user, 'api')
        ->postJson('/api/recurring-purchases', dataRecurringPayload(['frequency' => 'hourly']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['frequency']);
});

it('lists only the authenticated users recurring purchases', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    dueRecurring($user);
    dueRecurring($other, ['name' => 'Someone else']);
    dueRecurring($user, ['name' => 'Paused one', 'status' => 'paused']);

    $this->actingAs($user, 'api')
        ->getJson('/api/recurring-purchases')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($user, 'api')
        ->getJson('/api/recurring-purchases?status=paused')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Paused one');
});

it('hides another users recurring purchase', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $purchase = dueRecurring($owner);

    $this->actingAs($intruder, 'api')
        ->getJson("/api/recurring-purchases/{$purchase->id}")
        ->assertStatus(404);

    $this->actingAs($intruder, 'api')
        ->patchJson("/api/recurring-purchases/{$purchase->id}", ['name' => 'Hacked'])
        ->assertStatus(404);

    $this->actingAs($intruder, 'api')
        ->deleteJson("/api/recurring-purchases/{$purchase->id}")
        ->assertStatus(404);

    $this->actingAs($intruder, 'api')
        ->postJson("/api/recurring-purchases/{$purchase->id}/pause")
        ->assertStatus(404);

    $this->actingAs($intruder, 'api')
        ->postJson("/api/recurring-purchases/{$purchase->id}/run")
        ->assertStatus(404);
});

it('pauses, resumes and cancels a recurring purchase', function () {
    $user = User::factory()->create();
    $purchase = dueRecurring($user);

    $this->actingAs($user, 'api')
        ->postJson("/api/recurring-purchases/{$purchase->id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', 'paused')
        ->assertJsonPath('data.next_run_at', null);

    $this->actingAs($user, 'api')
        ->postJson("/api/recurring-purchases/{$purchase->id}/resume")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect($purchase->fresh()->next_run_at->isFuture())->toBeTrue();

    $this->actingAs($user, 'api')
        ->deleteJson("/api/recurring-purchases/{$purchase->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->actingAs($user, 'api')
        ->postJson("/api/recurring-purchases/{$purchase->id}/resume")
        ->assertStatus(422);
});

it('updates the schedule and reschedules the next run', function () {
    $user = User::factory()->create();
    $purchase = dueRecurring($user, ['next_run_at' => now()->addMonth()]);

    $this->actingAs($user, 'api')
        ->patchJson("/api/recurring-purchases/{$purchase->id}", [
            'name' => 'Daily 1GB',
            'frequency' => 'daily',
            'interval' => 2,
            'payload' => ['amount' => 1200],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Daily 1GB')
        ->assertJsonPath('data.frequency', 'daily')
        ->assertJsonPath('data.interval', 2)
        ->assertJsonPath('data.amount', '1200.00');

    $fresh = $purchase->fresh();

    expect($fresh->next_run_at->lessThanOrEqualTo(now()->addHours(49)))
        ->and($fresh->next_run_at->greaterThan(now()->addHours(47)))
        ->and($fresh->payload['plan'])->toBe('gift-1gb');
});

it('computes monthly runs clamped to the last day of the month', function () {
    $service = app(RecurringPurchaseService::class);

    $next = $service->computeNextRunAt(Carbon::parse('2026-01-01 09:00:00'), 'monthly', 1, 31);

    expect($next->format('Y-m-d'))->toBe('2026-02-28');

    $weekly = $service->computeNextRunAt(Carbon::parse('2026-01-01 09:00:00'), 'weekly', 2);

    expect($weekly->format('Y-m-d'))->toBe('2026-01-15');
});

it('runs a purchase immediately through the run endpoint', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeRecurringPurchasesThroughFakeProvider('success');

    $purchase = dueRecurring($user, ['next_run_at' => now()->addMonth()]);

    $this->actingAs($user, 'api')
        ->postJson("/api/recurring-purchases/{$purchase->id}/run")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.last_status', 'successful')
        ->assertJsonPath('data.run_count', 1)
        ->assertJsonPath('data.failure_count', 0);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'category' => 'data',
        'status' => 'successful',
    ]);

    expect((float) $user->wallet->fresh()->balance)->toBe(4000.0);
});

it('requires a transaction pin to run a purchase when one is configured', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    app(TransactionPinService::class)->set($user, '1234');

    $purchase = dueRecurring($user, ['next_run_at' => now()->addMonth()]);

    $this->actingAs($user, 'api')
        ->postJson("/api/recurring-purchases/{$purchase->id}/run")
        ->assertStatus(422)
        ->assertJsonPath('code', 'transaction_pin_required');

    $this->assertDatabaseCount('transactions', 0);
});

it('processes only due purchases when the command runs', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 10000, 'currency' => 'NGN']);
    routeRecurringPurchasesThroughFakeProvider('success');

    $due = dueRecurring($user, ['name' => 'Due now']);
    $future = dueRecurring($user, ['name' => 'Next month', 'next_run_at' => now()->addMonth()]);
    $paused = dueRecurring($user, ['name' => 'Paused', 'status' => 'paused']);

    $this->artisan('recurring:purchases:run')
        ->expectsOutputToContain('Processed 1 recurring purchase(s)')
        ->assertSuccessful();

    expect($due->fresh()->run_count)->toBe(1)
        ->and($due->fresh()->next_run_at->isFuture())->toBeTrue()
        ->and($future->fresh()->run_count)->toBe(0)
        ->and($paused->fresh()->run_count)->toBe(0);
});

it('records failures and notifies the user when the wallet is short', function () {
    Notification::fake();

    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 100, 'currency' => 'NGN']);

    $purchase = dueRecurring($user);

    $outcome = app(RecurringPurchaseService::class)->process($purchase);

    expect($outcome['status'])->toBe('failed');

    $fresh = $purchase->fresh();

    expect($fresh->failure_count)->toBe(1)
        ->and($fresh->last_status)->toBe('failed')
        ->and($fresh->last_error)->toContain('Insufficient')
        ->and($fresh->next_run_at->greaterThan(now()->addMinutes(55)))->toBeTrue();

    Notification::assertSentTo($user, TransactionUpdate::class);

    $this->assertDatabaseCount('transactions', 0);
});

it('auto pauses a purchase after the configured number of failures', function () {
    Notification::fake();

    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 100, 'currency' => 'NGN']);
    $purchase = dueRecurring($user);
    $service = app(RecurringPurchaseService::class);

    foreach (range(1, 3) as $attempt) {
        $service->process($purchase, true);

        if ($attempt < 3) {
            expect($purchase->fresh()->status)->toBe('active');
        }
    }

    $fresh = $purchase->fresh();

    expect($fresh->failure_count)->toBe(3)
        ->and($fresh->status)->toBe('paused');

    Notification::assertSentToTimes($user, TransactionUpdate::class, 3);
});

it('treats a successful run as a reset of the failure counter', function () {
    $user = User::factory()->create();
    Wallet::create(['user_id' => $user->id, 'balance' => 5000, 'currency' => 'NGN']);
    routeRecurringPurchasesThroughFakeProvider('success');

    $purchase = dueRecurring($user, ['failure_count' => 2, 'status' => 'paused']);

    $outcome = app(RecurringPurchaseService::class)->process($purchase, true);

    expect($outcome['status'])->toBe('successful');

    $fresh = $purchase->fresh();

    expect($fresh->failure_count)->toBe(0)
        ->and($fresh->run_count)->toBe(1)
        ->and($fresh->status)->toBe('paused');
});
