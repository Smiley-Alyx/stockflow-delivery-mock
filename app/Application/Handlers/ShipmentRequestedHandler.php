<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Mappers\ShipmentMessageMapper;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentStateException;
use App\Domain\Delivery\Exceptions\ShipmentNotFoundException;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Infrastructure\Messaging\RabbitMq\Contracts\DeliveryEventPublisher;
use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;
use App\Support\DeliveryStructuredLogger;

final class ShipmentRequestedHandler
{
    public function __construct(
        private readonly ShipmentMessageMapper $mapper,
        private readonly ShipmentLifecycleService $shipments,
        private readonly DeliveryEventPublisher $eventPublisher,
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

        try {
            $shipment = $this->shipments->create($command);
        } catch (InvalidShipmentStateException $exception) {
            if ($command->shipmentId !== null && str_contains($exception->getMessage(), 'already exists')) {
                $shipment = $this->shipments->get($command->shipmentId);
                $this->eventPublisher->publishShipmentCreated($message, $shipment);

                return;
            }

            throw $exception;
        }

        $this->eventPublisher->publishShipmentCreated($message, $shipment);

        $previousStatus = $shipment->status();
        $this->shipments->advanceStatus(
            $shipment->shipmentId(),
            new \DateTimeImmutable($message->headers->occurredAt),
            'label_generated',
        );

        $updatedShipment = $this->shipments->get($shipment->shipmentId());
        $this->eventPublisher->publishShipmentStatusChanged(
            $message,
            $updatedShipment,
            $previousStatus,
            'label_generated',
        );
    }
}
