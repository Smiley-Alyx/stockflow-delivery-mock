<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Delivery\Models\PublishedEventRecord;
use App\Domain\Delivery\Repositories\PublishedEventRecordRepository;

final class InMemoryPublishedEventRecordRepository implements PublishedEventRecordRepository
{
    /** @var array<string, PublishedEventRecord> */
    private array $records = [];

    public function find(string $operation, string $shipmentId, string $idempotencyKey): ?PublishedEventRecord
    {
        return $this->records[$this->key($operation, $shipmentId, $idempotencyKey)] ?? null;
    }

    public function store(PublishedEventRecord $record): PublishedEventRecord
    {
        $key = $this->key($record->operation, $record->shipmentId, $record->idempotencyKey);

        if (isset($this->records[$key])) {
            return $this->records[$key];
        }

        $this->records[$key] = $record;

        return $record;
    }

    public function clear(): void
    {
        $this->records = [];
    }

    public function countForShipment(string $shipmentId, ?string $operation = null): int
    {
        return count(array_filter(
            $this->records,
            static fn (PublishedEventRecord $record): bool => $record->shipmentId === $shipmentId
                && ($operation === null || $record->operation === $operation),
        ));
    }

    private function key(string $operation, string $shipmentId, string $idempotencyKey): string
    {
        return implode('|', [$operation, $shipmentId, $idempotencyKey]);
    }
}
