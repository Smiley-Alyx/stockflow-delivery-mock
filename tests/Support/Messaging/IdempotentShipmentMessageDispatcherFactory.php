<?php

declare(strict_types=1);

namespace Tests\Support\Messaging;

use App\Application\Handlers\ShipmentCancelRequestedHandler;
use App\Application\Handlers\ShipmentMessageDispatcher;
use App\Application\Handlers\ShipmentRequestedHandler;
use App\Application\Mappers\ShipmentEventPayloadMapper;
use App\Application\Mappers\ShipmentMessageMapper;
use App\Domain\Delivery\Services\Debug\DeliveryDegradationSimulator;
use App\Domain\Delivery\Services\Idempotency\ShipmentIdempotencyService;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Infrastructure\Messaging\RabbitMq\IdempotentDeliveryEventPublisher;
use App\Infrastructure\Messaging\RabbitMq\OutgoingMessageHeadersFactory;
use App\Infrastructure\Messaging\RabbitMq\PublishedEventStore;
use App\Infrastructure\Observability\DeliveryMetricsRecorder;
use App\Infrastructure\Persistence\InMemoryIdempotencyRecordRepository;
use App\Infrastructure\Persistence\InMemoryPublishedEventRecordRepository;
use App\Infrastructure\Persistence\InMemoryShipmentRepository;
use Tests\Support\Debug\TestFailureModeSupport;
use Tests\Support\Observability\TestMetricsSupport;

final class IdempotentShipmentMessageDispatcherFactory
{
    /**
     * @return array{
     *     0: ShipmentMessageDispatcher,
     *     1: ShipmentLifecycleService,
     *     2: RecordingRabbitMqMessagePublisher,
     *     3: InMemoryIdempotencyRecordRepository,
     *     4: InMemoryPublishedEventRecordRepository,
     *     5: DeliveryDegradationSimulator,
     *     6: DeliveryMetricsRecorder
     * }
     */
    public static function create(
        ?InMemoryShipmentRepository $repository = null,
        ?DeliveryDegradationSimulator $degradationSimulator = null,
        ?DeliveryMetricsRecorder $metricsRecorder = null,
    ): array {
        TestFailureModeSupport::resetStateFile();

        $repository ??= new InMemoryShipmentRepository();
        $shipments = new ShipmentLifecycleService($repository);
        $mapper = new ShipmentMessageMapper();
        $recordingPublisher = new RecordingRabbitMqMessagePublisher();
        $idempotencyRecords = new InMemoryIdempotencyRecordRepository();
        $publishedEventRecords = new InMemoryPublishedEventRecordRepository();
        $idempotency = new ShipmentIdempotencyService($idempotencyRecords);
        $publishedEventStore = new PublishedEventStore($publishedEventRecords);
        $degradationSimulator ??= TestFailureModeSupport::simulator();
        $metricsRecorder ??= TestMetricsSupport::recorder();
        $eventPublisher = new IdempotentDeliveryEventPublisher(
            new ShipmentEventPayloadMapper(new OutgoingMessageHeadersFactory('stockflow-delivery-mock')),
            $recordingPublisher,
            $publishedEventStore,
            $degradationSimulator,
            $metricsRecorder,
        );

        $dispatcher = new ShipmentMessageDispatcher(
            new ShipmentRequestedHandler(
                $mapper,
                $shipments,
                $eventPublisher,
                $idempotency,
                $degradationSimulator,
                $publishedEventStore,
                $metricsRecorder,
            ),
            new ShipmentCancelRequestedHandler(
                $mapper,
                $shipments,
                $eventPublisher,
                $idempotency,
                $publishedEventStore,
                $degradationSimulator,
                $metricsRecorder,
            ),
        );

        return [
            $dispatcher,
            $shipments,
            $recordingPublisher,
            $idempotencyRecords,
            $publishedEventRecords,
            $degradationSimulator,
            $metricsRecorder,
        ];
    }
}
