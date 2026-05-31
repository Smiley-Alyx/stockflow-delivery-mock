<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Mappers\ShipmentMessageMapper;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentStateException;
use App\Domain\Delivery\Exceptions\ShipmentNotFoundException;
use App\Domain\Delivery\Models\PublishedEventRecord;
use App\Domain\Delivery\Services\Debug\DeliveryDegradationSimulator;
use App\Domain\Delivery\Services\Debug\DeliveryOperation;
use App\Domain\Delivery\Services\Idempotency\ShipmentIdempotencyService;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Infrastructure\Messaging\RabbitMq\Contracts\DeliveryEventPublisher;
use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;
use App\Infrastructure\Messaging\RabbitMq\PublishedEventStore;
use App\Support\DeliveryStructuredLogger;

final class ShipmentCancelRequestedHandler
{
    public function __construct(
        private readonly ShipmentMessageMapper $mapper,
        private readonly ShipmentLifecycleService $shipments,
        private readonly DeliveryEventPublisher $eventPublisher,
        private readonly ShipmentIdempotencyService $idempotency,
        private readonly PublishedEventStore $publishedEventStore,
        private readonly DeliveryDegradationSimulator $degradationSimulator,
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

        $existing = $this->idempotency->findCancelRecord($command->shipmentId, $command->idempotencyKey);

        if ($existing !== null) {
            $this->replayCancelEvents($message, $command);

            return;
        }

        $this->degradationSimulator->beforeProcessing(DeliveryOperation::ShipmentCancel);

        $simulatedCancelFailure = $this->degradationSimulator->cancelFailure();

        if ($simulatedCancelFailure !== null) {
            $this->handleSimulatedCancelFailure(
                $message,
                $command,
                $simulatedCancelFailure['code'],
                $simulatedCancelFailure['message'],
            );

            return;
        }

        try {
            $shipment = $this->shipments->cancel(
                $command->shipmentId,
                $command->occurredAt,
                $command->reason,
            );
        } catch (ShipmentNotFoundException $exception) {
            $this->idempotency->storeCancelRecord(
                $command->shipmentId,
                $command->idempotencyKey,
                $this->idempotency->cancelFailureFingerprint(
                    $command->shipmentId,
                    'shipment_not_found',
                    null,
                ),
            );

            $this->eventPublisher->publishShipmentCancelFailed(
                $message,
                $command,
                'shipment_not_found',
                $exception->getMessage(),
            );

            return;
        } catch (InvalidShipmentStateException $exception) {
            $currentStatus = $this->resolveCurrentStatus($command->shipmentId);
            $failureCode = $this->cancelFailureCode($currentStatus);

            $this->idempotency->storeCancelRecord(
                $command->shipmentId,
                $command->idempotencyKey,
                $this->idempotency->cancelFailureFingerprint(
                    $command->shipmentId,
                    $failureCode,
                    $currentStatus,
                ),
            );

            $this->eventPublisher->publishShipmentCancelFailed(
                $message,
                $command,
                $failureCode,
                $exception->getMessage(),
                $currentStatus,
            );

            return;
        }

        $this->idempotency->storeCancelRecord(
            $command->shipmentId,
            $command->idempotencyKey,
            $this->idempotency->cancelSuccessFingerprint($shipment),
        );

        $this->eventPublisher->publishShipmentCancelled($message, $shipment);
    }

    private function handleSimulatedCancelFailure(
        IncomingMessage $message,
        \App\Domain\Delivery\DTO\CancelShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
    ): void {
        $currentStatus = $this->resolveCurrentStatus($command->shipmentId);

        $this->idempotency->storeCancelRecord(
            $command->shipmentId,
            $command->idempotencyKey,
            $this->idempotency->cancelFailureFingerprint(
                $command->shipmentId,
                $failureCode,
                $currentStatus,
            ),
        );

        $this->eventPublisher->publishShipmentCancelFailed(
            $message,
            $command,
            $failureCode,
            $failureMessage,
            $currentStatus,
        );
    }

    private function replayCancelEvents(IncomingMessage $message, \App\Domain\Delivery\DTO\CancelShipmentCommand $command): void
    {
        $cancelledEvent = $this->publishedEventStore->find(
            PublishedEventRecord::OPERATION_SHIPMENT_CANCELLED,
            $command->shipmentId,
            $command->idempotencyKey,
        );

        if ($cancelledEvent !== null) {
            $this->eventPublisher->publishShipmentCancelled(
                $message,
                $this->shipments->get($command->shipmentId),
            );

            return;
        }

        $failedEvent = $this->publishedEventStore->find(
            PublishedEventRecord::OPERATION_SHIPMENT_CANCEL_FAILED,
            $command->shipmentId,
            $command->idempotencyKey,
        );

        if ($failedEvent === null) {
            throw new InvalidShipmentStateException('Missing stored cancel outcome for idempotent replay');
        }

        $currentStatus = isset($failedEvent->payload['current_status'])
            ? ShipmentStatus::from($failedEvent->payload['current_status'])
            : null;

        $this->eventPublisher->publishShipmentCancelFailed(
            $message,
            $command,
            (string) $failedEvent->payload['failure_code'],
            (string) $failedEvent->payload['failure_message'],
            $currentStatus,
        );
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
