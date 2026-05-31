<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services;

use App\Domain\Delivery\Repositories\ShipmentRepository;

final class DemoResetService
{
    public function __construct(
        private readonly ShipmentRepository $shipments,
    ) {
    }

    public function reset(): void
    {
        $this->shipments->clear();
    }
}
