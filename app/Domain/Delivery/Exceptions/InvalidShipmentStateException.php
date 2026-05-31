<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use App\Domain\Delivery\Models\Shipment;

final class InvalidShipmentStateException extends \DomainException
{
    public static function cannotAdvance(Shipment $shipment): self
    {
        return new self(sprintf(
            'Shipment %s in status %s cannot be advanced automatically.',
            $shipment->shipmentId(),
            $shipment->status()->value,
        ));
    }

    public static function cannotCancel(Shipment $shipment): self
    {
        return new self(sprintf(
            'Shipment %s in status %s cannot be cancelled.',
            $shipment->shipmentId(),
            $shipment->status()->value,
        ));
    }

    public static function duplicateShipmentId(string $shipmentId): self
    {
        return new self(sprintf('Shipment %s already exists.', $shipmentId));
    }

    public static function cannotMarkDelivered(Shipment $shipment): self
    {
        return new self(sprintf(
            'Shipment %s in status %s cannot be marked as delivered.',
            $shipment->shipmentId(),
            $shipment->status()->value,
        ));
    }

    public static function cannotMarkFailed(Shipment $shipment): self
    {
        return new self(sprintf(
            'Shipment %s in status %s cannot be marked as failed.',
            $shipment->shipmentId(),
            $shipment->status()->value,
        ));
    }
}
