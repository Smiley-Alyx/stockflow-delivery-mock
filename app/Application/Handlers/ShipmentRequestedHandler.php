<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Mappers\ShipmentMessageMapper;
use App\Domain\Delivery\DTO\CreateShipmentCommand;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentStateException;
use App\Domain\Delivery\Exceptions\ShipmentNotFoundException;
use App\Domain\Delivery\Models\PublishedEventRecord;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Delivery\Services\Debug\DeliveryDegradationSimulator;
use App\Domain\Delivery\Services\Debug\DeliveryOperation;
use App\Domain\Delivery\Services\Idempotency\ShipmentIdempotencyService;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Infrastructure\Messaging\RabbitMq\Contracts\DeliveryEventPublisher;
use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;
use App\Infrastructure\Messaging\RabbitMq\PublishedEventStore;
use App\Infrastructure\Observability\DeliveryMetricsRecorder;
use App\Support\DeliveryStructuredLogger;

final class ShipmentRequestedHandler
{
    public function __construct(
        private readonly ShipmentMessageMapper $mapper,
        private readonly ShipmentLifecycleService $shipments,
        private readonly DeliveryEventPublisher $eventPublisher,
        private readonly ShipmentIdempotencyService $idempotency,
        private readonly DeliveryDegradationSimulator $degradationSimulator,
        private readonly PublishedEventStore $publishedEventStore,
        private readonly DeliveryMetricsRecorder $metricsRecorder,
    ) {
    }

    public function handle(IncomingMessage $message): void
    {
        $startedAt = hrtime(true);
        $command = $this->mapper->toCreateShipmentCommand($message);
        $shipmentId = $command->shipmentId ?? throw new InvalidShipmentStateException('shipment_id is required');

        DeliveryStructuredLogger::info('delivery shipment requested', DeliveryStructuredLogger::context('delivery.shipment.requested', [
            'correlation_id' => $message->headers->correlationId,
            'shipment_id' => $shipmentId,
            'order_id' => $command->orderId,
            'idempotency_key' => $message->headers->idempotencyKey,
            'routing_key' => $message->routingKey,
        ]));

        $this->degradationSimulator->beforeProcessing(DeliveryOperation::ShipmentCreate);

        $existing = $this->idempotency->findCreateRecord($shipmentId, $message->headers->idempotencyKey);

        if ($existing !== null) {
            $this->replayExistingCreate($message, $command, $existing);
            $this->recordProcessed($message, 'idempotent_replay', $startedAt, true);

            return;
        }

        $creationFailure = $this->degradationSimulator->creationFailure();

        if ($creationFailure !== null) {
            $this->handleCreationFailure($message, $command, $creationFailure['code'], $creationFailure['message']);
            $this->recordProcessed($message, 'creation_failed', $startedAt);

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

        $this->recordProcessed($message, 'created', $startedAt);
    }

    private function replayExistingCreate(
        IncomingMessage $message,
        CreateShipmentCommand $command,
        \App\Domain\Delivery\Models\IdempotencyRecord $existing,
    ): void {
        try {
            $shipment = $this->shipments->get($command->shipmentId ?? '');
            $this->idempotency->assertMatchingFingerprint(
                $existing,
                $this->idempotency->createFingerprint($shipment),
            );
            $this->replayCreateEvents($message, $shipment);
        } catch (ShipmentNotFoundException) {
            $this->replayCreationFailure($message, $command);
        }
    }

    private function handleCreationFailure(
        IncomingMessage $message,
        CreateShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
    ): void {
        $shipmentId = (string) $command->shipmentId;

        $this->idempotency->storeCreateRecord(
            $shipmentId,
            $message->headers->idempotencyKey,
            $this->idempotency->creationFailureFingerprint($shipmentId, $failureCode),
        );

        $this->eventPublisher->publishShipmentCreationFailed(
            $message,
            $command,
            $failureCode,
            $failureMessage,
        );
    }

    private function replayCreationFailure(IncomingMessage $message, CreateShipmentCommand $command): void
    {
        $failedEvent = $this->publishedEventStore->find(
            PublishedEventRecord::OPERATION_SHIPMENT_CREATION_FAILED,
            (string) $command->shipmentId,
            $message->headers->idempotencyKey,
        );

        if ($failedEvent === null) {
            throw new InvalidShipmentStateException('Missing stored creation failure for idempotent replay');
        }

        $this->eventPublisher->publishShipmentCreationFailed(
            $message,
            $command,
            (string) $failedEvent->payload['failure_code'],
            (string) $failedEvent->payload['failure_message'],
        );
    }

    private function replayCreateEvents(IncomingMessage $message, Shipment $shipment): void
    {
        $this->eventPublisher->publishShipmentCreated($message, $shipment);
        $this->eventPublisher->publishShipmentStatusChanged(
            $message,
            $shipment,
            ShipmentStatus::Created,
            'label_generated',
        );
    }

    private function recordProcessed(
        IncomingMessage $message,
        string $outcome,
        int $startedAt,
        bool $idempotentReplay = false,
    ): void {
        $this->metricsRecorder->recordRequestProcessed(
            operation: 'shipment_create',
            routingKey: $message->routingKey,
            outcome: $outcome,
            durationSeconds: (hrtime(true) - $startedAt) / 1_000_000_000,
            idempotentReplay: $idempotentReplay,
        );
    }
}
