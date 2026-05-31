<?php

declare(strict_types=1);

namespace App\Application\Mappers;

use App\Domain\Delivery\DTO\CancelShipmentCommand;
use App\Domain\Delivery\DTO\CreateShipmentCommand;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\Shipment;
use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;
use App\Infrastructure\Messaging\RabbitMq\OutgoingMessageHeadersFactory;
use App\Infrastructure\Messaging\RabbitMq\PublishedDeliveryEvent;

final class ShipmentEventPayloadMapper
{
    public function __construct(
        private readonly OutgoingMessageHeadersFactory $headersFactory,
    ) {
    }

    public function shipmentCreated(IncomingMessage $incoming, Shipment $shipment): PublishedDeliveryEvent
    {
        return new PublishedDeliveryEvent(
            routingKey: 'delivery.shipment.created.v1',
            headers: $this->headersFactory->forResponse($incoming),
            payload: [
                'shipment_id' => $shipment->shipmentId(),
                'order_id' => $shipment->orderId(),
                'status' => $shipment->status()->value,
                'carrier_profile' => $shipment->carrierProfile()->toArray(),
                'tracking_number' => $shipment->trackingNumber(),
                'created_at' => $shipment->createdAt()->format('Y-m-d\TH:i:s\Z'),
            ],
        );
    }

    public function shipmentCreationFailed(
        IncomingMessage $incoming,
        CreateShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
    ): PublishedDeliveryEvent {
        return new PublishedDeliveryEvent(
            routingKey: 'delivery.shipment.creation_failed.v1',
            headers: $this->headersFactory->forResponse($incoming),
            payload: [
                'shipment_id' => (string) $command->shipmentId,
                'order_id' => $command->orderId,
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
            ],
        );
    }

    public function shipmentStatusChanged(
        IncomingMessage $incoming,
        Shipment $shipment,
        ShipmentStatus $previousStatus,
        ?string $reason = null,
    ): PublishedDeliveryEvent {
        return new PublishedDeliveryEvent(
            routingKey: 'delivery.shipment.status_changed.v1',
            headers: $this->headersFactory->forStatusChange(
                $incoming,
                $shipment->shipmentId(),
                $shipment->status()->value,
            ),
            payload: array_filter([
                'shipment_id' => $shipment->shipmentId(),
                'order_id' => $shipment->orderId(),
                'previous_status' => $previousStatus->value,
                'current_status' => $shipment->status()->value,
                'changed_at' => $shipment->updatedAt()->format('Y-m-d\TH:i:s\Z'),
                'reason' => $reason,
                'tracking_number' => $shipment->trackingNumber(),
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    public function shipmentCancelled(IncomingMessage $incoming, Shipment $shipment): PublishedDeliveryEvent
    {
        $latestReason = $shipment->statusHistory()[array_key_last($shipment->statusHistory())]->reason;

        return new PublishedDeliveryEvent(
            routingKey: 'delivery.shipment.cancelled.v1',
            headers: $this->headersFactory->forResponse($incoming),
            payload: array_filter([
                'shipment_id' => $shipment->shipmentId(),
                'order_id' => $shipment->orderId(),
                'status' => $shipment->status()->value,
                'cancelled_at' => $shipment->updatedAt()->format('Y-m-d\TH:i:s\Z'),
                'reason' => $latestReason,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }

    public function shipmentCancelFailed(
        IncomingMessage $incoming,
        CancelShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
        ?ShipmentStatus $currentStatus = null,
    ): PublishedDeliveryEvent {
        return new PublishedDeliveryEvent(
            routingKey: 'delivery.shipment.cancel_failed.v1',
            headers: $this->headersFactory->forResponse($incoming),
            payload: array_filter([
                'shipment_id' => $command->shipmentId,
                'order_id' => $command->orderId,
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
                'current_status' => $currentStatus?->value,
            ], static fn (mixed $value): bool => $value !== null),
        );
    }
}
