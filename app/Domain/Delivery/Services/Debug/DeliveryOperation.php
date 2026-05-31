<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services\Debug;

enum DeliveryOperation: string
{
    case ShipmentCreate = 'shipment_create';
    case ShipmentCancel = 'shipment_cancel';
}
