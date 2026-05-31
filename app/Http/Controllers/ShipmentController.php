<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Mappers\ShipmentMapper;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ShipmentController extends JsonController
{
    public function __construct(
        private readonly ShipmentLifecycleService $shipments,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $shipments = $this->shipments->list();

        if (isset($queryParams['status']) && is_string($queryParams['status']) && $queryParams['status'] !== '') {
            try {
                $status = ShipmentStatus::from($queryParams['status']);
            } catch (\ValueError) {
                return $this->json($response, ['error' => 'Invalid status filter.'], 400);
            }

            $shipments = array_values(array_filter(
                $shipments,
                static fn ($shipment) => $shipment->status() === $status,
            ));
        }

        if (isset($queryParams['order_id']) && is_string($queryParams['order_id']) && $queryParams['order_id'] !== '') {
            $orderId = $queryParams['order_id'];
            $shipments = array_values(array_filter(
                $shipments,
                static fn ($shipment) => $shipment->orderId() === $orderId,
            ));
        }

        $limit = 50;

        if (isset($queryParams['limit']) && is_numeric($queryParams['limit'])) {
            $limit = max(1, min(100, (int) $queryParams['limit']));
        }

        $shipments = array_slice($shipments, 0, $limit);

        return $this->json($response, [
            'data' => array_map(
                static fn ($shipment): array => ShipmentMapper::summary($shipment),
                $shipments,
            ),
        ]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $shipment = $this->shipments->get($args['shipmentId']);

        return $this->json($response, [
            'data' => ShipmentMapper::detail($shipment),
        ]);
    }

    public function advanceStatus(Request $request, Response $response, array $args): Response
    {
        $body = $this->parsedBody($request);
        $shipment = $this->shipments->advanceStatus(
            $args['shipmentId'],
            $this->occurredAtFromBody($body),
            $this->reasonFromBody($body),
        );

        return $this->json($response, [
            'data' => ShipmentMapper::detail($shipment),
        ]);
    }

    public function markDelivered(Request $request, Response $response, array $args): Response
    {
        $body = $this->parsedBody($request);
        $shipment = $this->shipments->markDelivered(
            $args['shipmentId'],
            $this->occurredAtFromBody($body),
            $this->reasonFromBody($body),
        );

        return $this->json($response, [
            'data' => ShipmentMapper::detail($shipment),
        ]);
    }

    public function markFailed(Request $request, Response $response, array $args): Response
    {
        $body = $this->parsedBody($request);
        $shipment = $this->shipments->markFailed(
            $args['shipmentId'],
            $this->occurredAtFromBody($body),
            $this->reasonFromBody($body),
        );

        return $this->json($response, [
            'data' => ShipmentMapper::detail($shipment),
        ]);
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $body = $this->parsedBody($request);
        $shipment = $this->shipments->cancel(
            $args['shipmentId'],
            $this->occurredAtFromBody($body),
            $this->reasonFromBody($body),
        );

        return $this->json($response, [
            'data' => ShipmentMapper::detail($shipment),
        ]);
    }
}
