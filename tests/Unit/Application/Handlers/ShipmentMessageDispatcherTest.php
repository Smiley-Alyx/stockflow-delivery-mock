<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handlers;

use App\Application\Handlers\ShipmentCancelRequestedHandler;
use App\Application\Handlers\ShipmentMessageDispatcher;
use App\Application\Handlers\ShipmentRequestedHandler;
use App\Application\Mappers\ShipmentMessageMapper;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use App\Infrastructure\Persistence\InMemoryShipmentRepository;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use PHPUnit\Framework\TestCase;
use Tests\Support\DeliveryTestFixtures;
use Tests\Support\Messaging\BuildsDeliveryMessages;

final class ShipmentMessageDispatcherTest extends TestCase
{
    use BuildsDeliveryMessages;

    private ShipmentLifecycleService $shipments;

    private ShipmentMessageDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = new InMemoryShipmentRepository();
        $this->shipments = new ShipmentLifecycleService($repository);
        $mapper = new ShipmentMessageMapper();

        $this->dispatcher = new ShipmentMessageDispatcher(
            new ShipmentRequestedHandler($mapper, $this->shipments),
            new ShipmentCancelRequestedHandler($mapper, $this->shipments),
        );
    }

    public function test_dispatches_shipment_requested_message(): void
    {
        $this->dispatcher->dispatch($this->incoming('delivery.shipment.requested.v1', [
            'shipment_id' => 'shp_dispatch_001',
            'order_id' => 'ord_dispatch_001',
            'delivery_address' => DeliveryTestFixtures::deliveryAddress()->toArray(),
            'carrier_profile' => DeliveryTestFixtures::carrierProfile()->toArray(),
        ]));

        $this->assertSame(ShipmentStatus::Created, $this->shipments->get('shp_dispatch_001')->status());
    }

    public function test_dispatches_shipment_cancel_requested_message(): void
    {
        $this->shipments->create(DeliveryTestFixtures::createShipmentCommand(
            orderId: 'ord_dispatch_001',
            shipmentId: 'shp_dispatch_001',
        ));

        $this->dispatcher->dispatch($this->incoming('delivery.shipment.cancel_requested.v1', [
            'shipment_id' => 'shp_dispatch_001',
            'order_id' => 'ord_dispatch_001',
            'reason' => 'customer_cancelled',
        ]));

        $this->assertSame(ShipmentStatus::Cancelled, $this->shipments->get('shp_dispatch_001')->status());
    }

    public function test_rejects_unsupported_routing_key(): void
    {
        $this->expectException(InvalidMessageException::class);

        $this->dispatcher->dispatch($this->incoming('delivery.shipment.created.v1', []));
    }
}
