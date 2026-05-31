<?php

declare(strict_types=1);

namespace App\Application\Mappers;

use App\Domain\Delivery\Models\Shipment;
use App\Domain\Delivery\Models\ShipmentStatusHistory;

final class ShipmentMapper
{
    /**
     * @return array{
     *     shipment_id: string,
     *     order_id: string,
     *     status: string,
     *     carrier_code: string,
     *     service_level: string,
     *     created_at: string,
     *     updated_at: string
     * }
     */
    public static function summary(Shipment $shipment): array
    {
        return [
            'shipment_id' => $shipment->shipmentId(),
            'order_id' => $shipment->orderId(),
            'status' => $shipment->status()->value,
            'carrier_code' => $shipment->carrierProfile()->carrierCode,
            'service_level' => $shipment->carrierProfile()->serviceLevel,
            'created_at' => $shipment->createdAt()->format(DATE_ATOM),
            'updated_at' => $shipment->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Shipment $shipment): array
    {
        return [
            ...self::summary($shipment),
            'delivery_address' => $shipment->deliveryAddress()->toArray(),
            'carrier_profile' => $shipment->carrierProfile()->toArray(),
            'status_history' => array_map(
                static fn (ShipmentStatusHistory $history): array => $history->toArray(),
                $shipment->statusHistory(),
            ),
        ];
    }
}
