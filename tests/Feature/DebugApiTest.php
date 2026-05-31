<?php

declare(strict_types=1);

test('debug reset clears shipments', function (): void {
    seedShipment('shp_http_001');

    $response = $this->app->handle(deliveryJsonRequest('POST', '/debug/reset'));

    expect($response->getStatusCode())->toBe(200);

    $payload = deliveryJsonResponse($response);

    expect($payload['status'])->toBe('reset')
        ->and($this->shipments->list())->toBe([]);
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
