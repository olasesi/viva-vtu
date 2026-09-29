<?php

use App\Services\Providers\AidaPayProvider;
use App\Services\Providers\VtpassProvider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

function providerStack(array $responses, array &$history): HandlerStack
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    return $stack;
}

it('keeps the vtpass /api base path on purchase and requery requests', function () {
    $history = [];
    $provider = new VtpassProvider([
        'base_url' => 'https://vtpass.com/api',
        'username' => 'user',
        'password' => 'pass',
        'handler' => providerStack([
            new Response(200, [], json_encode(['code' => '000'])),
            new Response(200, [], json_encode(['code' => '000'])),
        ], $history),
    ]);

    $provider->purchaseAirtime([
        'network' => 'mtn',
        'phone_number' => '08031234567',
        'amount' => 500,
        'request_id' => 'REQ-1',
    ]);
    $provider->requery('REQ-1');

    expect((string) $history[0]['request']->getUri())->toBe('https://vtpass.com/api/pay')
        ->and((string) $history[1]['request']->getUri())->toBe('https://vtpass.com/api/requery/REQ-1');
});

it('keeps the aidapay /api/v1 base path on buy and verification requests', function () {
    $history = [];
    $provider = new AidaPayProvider([
        'base_url' => 'https://www.aidapay.ng/api/v1',
        'api_token' => 'token',
        'account_pin' => '1234',
        'handler' => providerStack([
            new Response(200, [], json_encode(['success' => true, 'data' => ['message' => 'processing']])),
            new Response(200, [], json_encode(['success' => true, 'data' => ['verified' => true]])),
        ], $history),
    ]);

    $provider->purchaseAirtime([
        'network' => 'mtn',
        'phone_number' => '08031234567',
        'amount' => 500,
        'request_id' => 'REQ-2',
    ]);
    $provider->verifyCustomer(['serviceID' => 'dstv', 'billersCode' => '7034567890']);

    expect((string) $history[0]['request']->getUri())->toBe('https://www.aidapay.ng/api/v1/buy')
        ->and((string) $history[1]['request']->getUri())->toBe('https://www.aidapay.ng/api/v1/validation/dstv/7034567890');
});

it('normalises a base_url that already ends in a slash', function () {
    $history = [];
    $provider = new VtpassProvider([
        'base_url' => 'https://vtpass.com/api/',
        'username' => 'user',
        'password' => 'pass',
        'handler' => providerStack([
            new Response(200, [], json_encode(['code' => '000'])),
        ], $history),
    ]);

    $provider->purchaseAirtime([
        'network' => 'mtn',
        'phone_number' => '08031234567',
        'amount' => 500,
        'request_id' => 'REQ-3',
    ]);

    expect((string) $history[0]['request']->getUri())->toBe('https://vtpass.com/api/pay');
});
