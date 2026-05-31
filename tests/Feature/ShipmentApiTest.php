<?php

declare(strict_types=1);

use Tests\Support\DeliveryTestFixtures;

test('shipments index returns created shipments', function (): void {
    seedShipment('shp_http_001');
    seedShipment('shp_http_002');

    $response = $this->app->handle(deliveryJsonRequest('GET', '/shipments'));

    expect($response->getStatusCode())->toBe(200);

    $payload = deliveryJsonResponse($response);

    expect($payload['data'])->toHaveCount(2)
        ->and($payload['data'][0]['shipment_id'])->toBe('shp_http_001');
});

test('shipments index supports status and order filters', function (): void {
    seedShipment('shp_http_001');

    $this->shipments->create(DeliveryTestFixtures::createShipmentCommand(
        orderId: 'ord_other',
        shipmentId: 'shp_http_002',
    ));

    $this->shipments->advanceStatus(
        'shp_http_002',
        new DateTimeImmutable('2026-05-31T10:05:00+00:00'),
    );

    $response = $this->app->handle(deliveryJsonRequest('GET', '/shipments?status=label_generated&order_id=ord_other'));

    expect($response->getStatusCode())->toBe(200);

    $payload = deliveryJsonResponse($response);

    expect($payload['data'])->toHaveCount(1)
        ->and($payload['data'][0]['shipment_id'])->toBe('shp_http_002')
        ->and($payload['data'][0]['status'])->toBe('label_generated');
});

test('shipment detail endpoint returns full shipment payload', function (): void {
    seedShipment('shp_http_001');

    $response = $this->app->handle(deliveryJsonRequest('GET', '/shipments/shp_http_001'));

    expect($response->getStatusCode())->toBe(200);

    $payload = deliveryJsonResponse($response);

    expect($payload['data']['shipment_id'])->toBe('shp_http_001')
        ->and($payload['data']['delivery_address']['city'])->toBe('Moscow')
        ->and($payload['data']['status_history'])->toHaveCount(1);
});

test('shipment detail returns not found for missing shipment', function (): void {
    $response = $this->app->handle(deliveryJsonRequest('GET', '/shipments/shp_missing'));

    expect($response->getStatusCode())->toBe(404);

    $payload = deliveryJsonResponse($response);

    expect($payload['error'])->toBe('Shipment shp_missing was not found.');
});

test('advance status endpoint moves shipment forward', function (): void {
    seedShipment('shp_http_001');

    $response = $this->app->handle(deliveryJsonRequest(
        'POST',
        '/shipments/shp_http_001/advance-status',
        [
            'occurred_at' => '2026-05-31T10:05:00+00:00',
            'reason' => 'debug_advance',
        ],
    ));

    expect($response->getStatusCode())->toBe(200);

    $payload = deliveryJsonResponse($response);

    expect($payload['data']['status'])->toBe('label_generated')
        ->and($payload['data']['status_history'])->toHaveCount(2);
});

test('mark delivered endpoint updates shipment status', function (): void {
    seedShipment('shp_http_001');

    $occurredAt = new DateTimeImmutable('2026-05-31T10:05:00+00:00');

    foreach (range(1, 4) as $step) {
        $this->shipments->advanceStatus('shp_http_001', $occurredAt->modify("+{$step} minutes"));
    }

    $response = $this->app->handle(deliveryJsonRequest(
        'POST',
        '/shipments/shp_http_001/mark-delivered',
        ['reason' => 'debug_delivered'],
    ));

    expect($response->getStatusCode())->toBe(200);

    $payload = deliveryJsonResponse($response);

    expect($payload['data']['status'])->toBe('delivered');
});

test('mark failed endpoint updates shipment status', function (): void {
    seedShipment('shp_http_001');

    $occurredAt = new DateTimeImmutable('2026-05-31T10:05:00+00:00');

    foreach (range(1, 3) as $step) {
        $this->shipments->advanceStatus('shp_http_001', $occurredAt->modify("+{$step} minutes"));
    }

    $response = $this->app->handle(deliveryJsonRequest(
        'POST',
        '/shipments/shp_http_001/mark-failed',
        ['reason' => 'debug_failed'],
    ));

    expect($response->getStatusCode())->toBe(200);

    $payload = deliveryJsonResponse($response);

    expect($payload['data']['status'])->toBe('delivery_failed');
});

test('cancel endpoint cancels shipment', function (): void {
    seedShipment('shp_http_001');

    $response = $this->app->handle(deliveryJsonRequest(
        'POST',
        '/shipments/shp_http_001/cancel',
        ['reason' => 'debug_cancel'],
    ));

    expect($response->getStatusCode())->toBe(200);

    $payload = deliveryJsonResponse($response);

    expect($payload['data']['status'])->toBe('cancelled');
});

test('cancel endpoint returns conflict for non cancellable shipment', function (): void {
    seedShipment('shp_http_001');

    $occurredAt = new DateTimeImmutable('2026-05-31T10:05:00+00:00');

    foreach (range(1, 3) as $step) {
        $this->shipments->advanceStatus('shp_http_001', $occurredAt->modify("+{$step} minutes"));
    }

    $response = $this->app->handle(deliveryJsonRequest('POST', '/shipments/shp_http_001/cancel'));

    expect($response->getStatusCode())->toBe(409);
});

test('advance status endpoint returns conflict for terminal shipment', function (): void {
    seedShipment('shp_http_001');

    $occurredAt = new DateTimeImmutable('2026-05-31T10:05:00+00:00');

    foreach (range(1, 5) as $step) {
        $this->shipments->advanceStatus('shp_http_001', $occurredAt->modify("+{$step} minutes"));
    }

    $response = $this->app->handle(deliveryJsonRequest('POST', '/shipments/shp_http_001/advance-status'));

    expect($response->getStatusCode())->toBe(409);
});
