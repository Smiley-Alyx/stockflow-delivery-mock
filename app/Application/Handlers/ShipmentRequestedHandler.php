<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Mappers\ShipmentMessageMapper;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentStateException;
use App\Domain\Delivery\Services\Idempotency\ShipmentIdempotencyService;
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
        private readonly ShipmentIdempotencyService $idempotency,
    ) {
    }

    public function handle(IncomingMessage $message): void
    {
        $command = $this->mapper->toCreateShipmentCommand($message);
        $shipmentId = $command->shipmentId ?? throw new InvalidShipmentStateException('shipment_id is required');

        DeliveryStructuredLogger::info('delivery shipment requested', [
            'event' => 'delivery.shipment.requested.v1',
            'correlation_id' => $message->headers->correlationId,
            'shipment_id' => $shipmentId,
            'order_id' => $command->orderId,
            'idempotency_key' => $message->headers->idempotencyKey,
        ]);

        $existing = $this->idempotency->findCreateRecord($shipmentId, $message->headers->idempotencyKey);

        if ($existing !== null) {
            $shipment = $this->shipments->get($shipmentId);
            $this->idempotency->assertMatchingFingerprint(
                $existing,
                $this->idempotency->createFingerprint($shipment),
            );
            $this->replayCreateEvents($message, $shipment);

            return;
        }

        $shipment = $this->shipments->create($command);
        $previousStatus = $shipment->status();

        $this->shipments->advanceStatus(
            $shipment->shipmentId(),
            new \DateTimeImmutable($message->headers->occurredAt),
            'label_generated',
        );

        $updatedShipment = $this->shipments->get($shipment->shipmentId());

        $this->idempotency->storeCreateRecord(
            $shipmentId,
            $message->headers->idempotencyKey,
            $this->idempotency->createFingerprint($updatedShipment),
        );

        $this->eventPublisher->publishShipmentCreated($message, $shipment);
        $this->eventPublisher->publishShipmentStatusChanged(
            $message,
            $updatedShipment,
            $previousStatus,
            'label_generated',
        );
    }

    private function replayCreateEvents(IncomingMessage $message, \App\Domain\Delivery\Models\Shipment $shipment): void
    {
        $this->eventPublisher->publishShipmentCreated($message, $shipment);
        $this->eventPublisher->publishShipmentStatusChanged(
            $message,
            $shipment,
            ShipmentStatus::Created,
            'label_generated',
        );
    }
}
