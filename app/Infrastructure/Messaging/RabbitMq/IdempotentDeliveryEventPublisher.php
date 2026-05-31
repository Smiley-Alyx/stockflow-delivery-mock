<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Application\Mappers\ShipmentEventPayloadMapper;
use App\Domain\Delivery\DTO\CancelShipmentCommand;
use App\Domain\Delivery\DTO\CreateShipmentCommand;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\PublishedEventRecord;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Delivery\Services\Debug\DeliveryDegradationSimulator;
use App\Infrastructure\Messaging\RabbitMq\Contracts\DeliveryEventPublisher;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\PublishedEventConflictException;
use App\Support\DeliveryStructuredLogger;

final class IdempotentDeliveryEventPublisher implements DeliveryEventPublisher
{
    public function __construct(
        private readonly ShipmentEventPayloadMapper $payloadMapper,
        private readonly RabbitMqMessagePublisher $messagePublisher,
        private readonly PublishedEventStore $publishedEventStore,
        private readonly DeliveryDegradationSimulator $degradationSimulator,
    ) {
    }

    public function publishShipmentCreated(IncomingMessage $incoming, Shipment $shipment): void
    {
        $this->publishIdempotently(
            operation: PublishedEventRecord::OPERATION_SHIPMENT_CREATED,
            shipmentId: $shipment->shipmentId(),
            idempotencyKey: $incoming->headers->idempotencyKey,
            responseFingerprint: $this->createdFingerprint($shipment),
            eventFactory: fn (): PublishedDeliveryEvent => $this->payloadMapper->shipmentCreated($incoming, $shipment),
        );
    }

    public function publishShipmentCreationFailed(
        IncomingMessage $incoming,
        CreateShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
    ): void {
        $shipmentId = (string) $command->shipmentId;

        $this->publishIdempotently(
            operation: PublishedEventRecord::OPERATION_SHIPMENT_CREATION_FAILED,
            shipmentId: $shipmentId,
            idempotencyKey: $incoming->headers->idempotencyKey,
            responseFingerprint: $this->creationFailedFingerprint($shipmentId, $failureCode),
            eventFactory: fn (): PublishedDeliveryEvent => $this->payloadMapper->shipmentCreationFailed(
                $incoming,
                $command,
                $failureCode,
                $failureMessage,
            ),
        );
    }

    public function publishShipmentStatusChanged(
        IncomingMessage $incoming,
        Shipment $shipment,
        ShipmentStatus $previousStatus,
        ?string $reason = null,
    ): void
    {
        $idempotencyKey = sprintf(
            'idem-shp-status-%s-%s',
            $shipment->shipmentId(),
            $shipment->status()->value,
        );

        $this->publishIdempotently(
            operation: PublishedEventRecord::OPERATION_SHIPMENT_STATUS_CHANGED,
            shipmentId: $shipment->shipmentId(),
            idempotencyKey: $idempotencyKey,
            responseFingerprint: $this->statusChangedFingerprint(
                $shipment->shipmentId(),
                $previousStatus,
                $shipment->status(),
                $reason,
            ),
            eventFactory: fn (): PublishedDeliveryEvent => $this->payloadMapper->shipmentStatusChanged(
                $incoming,
                $shipment,
                $previousStatus,
                $reason,
            ),
        );
    }

    public function publishShipmentCancelled(IncomingMessage $incoming, Shipment $shipment): void
    {
        $this->publishIdempotently(
            operation: PublishedEventRecord::OPERATION_SHIPMENT_CANCELLED,
            shipmentId: $shipment->shipmentId(),
            idempotencyKey: $incoming->headers->idempotencyKey,
            responseFingerprint: $this->cancelledFingerprint($shipment),
            eventFactory: fn (): PublishedDeliveryEvent => $this->payloadMapper->shipmentCancelled($incoming, $shipment),
        );
    }

    public function publishShipmentCancelFailed(
        IncomingMessage $incoming,
        CancelShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
        ?ShipmentStatus $currentStatus = null,
    ): void {
        $this->publishIdempotently(
            operation: PublishedEventRecord::OPERATION_SHIPMENT_CANCEL_FAILED,
            shipmentId: $command->shipmentId,
            idempotencyKey: $incoming->headers->idempotencyKey,
            responseFingerprint: $this->cancelFailedFingerprint(
                $command->shipmentId,
                $failureCode,
                $currentStatus,
            ),
            eventFactory: fn (): PublishedDeliveryEvent => $this->payloadMapper->shipmentCancelFailed(
                $incoming,
                $command,
                $failureCode,
                $failureMessage,
                $currentStatus,
            ),
        );
    }

    public function close(): void
    {
        $this->messagePublisher->close();
    }

    /**
     * @param callable(): PublishedDeliveryEvent $eventFactory
     */
    private function publishIdempotently(
        string $operation,
        string $shipmentId,
        string $idempotencyKey,
        string $responseFingerprint,
        callable $eventFactory,
    ): void {
        $existing = $this->publishedEventStore->find($operation, $shipmentId, $idempotencyKey);

        if ($existing !== null) {
            $this->assertMatchingFingerprint($existing, $responseFingerprint, $operation, $shipmentId, $idempotencyKey);
            $this->publishStoredEvent($existing);

            return;
        }

        $event = $eventFactory();
        $this->publishEvent($event, false);

        $stored = $this->publishedEventStore->store(
            $operation,
            $shipmentId,
            $idempotencyKey,
            $event,
            $responseFingerprint,
        );

        if ($stored->responseFingerprint !== $responseFingerprint) {
            $this->assertMatchingFingerprint($stored, $responseFingerprint, $operation, $shipmentId, $idempotencyKey);
        }
    }

    private function publishStoredEvent(PublishedEventRecord $record): void
    {
        $this->publishEvent($this->publishedEventStore->toPublishedEvent($record), true);
    }

    private function publishEvent(PublishedDeliveryEvent $event, bool $storedReplay): void
    {
        $this->degradationSimulator->assertPublishAllowed();
        $this->messagePublisher->publish($event);

        DeliveryStructuredLogger::info('delivery event published', [
            'routing_key' => $event->routingKey,
            'correlation_id' => $event->headers->correlationId,
            'causation_id' => $event->headers->causationId,
            'message_id' => $event->headers->messageId,
            'idempotency_key' => $event->headers->idempotencyKey,
            'idempotent_event_replay' => $storedReplay,
        ]);

        if (! $storedReplay && $this->degradationSimulator->shouldDuplicatePublishedResponse()) {
            $this->messagePublisher->publish($event);

            DeliveryStructuredLogger::info('delivery event duplicate published', [
                'routing_key' => $event->routingKey,
                'message_id' => $event->headers->messageId,
                'idempotency_key' => $event->headers->idempotencyKey,
            ]);
        }
    }

    private function assertMatchingFingerprint(
        PublishedEventRecord $record,
        string $responseFingerprint,
        string $operation,
        string $shipmentId,
        string $idempotencyKey,
    ): void {
        if ($record->responseFingerprint !== $responseFingerprint) {
            throw PublishedEventConflictException::forOperation($operation, $shipmentId, $idempotencyKey);
        }
    }

    private function createdFingerprint(Shipment $shipment): string
    {
        return hash('sha256', implode('|', [
            PublishedEventRecord::OPERATION_SHIPMENT_CREATED,
            $shipment->shipmentId(),
            $shipment->orderId(),
            ShipmentStatus::Created->value,
            $shipment->trackingNumber() ?? '',
        ]));
    }

    private function creationFailedFingerprint(string $shipmentId, string $failureCode): string
    {
        return hash('sha256', implode('|', [
            PublishedEventRecord::OPERATION_SHIPMENT_CREATION_FAILED,
            $shipmentId,
            $failureCode,
        ]));
    }

    private function statusChangedFingerprint(
        string $shipmentId,
        ShipmentStatus $previousStatus,
        ShipmentStatus $currentStatus,
        ?string $reason,
    ): string {
        return hash('sha256', implode('|', [
            PublishedEventRecord::OPERATION_SHIPMENT_STATUS_CHANGED,
            $shipmentId,
            $previousStatus->value,
            $currentStatus->value,
            $reason ?? '',
        ]));
    }

    private function cancelledFingerprint(Shipment $shipment): string
    {
        return hash('sha256', implode('|', [
            PublishedEventRecord::OPERATION_SHIPMENT_CANCELLED,
            $shipment->shipmentId(),
            $shipment->updatedAt()->format(DATE_ATOM),
        ]));
    }

    private function cancelFailedFingerprint(
        string $shipmentId,
        string $failureCode,
        ?ShipmentStatus $currentStatus,
    ): string {
        return hash('sha256', implode('|', [
            PublishedEventRecord::OPERATION_SHIPMENT_CANCEL_FAILED,
            $shipmentId,
            $failureCode,
            $currentStatus?->value ?? 'unknown',
        ]));
    }
}
