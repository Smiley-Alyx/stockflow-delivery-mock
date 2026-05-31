<?php

declare(strict_types=1);

namespace App\Domain\Delivery\DTO;

use App\Domain\Delivery\Models\CarrierProfile;
use App\Domain\Delivery\Models\DeliveryAddress;

final readonly class CreateShipmentCommand
{
    public function __construct(
        public string $orderId,
        public DeliveryAddress $deliveryAddress,
        public CarrierProfile $carrierProfile,
        public \DateTimeImmutable $occurredAt,
        public ?string $shipmentId = null,
    ) {
    }
}
