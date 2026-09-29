<?php

use App\Services\Providers\RechargeProvider;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

function rechargeMockStack(array $responses, array &$history): HandlerStack
{
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $stack->push(Middleware::history($history));

    return $stack;
}

function rechargeProvider(array $responses, array &$history): RechargeProvider
{
    $config = [
        'base_url' => 'https://nigeria.recharge.com.ng/api',
        'api_token' => 'test-token',
        'success_codes' => ['000', '200'],
        'handler' => rechargeMockStack($responses, $history),
        'endpoints' => [
            'airtime' => '/airtime',
            'data' => '/data',
            'electricity' => '/electricity',
            'cable' => '/tv',
            'exam' => '/exam',
            'streaming' => '/streaming',
            'verify' => '/verify',
            'requery' => '/status',
        ],
    ];

    return new RechargeProvider($config);
}

it('sends a correctly formed airtime request with token auth', function () {
    $history = [];
    $provider = rechargeProvider([
        new Response(200, [], json_encode(['code' => '000', 'content' => ['ident' => 'mtn-123']])),
    ], $history);

    $result = $provider->purchaseAirtime([
        'network' => 'mtn',
        'phone_number' => '08031234567',
        'amount' => 500,
        'request_id' => 'REQ-1',
    ]);

    expect($provider->isSuccessful($result))->toBeTrue();
    expect($result['content']['ident'])->toBe('mtn-123');

    $request = $history[0]['request'];
    expect((string) $request->getUri())->toBe('https://nigeria.recharge.com.ng/api/airtime')
        ->and($request->getHeaderLine('Authorization'))->toBe('Token test-token')
        ->and((string) $request->getBody())->toBe(json_encode([
            'network' => 'mtn',
            'amount' => 500,
            'phone' => '08031234567',
            'request_id' => 'REQ-1',
        ]));
});

it('builds the data purchase payload on the data endpoint', function () {
    $history = [];
    $provider = rechargeProvider([
        new Response(200, [], json_encode(['code' => '200'])),
    ], $history);

    $provider->purchaseData([
        'network' => 'glo',
        'phone_number' => '08031234567',
        'plan' => 'glo-data-1gb',
        'amount' => 1000,
        'request_id' => 'REQ-2',
    ]);

    $request = $history[0]['request'];
    expect((string) $request->getUri())->toContain('/data')
        ->and((string) $request->getBody())->toBe(json_encode([
            'network' => 'glo',
            'plan' => 'glo-data-1gb',
            'amount' => 1000,
            'phone' => '08031234567',
            'request_id' => 'REQ-2',
        ]));
});

it('maps electricity meter types on purchase', function () {
    $history = [];
    $provider = rechargeProvider([
        new Response(200, [], json_encode(['code' => '000'])),
        new Response(200, [], json_encode(['code' => '000'])),
    ], $history);

    $provider->purchaseElectricity([
        'disco' => 'ikeja-electric',
        'meter_number' => '12345678901',
        'meter_type' => 'prepaid',
        'amount' => 2000,
        'request_id' => 'REQ-3',
    ]);
    $provider->purchaseElectricity([
        'disco' => 'ikeja-electric',
        'meter_number' => '12345678901',
        'meter_type' => 'postpaid',
        'amount' => 2000,
        'request_id' => 'REQ-4',
    ]);

    expect(json_decode((string) $history[0]['request']->getBody(), true)['meter_type'])->toBe('prepaid')
        ->and(json_decode((string) $history[1]['request']->getBody(), true)['meter_type'])->toBe('postpaid');
});

it('routes cable validation through the verify endpoint', function () {
    $history = [];
    $provider = rechargeProvider([
        new Response(200, [], json_encode(['code' => '000', 'content' => ['Customer_Name' => 'JOHN DOE']])),
    ], $history);

    $result = $provider->purchaseCable([
        'cable' => 'dstv',
        'smartcard_number' => '7034567890',
        'package' => 'Compact Plus',
        'amount' => 25000,
        'request_id' => 'REQ-5',
        'action' => 'validate',
    ]);

    expect($provider->isSuccessful($result))->toBeTrue()
        ->and((string) $history[0]['request']->getUri())->toContain('/verify')
        ->and(json_decode((string) $history[0]['request']->getBody(), true))->toBe([
            'serviceID' => 'dstv',
            'billersCode' => '7034567890',
        ]);
});

it('buys cable subscription on the tv endpoint', function () {
    $history = [];
    $provider = rechargeProvider([
        new Response(200, [], json_encode(['code' => '000'])),
    ], $history);

    $provider->purchaseCable([
        'cable' => 'gotv',
        'smartcard_number' => '7034567890',
        'package' => 'Smallie',
        'amount' => 2300,
        'request_id' => 'REQ-6',
    ]);

    expect((string) $history[0]['request']->getUri())->toContain('/tv')
        ->and(json_decode((string) $history[0]['request']->getBody(), true))->toBe([
            'cable' => 'gotv',
            'smartcard_number' => '7034567890',
            'package' => 'Smallie',
            'amount' => 2300,
            'request_id' => 'REQ-6',
        ]);
});

it('treats a definitive provider rejection as not successful', function () {
    $history = [];
    $provider = rechargeProvider([
        new Response(200, [], json_encode(['code' => '903', 'response_message' => 'Wrong network selected'])),
    ], $history);

    $result = $provider->purchaseAirtime([
        'network' => 'mtn',
        'phone_number' => '08031234567',
        'amount' => 500,
        'request_id' => 'REQ-7',
    ]);

    expect($provider->isSuccessful($result))->toBeFalse()
        ->and($result['code'])->toBe('903')
        ->and($result['response_message'])->toBe('Wrong network selected');
});

it('returns a 999 envelope when the provider is unreachable', function () {
    $history = [];
    $provider = rechargeProvider([new ConnectException('boom', new Request('POST', '/airtime'))], $history);

    $result = $provider->purchaseAirtime([
        'network' => 'mtn',
        'phone_number' => '08031234567',
        'amount' => 500,
        'request_id' => 'REQ-8',
    ]);

    expect($result['code'])->toBe('999')
        ->and($result['response_message'])->toContain('unavailable');
});

it('requeries a request by id with a query string', function () {
    $history = [];
    $provider = rechargeProvider([
        new Response(200, [], json_encode(['code' => '000', 'content' => ['status' => 'delivered']])),
    ], $history);

    $result = $provider->requery('REQ-9');

    expect($provider->isSuccessful($result))->toBeTrue()
        ->and((string) $history[0]['request']->getUri())->toContain('/status')
        ->and($history[0]['request']->getUri()->getQuery())->toBe('request_id=REQ-9');
});

it('sends exam pin and streaming purchases', function () {
    $history = [];
    $provider = rechargeProvider([
        new Response(200, [], json_encode(['code' => '000'])),
        new Response(200, [], json_encode(['code' => '000'])),
    ], $history);

    $provider->purchaseExamPins(['exam_type' => 'waec', 'quantity' => 2, 'amount' => 2000, 'request_id' => 'REQ-10']);
    $provider->purchaseStreaming(['platform' => 'netflix', 'plan' => 'mobile', 'amount' => 2900, 'request_id' => 'REQ-11']);

    expect((string) $history[0]['request']->getUri())->toContain('/exam')
        ->and(json_decode((string) $history[1]['request']->getBody(), true))->toBe([
            'platform' => 'netflix',
            'plan' => 'mobile',
            'amount' => 2900,
            'request_id' => 'REQ-11',
        ]);
});

it('wraps non-array responses as a failure', function () {
    $history = [];
    $provider = rechargeProvider([
        new Response(200, [], 'not-json'),
    ], $history);

    $result = $provider->purchaseAirtime([
        'network' => 'mtn',
        'phone_number' => '08031234567',
        'amount' => 500,
        'request_id' => 'REQ-12',
    ]);

    expect($result['code'])->toBe('999')
        ->and($result['response_message'])->toBe('Invalid provider response');
});
