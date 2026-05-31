<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Mappers\ShipmentMessageMapper;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;
use App\Support\DeliveryStructuredLogger;

final class ShipmentCancelRequestedHandler
{
    public function __construct(
        private readonly ShipmentMessageMapper $mapper,
        private readonly ShipmentLifecycleService $shipments,
    ) {
    }

    public function handle(IncomingMessage $message): void
    {
        $command = $this->mapper->toCancelShipmentCommand($message);

        DeliveryStructuredLogger::info('delivery shipment cancel requested', [
            'event' => 'delivery.shipment.cancel_requested.v1',
            'correlation_id' => $command->correlationId,
            'shipment_id' => $command->shipmentId,
            'order_id' => $command->orderId,
            'idempotency_key' => $command->idempotencyKey,
        ]);

        $shipment = $this->shipments->cancel(
            $command->shipmentId,
            $command->occurredAt,
            $command->reason,
        );

        DeliveryStructuredLogger::info('delivery shipment cancelled', [
            'event' => 'delivery.shipment.cancelled',
            'correlation_id' => $command->correlationId,
            'shipment_id' => $shipment->shipmentId(),
            'status' => $shipment->status()->value,
        ]);
    }
}
