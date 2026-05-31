<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Mappers;

use App\Application\Mappers\ShipmentEventPayloadMapper;
use App\Domain\Delivery\DTO\CancelShipmentCommand;
use App\Domain\Delivery\DTO\CreateShipmentCommand;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Infrastructure\Messaging\RabbitMq\OutgoingMessageHeadersFactory;
use PHPUnit\Framework\TestCase;
use Tests\Support\DeliveryTestFixtures;
use Tests\Support\Messaging\BuildsDeliveryMessages;

final class ShipmentEventPayloadMapperTest extends TestCase
{
    use BuildsDeliveryMessages;

    private ShipmentEventPayloadMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new ShipmentEventPayloadMapper(
            new OutgoingMessageHeadersFactory('stockflow-delivery-mock'),
        );
    }

    public function test_maps_shipment_created_event(): void
    {
        $shipment = DeliveryTestFixtures::createShipmentCommand()->shipmentId !== null
            ? \App\Domain\Delivery\Models\Shipment::create(
                orderId: 'ord_map_001',
                deliveryAddress: DeliveryTestFixtures::deliveryAddress(),
                carrierProfile: DeliveryTestFixtures::carrierProfile(),
                occurredAt: new \DateTimeImmutable('2026-05-31T10:20:01Z'),
                shipmentId: 'shp_map_001',
            )
            : throw new \RuntimeException('missing shipment id');

        $event = $this->mapper->shipmentCreated(
            $this->incoming('delivery.shipment.requested.v1', []),
            $shipment,
        );

        $this->assertSame('delivery.shipment.created.v1', $event->routingKey);
        $this->assertSame('cor_test_001', $event->headers->correlationId);
        $this->assertSame('msg_test_001', $event->headers->causationId);
        $this->assertSame('shp_map_001', $event->payload['shipment_id']);
        $this->assertSame('TRK-SHPMAP001', $event->payload['tracking_number']);
    }

    public function test_maps_shipment_creation_failed_event(): void
    {
        $event = $this->mapper->shipmentCreationFailed(
            $this->incoming('delivery.shipment.requested.v1', []),
            DeliveryTestFixtures::createShipmentCommand(shipmentId: 'shp_map_002'),
            'address_invalid',
            'Delivery address could not be validated for carrier routing.',
        );

        $this->assertSame('delivery.shipment.creation_failed.v1', $event->routingKey);
        $this->assertSame('address_invalid', $event->payload['failure_code']);
    }

    public function test_maps_shipment_status_changed_event(): void
    {
        $shipment = \App\Domain\Delivery\Models\Shipment::create(
            orderId: 'ord_map_001',
            deliveryAddress: DeliveryTestFixtures::deliveryAddress(),
            carrierProfile: DeliveryTestFixtures::carrierProfile(),
            occurredAt: new \DateTimeImmutable('2026-05-31T10:25:00Z'),
            shipmentId: 'shp_map_001',
        );
        $shipment->transitionTo(ShipmentStatus::LabelGenerated, new \DateTimeImmutable('2026-05-31T10:25:00Z'));

        $event = $this->mapper->shipmentStatusChanged(
            $this->incoming('delivery.shipment.requested.v1', []),
            $shipment,
            ShipmentStatus::Created,
            'label_generated',
        );

        $this->assertSame('delivery.shipment.status_changed.v1', $event->routingKey);
        $this->assertSame('created', $event->payload['previous_status']);
        $this->assertSame('label_generated', $event->payload['current_status']);
        $this->assertSame('idem-shp-status-shp_map_001-label_generated', $event->headers->idempotencyKey);
    }

    public function test_maps_shipment_cancelled_event(): void
    {
        $shipment = \App\Domain\Delivery\Models\Shipment::create(
            orderId: 'ord_map_001',
            deliveryAddress: DeliveryTestFixtures::deliveryAddress(),
            carrierProfile: DeliveryTestFixtures::carrierProfile(),
            occurredAt: new \DateTimeImmutable('2026-05-31T11:00:01Z'),
            shipmentId: 'shp_map_001',
        );
        $shipment->transitionTo(
            ShipmentStatus::Cancelled,
            new \DateTimeImmutable('2026-05-31T11:00:01Z'),
            'customer_cancelled',
        );

        $event = $this->mapper->shipmentCancelled(
            $this->incoming('delivery.shipment.cancel_requested.v1', []),
            $shipment,
        );

        $this->assertSame('delivery.shipment.cancelled.v1', $event->routingKey);
        $this->assertSame('cancelled', $event->payload['status']);
        $this->assertSame('customer_cancelled', $event->payload['reason']);
    }

    public function test_maps_shipment_cancel_failed_event(): void
    {
        $event = $this->mapper->shipmentCancelFailed(
            $this->incoming('delivery.shipment.cancel_requested.v1', []),
            new CancelShipmentCommand(
                shipmentId: 'shp_map_003',
                orderId: 'ord_map_003',
                occurredAt: new \DateTimeImmutable('2026-05-31T12:00:01Z'),
                reason: 'customer_cancelled',
                idempotencyKey: 'idem_test_001',
                messageId: 'msg_test_001',
                correlationId: 'cor_test_001',
            ),
            'shipment_already_in_transit',
            'Shipment is already in transit and cannot be cancelled.',
            ShipmentStatus::InTransit,
        );

        $this->assertSame('delivery.shipment.cancel_failed.v1', $event->routingKey);
        $this->assertSame('in_transit', $event->payload['current_status']);
    }
}
