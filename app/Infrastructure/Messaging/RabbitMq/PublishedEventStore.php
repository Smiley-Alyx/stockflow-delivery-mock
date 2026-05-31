<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Domain\Delivery\Models\PublishedEventRecord;
use App\Domain\Delivery\Repositories\PublishedEventRecordRepository;
use App\Domain\Delivery\Support\PrefixedIdGenerator;

final class PublishedEventStore
{
    public function __construct(
        private readonly PublishedEventRecordRepository $records,
    ) {
    }

    public function find(string $operation, string $shipmentId, string $idempotencyKey): ?PublishedEventRecord
    {
        return $this->records->find($operation, $shipmentId, $idempotencyKey);
    }

    public function store(
        string $operation,
        string $shipmentId,
        string $idempotencyKey,
        PublishedDeliveryEvent $event,
        string $responseFingerprint,
    ): PublishedEventRecord {
        return $this->records->store(new PublishedEventRecord(
            recordId: PrefixedIdGenerator::generate('pev'),
            operation: $operation,
            shipmentId: $shipmentId,
            idempotencyKey: $idempotencyKey,
            routingKey: $event->routingKey,
            headers: $event->headers,
            payload: $event->payload,
            responseFingerprint: $responseFingerprint,
        ));
    }

    public function toPublishedEvent(PublishedEventRecord $record): PublishedDeliveryEvent
    {
        return new PublishedDeliveryEvent(
            routingKey: $record->routingKey,
            headers: $record->headers,
            payload: $record->payload,
        );
    }
}
