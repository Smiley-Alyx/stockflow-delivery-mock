<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Mappers\ShipmentEventPayloadMapper;
use App\Domain\Delivery\DTO\CancelShipmentCommand;
use App\Domain\Delivery\DTO\CreateShipmentCommand;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\Shipment;
use App\Infrastructure\Messaging\RabbitMq\Contracts\DeliveryEventPublisher;
use App\Support\DeliveryStructuredLogger;

final class RabbitMqDeliveryEventPublisher implements DeliveryEventPublisher
{
    public function __construct(
        private readonly ShipmentEventPayloadMapper $payloadMapper,
        private readonly RabbitMqMessagePublisher $messagePublisher,
    ) {
    }

    public function publishShipmentCreated(IncomingMessage $incoming, Shipment $shipment): void
    {
        $this->publish($this->payloadMapper->shipmentCreated($incoming, $shipment));
    }

    public function publishShipmentCreationFailed(
        IncomingMessage $incoming,
        CreateShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
    ): void {
        $this->publish($this->payloadMapper->shipmentCreationFailed(
            $incoming,
            $command,
            $failureCode,
            $failureMessage,
        ));
    }

    public function publishShipmentStatusChanged(
        IncomingMessage $incoming,
        Shipment $shipment,
        ShipmentStatus $previousStatus,
        ?string $reason = null,
    ): void {
        $this->publish($this->payloadMapper->shipmentStatusChanged(
            $incoming,
            $shipment,
            $previousStatus,
            $reason,
        ));
    }

    public function publishShipmentCancelled(IncomingMessage $incoming, Shipment $shipment): void
    {
        $this->publish($this->payloadMapper->shipmentCancelled($incoming, $shipment));
    }

    public function publishShipmentCancelFailed(
        IncomingMessage $incoming,
        CancelShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
        ?ShipmentStatus $currentStatus = null,
    ): void {
        $this->publish($this->payloadMapper->shipmentCancelFailed(
            $incoming,
            $command,
            $failureCode,
            $failureMessage,
            $currentStatus,
        ));
    }

    public function close(): void
    {
        $this->messagePublisher->close();
    }

    private function publish(PublishedDeliveryEvent $event): void
    {
        $this->messagePublisher->publish($event);

        DeliveryStructuredLogger::info('delivery event published', [
            'routing_key' => $event->routingKey,
            'correlation_id' => $event->headers->correlationId,
            'causation_id' => $event->headers->causationId,
            'message_id' => $event->headers->messageId,
            'idempotency_key' => $event->headers->idempotencyKey,
        ]);
    }
}
