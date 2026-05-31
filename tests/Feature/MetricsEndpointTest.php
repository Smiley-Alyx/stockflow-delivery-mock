<?php

declare(strict_types=1);

use App\Infrastructure\Observability\DeliveryMetricsRecorder;
use Slim\Psr7\Factory\ServerRequestFactory;

test('metrics endpoint returns prometheus text', function (): void {
    $recorder = $this->container->get(DeliveryMetricsRecorder::class);
    $recorder->recordRequestProcessed(
        operation: 'shipment_create',
        routingKey: 'delivery.shipment.requested.v1',
        outcome: 'created',
        durationSeconds: 0.015,
    );

    $request = (new ServerRequestFactory())->createServerRequest('GET', '/metrics');
    $response = $this->app->handle($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeaderLine('Content-Type'))->toBe('text/plain; version=0.0.4; charset=utf-8');

    $body = (string) $response->getBody();

    expect($body)->toContain('delivery_requests_total')
        ->and($body)->toContain('delivery_failure_mode_active');
});

test('metrics endpoint is disabled when config is off', function (): void {
    putenv('DELIVERY_MOCK_METRICS_ENABLED=false');

    $container = App\Bootstrap\AppFactory::buildContainer();
    $app = App\Bootstrap\AppFactory::create($container);

    $request = (new ServerRequestFactory())->createServerRequest('GET', '/metrics');
    $response = $app->handle($request);

    expect($response->getStatusCode())->toBe(404);
});
