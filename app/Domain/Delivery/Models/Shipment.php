<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentTransitionException;
use App\Domain\Delivery\Support\PrefixedIdGenerator;

final class Shipment
{
    /** @param list<ShipmentStatusHistory> $statusHistory */
    private function __construct(
        private readonly string $shipmentId,
        private readonly string $orderId,
        private ShipmentStatus $status,
        private readonly DeliveryAddress $deliveryAddress,
        private readonly CarrierProfile $carrierProfile,
        private array $statusHistory,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        string $orderId,
        DeliveryAddress $deliveryAddress,
        CarrierProfile $carrierProfile,
        \DateTimeImmutable $occurredAt,
        ?string $shipmentId = null,
    ): self {
        $resolvedShipmentId = $shipmentId ?? PrefixedIdGenerator::generate('shp');

        $history = new ShipmentStatusHistory(
            historyId: PrefixedIdGenerator::generate('shh'),
            shipmentId: $resolvedShipmentId,
            fromStatus: null,
            toStatus: ShipmentStatus::Created,
            occurredAt: $occurredAt,
            reason: 'shipment_created',
        );

        return new self(
            shipmentId: $resolvedShipmentId,
            orderId: $orderId,
            status: ShipmentStatus::Created,
            deliveryAddress: $deliveryAddress,
            carrierProfile: $carrierProfile,
            statusHistory: [$history],
            createdAt: $occurredAt,
            updatedAt: $occurredAt,
        );
    }

    public function shipmentId(): string
    {
        return $this->shipmentId;
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function status(): ShipmentStatus
    {
        return $this->status;
    }

    public function deliveryAddress(): DeliveryAddress
    {
        return $this->deliveryAddress;
    }

    public function carrierProfile(): CarrierProfile
    {
        return $this->carrierProfile;
    }

    /**
     * @return list<ShipmentStatusHistory>
     */
    public function statusHistory(): array
    {
        return $this->statusHistory;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function transitionTo(
        ShipmentStatus $nextStatus,
        \DateTimeImmutable $occurredAt,
        ?string $reason = null,
    ): void {
        if (! $this->status->canTransitionTo($nextStatus)) {
            throw InvalidShipmentTransitionException::fromTo(
                $this->shipmentId,
                $this->status,
                $nextStatus,
            );
        }

        if ($this->status === $nextStatus) {
            return;
        }

        $this->statusHistory[] = new ShipmentStatusHistory(
            historyId: PrefixedIdGenerator::generate('shh'),
            shipmentId: $this->shipmentId,
            fromStatus: $this->status,
            toStatus: $nextStatus,
            occurredAt: $occurredAt,
            reason: $reason,
        );

        $this->status = $nextStatus;
        $this->updatedAt = $occurredAt;
    }
}
