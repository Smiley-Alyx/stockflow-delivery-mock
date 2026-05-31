<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services;

use App\Domain\Delivery\Repositories\IdempotencyRecordRepository;
use App\Domain\Delivery\Repositories\PublishedEventRecordRepository;
use App\Domain\Delivery\Repositories\ShipmentRepository;

final class DemoResetService
{
    public function __construct(
        private readonly ShipmentRepository $shipments,
        private readonly IdempotencyRecordRepository $idempotencyRecords,
        private readonly PublishedEventRecordRepository $publishedEvents,
    ) {
    }

    public function reset(): void
    {
        $this->shipments->clear();
        $this->idempotencyRecords->clear();
        $this->publishedEvents->clear();
    }
}
