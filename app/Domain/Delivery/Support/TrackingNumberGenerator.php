<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Support;

final class TrackingNumberGenerator
{
    public static function forShipment(string $shipmentId): string
    {
        $normalized = strtoupper(str_replace('_', '', $shipmentId));

        return 'TRK-'.substr($normalized, -12);
    }
}
