<?php

declare(strict_types=1);

use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\DeliveryTestFixtures;

function deliveryJsonRequest(string $method, string $uri, ?array $body = null): Psr\Http\Message\ServerRequestInterface
{
    $request = (new ServerRequestFactory())->createServerRequest($method, $uri);

    if ($body === null) {
        return $request;
    }

    $request->getBody()->write((string) json_encode($body, JSON_THROW_ON_ERROR));

    return $request
        ->withHeader('Content-Type', 'application/json')
        ->withParsedBody($body);
}

/**
 * @return array<string, mixed>
 */
function deliveryJsonResponse(Psr\Http\Message\ResponseInterface $response): array
{
    return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
}

function seedShipment(string $shipmentId = 'shp_http_001'): void
{
    test()->shipments->create(DeliveryTestFixtures::createShipmentCommand(
        orderId: 'ord_http_001',
        shipmentId: $shipmentId,
    ));
}
