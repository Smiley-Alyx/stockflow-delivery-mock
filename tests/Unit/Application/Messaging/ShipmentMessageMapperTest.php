<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Messaging;

use App\Application\Mappers\ShipmentMessageMapper;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Messaging\BuildsDeliveryMessages;

final class ShipmentMessageMapperTest extends TestCase
{
    use BuildsDeliveryMessages;

    private ShipmentMessageMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new ShipmentMessageMapper();
    }

    public function test_maps_shipment_requested_payload(): void
    {
        $command = $this->mapper->toCreateShipmentCommand($this->incoming('delivery.shipment.requested.v1', [
            'shipment_id' => 'shp_map_001',
            'order_id' => 'ord_map_001',
            'delivery_address' => [
                'recipient_name' => 'Jane Doe',
                'country_code' => 'RU',
                'city' => 'Moscow',
                'postal_code' => '101000',
                'street_line1' => 'Red Square 1',
            ],
            'carrier_profile' => [
                'carrier_code' => 'stockflow-express',
                'service_level' => 'standard',
            ],
        ]));

        $this->assertSame('shp_map_001', $command->shipmentId);
        $this->assertSame('ord_map_001', $command->orderId);
        $this->assertSame('Moscow', $command->deliveryAddress->city);
    }

    public function test_maps_shipment_cancel_requested_payload(): void
    {
        $command = $this->mapper->toCancelShipmentCommand($this->incoming('delivery.shipment.cancel_requested.v1', [
            'shipment_id' => 'shp_map_001',
            'order_id' => 'ord_map_001',
            'reason' => 'customer_cancelled',
        ]));

        $this->assertSame('shp_map_001', $command->shipmentId);
        $this->assertSame('customer_cancelled', $command->reason);
        $this->assertSame('idem_test_001', $command->idempotencyKey);
    }

    public function test_rejects_invalid_shipment_request_payload(): void
    {
        $this->expectException(InvalidMessageException::class);

        $this->mapper->toCreateShipmentCommand(
            $this->incoming('delivery.shipment.requested.v1', ['shipment_id' => 'shp_map_001']),
        );
    }
}
