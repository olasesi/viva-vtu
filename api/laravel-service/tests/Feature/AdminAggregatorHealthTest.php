<?php

use App\Models\User;
use App\Services\ProviderRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'aggregators.health.failure_threshold' => 3,
        'aggregators.health.cooldown_minutes' => 5,
        'aggregators.providers.vtpass.enabled' => true,
        'aggregators.providers.aidapay.enabled' => true,
    ]);
});

function healthAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

it('requires authentication to view aggregator health', function () {
    $this->getJson('/api/admin/aggregator-health')->assertStatus(401);
});

it('blocks non-admins from the health report', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user, 'api')
        ->getJson('/api/admin/aggregator-health')
        ->assertStatus(403);
});

it('reports enabled healthy providers with zero failures', function () {
    $response = $this->actingAs(healthAdmin(), 'api')
        ->getJson('/api/admin/aggregator-health');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.unhealthy', []);

    $this->assertArrayHasKey('vtpass', $response->json('data.providers'));
    $this->assertArrayHasKey('aidapay', $response->json('data.providers'));
});

it('lists a tripped provider as unhealthy with its failure count', function () {
    app(ProviderRouter::class)->markFailure('vtpass');
    app(ProviderRouter::class)->markFailure('vtpass');
    app(ProviderRouter::class)->markFailure('vtpass');

    $response = $this->actingAs(healthAdmin(), 'api')
        ->getJson('/api/admin/aggregator-health');

    $response->assertStatus(200)
        ->assertJsonPath('data.unhealthy', ['vtpass'])
        ->assertJsonPath('data.providers.vtpass.healthy', false)
        ->assertJsonPath('data.providers.vtpass.consecutive_failures', 3)
        ->assertJsonPath('data.providers.aidapay.healthy', true);
});

it('posts a Slack-style alert once when the breaker trips', function () {
    Http::fake();
    config([
        'aggregators.health.alert_enabled' => true,
        'aggregators.health.alert_url' => 'https://hooks.slack.com/services/abc/def',
    ]);

    app(ProviderRouter::class)->markFailure('vtpass');
    app(ProviderRouter::class)->markFailure('vtpass');
    app(ProviderRouter::class)->markFailure('vtpass');
    app(ProviderRouter::class)->markFailure('vtpass');

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://hooks.slack.com/services/abc/def'
            && str_contains($body['text'], 'vtpass')
            && str_contains($body['text'], 'Consecutive failures');
    });

    $this->assertTrue(Http::recorded()[0][0] !== null);
});

it('does not send alerts when alerting is disabled', function () {
    Http::fake();
    config(['aggregators.health.alert_enabled' => false]);

    app(ProviderRouter::class)->markFailure('vtpass');
    app(ProviderRouter::class)->markFailure('vtpass');
    app(ProviderRouter::class)->markFailure('vtpass');

    Http::assertNothingSent();
});
