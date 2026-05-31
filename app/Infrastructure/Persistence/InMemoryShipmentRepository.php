<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Delivery\Models\Shipment;
use App\Domain\Delivery\Repositories\ShipmentRepository;

final class InMemoryShipmentRepository implements ShipmentRepository
{
    /** @var array<string, Shipment> */
    private array $shipments = [];

    public function save(Shipment $shipment): void
    {
        $this->shipments[$shipment->shipmentId()] = $shipment;
    }

    public function findById(string $shipmentId): ?Shipment
    {
        return $this->shipments[$shipmentId] ?? null;
    }

    public function all(): array
    {
        return array_values($this->shipments);
    }

    public function clear(): void
    {
        $this->shipments = [];
    }
}
