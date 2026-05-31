<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Delivery\Models\IdempotencyRecord;
use App\Domain\Delivery\Repositories\IdempotencyRecordRepository;

final class InMemoryIdempotencyRecordRepository implements IdempotencyRecordRepository
{
    /** @var array<string, IdempotencyRecord> */
    private array $records = [];

    public function find(string $operation, string $shipmentId, string $idempotencyKey): ?IdempotencyRecord
    {
        return $this->records[$this->key($operation, $shipmentId, $idempotencyKey)] ?? null;
    }

    public function store(IdempotencyRecord $record): IdempotencyRecord
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

    private function key(string $operation, string $shipmentId, string $idempotencyKey): string
    {
        return implode('|', [$operation, $shipmentId, $idempotencyKey]);
    }
}
