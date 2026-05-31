<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Repositories;

use App\Domain\Delivery\Models\PublishedEventRecord;

interface PublishedEventRecordRepository
{
    public function find(string $operation, string $shipmentId, string $idempotencyKey): ?PublishedEventRecord;

    public function store(PublishedEventRecord $record): PublishedEventRecord;

    public function clear(): void;
}
