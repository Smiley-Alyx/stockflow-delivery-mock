<?php

declare(strict_types=1);

namespace Tests\Support\Messaging;

use App\Application\Handlers\ShipmentCancelRequestedHandler;
use App\Application\Handlers\ShipmentMessageDispatcher;
use App\Application\Handlers\ShipmentRequestedHandler;
use App\Application\Mappers\ShipmentEventPayloadMapper;
use App\Application\Mappers\ShipmentMessageMapper;
use App\Domain\Delivery\Services\Idempotency\ShipmentIdempotencyService;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Infrastructure\Messaging\RabbitMq\IdempotentDeliveryEventPublisher;
use App\Infrastructure\Messaging\RabbitMq\OutgoingMessageHeadersFactory;
use App\Infrastructure\Messaging\RabbitMq\PublishedEventStore;
use App\Infrastructure\Persistence\InMemoryIdempotencyRecordRepository;
use App\Infrastructure\Persistence\InMemoryPublishedEventRecordRepository;
use App\Infrastructure\Persistence\InMemoryShipmentRepository;

final class IdempotentShipmentMessageDispatcherFactory
{
    public static function create(
        ?InMemoryShipmentRepository $repository = null,
    ): array {
        $repository ??= new InMemoryShipmentRepository();
        $shipments = new ShipmentLifecycleService($repository);
        $mapper = new ShipmentMessageMapper();
        $recordingPublisher = new RecordingRabbitMqMessagePublisher();
        $idempotencyRecords = new InMemoryIdempotencyRecordRepository();
        $publishedEventRecords = new InMemoryPublishedEventRecordRepository();
        $idempotency = new ShipmentIdempotencyService($idempotencyRecords);
        $publishedEventStore = new PublishedEventStore($publishedEventRecords);
        $eventPublisher = new IdempotentDeliveryEventPublisher(
            new ShipmentEventPayloadMapper(new OutgoingMessageHeadersFactory('stockflow-delivery-mock')),
            $recordingPublisher,
            $publishedEventStore,
        );

        $dispatcher = new ShipmentMessageDispatcher(
            new ShipmentRequestedHandler($mapper, $shipments, $eventPublisher, $idempotency),
            new ShipmentCancelRequestedHandler(
                $mapper,
                $shipments,
                $eventPublisher,
                $idempotency,
                $publishedEventStore,
            ),
        );

        return [$dispatcher, $shipments, $recordingPublisher, $idempotencyRecords, $publishedEventRecords];
    }
}
