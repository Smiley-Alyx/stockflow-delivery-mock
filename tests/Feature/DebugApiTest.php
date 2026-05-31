<?php

declare(strict_types=1);

test('debug reset clears shipments', function (): void {
    seedShipment('shp_http_001');

    $response = $this->app->handle(deliveryJsonRequest('POST', '/debug/reset'));

    expect($response->getStatusCode())->toBe(200);

    $payload = deliveryJsonResponse($response);

    expect($payload['status'])->toBe('reset')
        ->and($payload['failure_mode'])->toBe('normal')
        ->and($this->shipments->list())->toBe([]);
});

test('debug failure mode can be updated and read', function (): void {
    $setResponse = $this->app->handle(deliveryJsonRequest('POST', '/debug/failure-mode', [
        'mode' => 'always_reject_creation',
    ]));

    expect($setResponse->getStatusCode())->toBe(200);

    $setPayload = deliveryJsonResponse($setResponse);

    expect($setPayload['data']['mode'])->toBe('always_reject_creation');

    $showResponse = $this->app->handle(deliveryJsonRequest('GET', '/debug/failure-mode'));

    expect($showResponse->getStatusCode())->toBe(200);

    $showPayload = deliveryJsonResponse($showResponse);

    expect($showPayload['data']['mode'])->toBe('always_reject_creation')
        ->and($showPayload['data']['available_modes'])->toContain('provider_unavailable');
});

test('debug failure mode rejects invalid mode', function (): void {
    $response = $this->app->handle(deliveryJsonRequest('POST', '/debug/failure-mode', [
        'mode' => 'not_a_real_mode',
    ]));

    expect($response->getStatusCode())->toBe(422);
});

test('debug reset is forbidden when debug mode is disabled', function (): void {
    putenv('DELIVERY_MOCK_DEBUG_ENABLED=false');

    $container = App\Bootstrap\AppFactory::buildContainer();
    $app = App\Bootstrap\AppFactory::create($container);

    $response = $app->handle(deliveryJsonRequest('POST', '/debug/reset'));

    expect($response->getStatusCode())->toBe(403);

    $payload = deliveryJsonResponse($response);

    expect($payload['error'])->toBe('Debug endpoints are disabled.');
});
