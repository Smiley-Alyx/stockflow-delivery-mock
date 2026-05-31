<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Mappers\ShipmentMessageMapper;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;
use App\Support\DeliveryStructuredLogger;

final class ShipmentRequestedHandler
{
    public function __construct(
        private readonly ShipmentMessageMapper $mapper,
        private readonly ShipmentLifecycleService $shipments,
    ) {
    }

    public function handle(IncomingMessage $message): void
    {
        $command = $this->mapper->toCreateShipmentCommand($message);

        DeliveryStructuredLogger::info('delivery shipment requested', [
            'event' => 'delivery.shipment.requested.v1',
            'correlation_id' => $message->headers->correlationId,
            'shipment_id' => $command->shipmentId,
            'order_id' => $command->orderId,
            'idempotency_key' => $message->headers->idempotencyKey,
        ]);

        $shipment = $this->shipments->create($command);

        DeliveryStructuredLogger::info('delivery shipment accepted', [
            'event' => 'delivery.shipment.accepted',
            'correlation_id' => $message->headers->correlationId,
            'shipment_id' => $shipment->shipmentId(),
            'status' => $shipment->status()->value,
        ]);
    }
}
