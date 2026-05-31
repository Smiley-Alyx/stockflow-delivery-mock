<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Domain\Delivery\DTO\CancelShipmentCommand;
use App\Domain\Delivery\DTO\CreateShipmentCommand;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\Shipment;
use App\Infrastructure\Messaging\RabbitMq\Contracts\DeliveryEventPublisher;
use App\Support\DeliveryStructuredLogger;

final class NullDeliveryEventPublisher implements DeliveryEventPublisher
{
    public function publishShipmentCreated(IncomingMessage $incoming, Shipment $shipment): void
    {
        $this->log('delivery.shipment.created.v1', $incoming);
    }

    public function publishShipmentCreationFailed(
        IncomingMessage $incoming,
        CreateShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
    ): void {
        $this->log('delivery.shipment.creation_failed.v1', $incoming, $failureCode);
    }

    public function publishShipmentStatusChanged(
        IncomingMessage $incoming,
        Shipment $shipment,
        ShipmentStatus $previousStatus,
        ?string $reason = null,
    ): void {
        $this->log('delivery.shipment.status_changed.v1', $incoming, $shipment->status()->value);
    }

    public function publishShipmentCancelled(IncomingMessage $incoming, Shipment $shipment): void
    {
        $this->log('delivery.shipment.cancelled.v1', $incoming);
    }

    public function publishShipmentCancelFailed(
        IncomingMessage $incoming,
        CancelShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
        ?ShipmentStatus $currentStatus = null,
    ): void {
        $this->log('delivery.shipment.cancel_failed.v1', $incoming, $failureCode);
    }

    private function log(string $routingKey, IncomingMessage $incoming, ?string $detail = null): void
    {
        DeliveryStructuredLogger::info('delivery event publish skipped (DELIVERY_MOCK_PUBLISH_EVENTS=false)', [
            'routing_key' => $routingKey,
            'correlation_id' => $incoming->headers->correlationId,
            'message_id' => $incoming->headers->messageId,
            'detail' => $detail,
        ]);
    }
}
