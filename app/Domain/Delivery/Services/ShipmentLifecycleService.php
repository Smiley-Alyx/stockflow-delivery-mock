<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services;

use App\Domain\Delivery\DTO\CreateShipmentCommand;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentStateException;
use App\Domain\Delivery\Exceptions\ShipmentNotFoundException;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Delivery\Repositories\ShipmentRepository;

final class ShipmentLifecycleService
{
    public function __construct(
        private readonly ShipmentRepository $shipments,
    ) {
    }

    public function create(CreateShipmentCommand $command): Shipment
    {
        if ($command->shipmentId !== null && $this->shipments->findById($command->shipmentId) !== null) {
            throw InvalidShipmentStateException::duplicateShipmentId($command->shipmentId);
        }

        $shipment = Shipment::create(
            orderId: $command->orderId,
            deliveryAddress: $command->deliveryAddress,
            carrierProfile: $command->carrierProfile,
            occurredAt: $command->occurredAt,
            shipmentId: $command->shipmentId,
        );

        $this->shipments->save($shipment);

        return $shipment;
    }

    public function get(string $shipmentId): Shipment
    {
        return $this->findOrFail($shipmentId);
    }

    public function advanceStatus(
        string $shipmentId,
        \DateTimeImmutable $occurredAt,
        ?string $reason = null,
    ): Shipment {
        $shipment = $this->findOrFail($shipmentId);
        $nextStatus = $shipment->status()->nextDefaultStatus();

        if ($nextStatus === null) {
            throw InvalidShipmentStateException::cannotAdvance($shipment);
        }

        $shipment->transitionTo(
            $nextStatus,
            $occurredAt,
            $reason ?? 'status_advanced',
        );

        $this->shipments->save($shipment);

        return $shipment;
    }

    public function cancel(
        string $shipmentId,
        \DateTimeImmutable $occurredAt,
        ?string $reason = null,
    ): Shipment {
        $shipment = $this->findOrFail($shipmentId);

        if (! $shipment->status()->canTransitionTo(ShipmentStatus::Cancelled)) {
            throw InvalidShipmentStateException::cannotCancel($shipment);
        }

        $shipment->transitionTo(
            ShipmentStatus::Cancelled,
            $occurredAt,
            $reason ?? 'shipment_cancelled',
        );

        $this->shipments->save($shipment);

        return $shipment;
    }

    /**
     * @return list<Shipment>
     */
    public function list(): array
    {
        return $this->shipments->all();
    }

    public function markDelivered(
        string $shipmentId,
        \DateTimeImmutable $occurredAt,
        ?string $reason = null,
    ): Shipment {
        $shipment = $this->findOrFail($shipmentId);

        if (! $shipment->status()->canTransitionTo(ShipmentStatus::Delivered)) {
            throw InvalidShipmentStateException::cannotMarkDelivered($shipment);
        }

        $shipment->transitionTo(
            ShipmentStatus::Delivered,
            $occurredAt,
            $reason ?? 'marked_delivered',
        );

        $this->shipments->save($shipment);

        return $shipment;
    }

    public function markFailed(
        string $shipmentId,
        \DateTimeImmutable $occurredAt,
        ?string $reason = null,
    ): Shipment {
        $shipment = $this->findOrFail($shipmentId);

        if (! $shipment->status()->canTransitionTo(ShipmentStatus::DeliveryFailed)) {
            throw InvalidShipmentStateException::cannotMarkFailed($shipment);
        }

        $shipment->transitionTo(
            ShipmentStatus::DeliveryFailed,
            $occurredAt,
            $reason ?? 'marked_failed',
        );

        $this->shipments->save($shipment);

        return $shipment;
    }

    private function findOrFail(string $shipmentId): Shipment
    {
        $shipment = $this->shipments->findById($shipmentId);

        if ($shipment === null) {
            throw ShipmentNotFoundException::forId($shipmentId);
        }

        return $shipment;
    }
}
