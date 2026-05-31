<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Repositories;

use App\Domain\Delivery\Models\Shipment;

interface ShipmentRepository
{
    public function save(Shipment $shipment): void;

    public function findById(string $shipmentId): ?Shipment;

    /**
     * @return list<Shipment>
     */
    public function all(): array;
    public function clear(): void;
}
