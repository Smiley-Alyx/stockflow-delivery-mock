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

final class ShipmentCancelRequestedHandler
{
    public function __construct(
        private readonly ShipmentMessageMapper $mapper,
        private readonly ShipmentLifecycleService $shipments,
        private readonly DeliveryEventPublisher $eventPublisher,
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

        try {
            $shipment = $this->shipments->cancel(
                $command->shipmentId,
                $command->occurredAt,
                $command->reason,
            );
        } catch (ShipmentNotFoundException $exception) {
            $this->eventPublisher->publishShipmentCancelFailed(
                $message,
                $command,
                'shipment_not_found',
                $exception->getMessage(),
            );

            return;
        } catch (InvalidShipmentStateException $exception) {
            $currentStatus = $this->resolveCurrentStatus($command->shipmentId);

            $this->eventPublisher->publishShipmentCancelFailed(
                $message,
                $command,
                $this->cancelFailureCode($currentStatus),
                $exception->getMessage(),
                $currentStatus,
            );

            return;
        }

        $this->eventPublisher->publishShipmentCancelled($message, $shipment);
    }

    private function resolveCurrentStatus(string $shipmentId): ?ShipmentStatus
    {
        try {
            return $this->shipments->get($shipmentId)->status();
        } catch (ShipmentNotFoundException) {
            return null;
        }
    }

    private function cancelFailureCode(?ShipmentStatus $status): string
    {
        return match ($status) {
            ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery => 'shipment_already_in_transit',
            ShipmentStatus::Delivered => 'shipment_already_delivered',
            ShipmentStatus::Cancelled => 'shipment_already_cancelled',
            default => 'delivery_failed',
        };
    }
}
