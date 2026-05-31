<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

final class ShipmentNotFoundException extends \DomainException
{
    public static function forId(string $shipmentId): self
    {
        return new self(sprintf('Shipment %s was not found.', $shipmentId));
    }
}
