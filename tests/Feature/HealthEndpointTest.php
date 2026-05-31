<?php

declare(strict_types=1);

use App\Bootstrap\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

test('root returns service metadata', function (): void {
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/');
    $response = $this->app->handle($request);

    expect($response->getStatusCode())->toBe(200);

    $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'service' => 'stockflow-delivery-mock',
        'status' => 'ok',
    ]);
});

test('health endpoint returns ok', function (): void {
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
    $response = $this->app->handle($request);

    expect($response->getStatusCode())->toBe(200);

    $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toBe(['status' => 'ok']);
});

test('ready endpoint returns ready', function (): void {
    $request = (new ServerRequestFactory())->createServerRequest('GET', '/ready');
    $response = $this->app->handle($request);

    expect($response->getStatusCode())->toBe(200);

    $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toBe(['status' => 'ready']);
});
