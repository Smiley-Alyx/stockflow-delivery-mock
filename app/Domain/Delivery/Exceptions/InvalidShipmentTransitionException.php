<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use App\Domain\Delivery\Enums\ShipmentStatus;

final class InvalidShipmentTransitionException extends \DomainException
{
    public static function fromTo(string $shipmentId, ShipmentStatus $from, ShipmentStatus $to): self
    {
        return new self(sprintf(
            'Cannot transition shipment %s from %s to %s',
            $shipmentId,
            $from->value,
            $to->value,
        ));
    }
}
