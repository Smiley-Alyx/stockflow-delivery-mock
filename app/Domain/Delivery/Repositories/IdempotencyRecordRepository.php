<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Repositories;

use App\Domain\Delivery\Models\IdempotencyRecord;

interface IdempotencyRecordRepository
{
    public function find(string $operation, string $shipmentId, string $idempotencyKey): ?IdempotencyRecord;

    public function store(IdempotencyRecord $record): IdempotencyRecord;

    public function clear(): void;
}
