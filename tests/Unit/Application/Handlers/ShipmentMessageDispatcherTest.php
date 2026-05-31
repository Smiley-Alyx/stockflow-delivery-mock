<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handlers;

use App\Application\Handlers\ShipmentMessageDispatcher;
use App\Domain\Delivery\Enums\ShipmentStatus;
use PHPUnit\Framework\TestCase;
use Tests\Support\DeliveryTestFixtures;
use Tests\Support\Messaging\BuildsDeliveryMessages;
use Tests\Support\Messaging\IdempotentShipmentMessageDispatcherFactory;

final class ShipmentMessageDispatcherTest extends TestCase
{
    use BuildsDeliveryMessages;

    private \App\Domain\Delivery\Services\ShipmentLifecycleService $shipments;

    private ShipmentMessageDispatcher $dispatcher;

    private \Tests\Support\Messaging\RecordingRabbitMqMessagePublisher $recordingPublisher;

    protected function setUp(): void
    {
        parent::setUp();

        [
            $this->dispatcher,
            $this->shipments,
            $this->recordingPublisher,
        ] = IdempotentShipmentMessageDispatcherFactory::create();
    }

    public function test_dispatches_shipment_requested_message_and_publishes_result_events(): void
    {
        $this->dispatcher->dispatch($this->incoming('delivery.shipment.requested.v1', [
            'shipment_id' => 'shp_dispatch_001',
            'order_id' => 'ord_dispatch_001',
            'delivery_address' => DeliveryTestFixtures::deliveryAddress()->toArray(),
            'carrier_profile' => DeliveryTestFixtures::carrierProfile()->toArray(),
        ]));

        $this->assertSame(ShipmentStatus::LabelGenerated, $this->shipments->get('shp_dispatch_001')->status());
        $this->assertCount(2, $this->recordingPublisher->published);
        $this->assertSame('delivery.shipment.created.v1', $this->recordingPublisher->published[0]->routingKey);
        $this->assertSame('delivery.shipment.status_changed.v1', $this->recordingPublisher->published[1]->routingKey);
        $this->assertSame('cor_test_001', $this->recordingPublisher->published[0]->headers->correlationId);
        $this->assertSame('msg_test_001', $this->recordingPublisher->published[0]->headers->causationId);
    }

    public function test_dispatches_shipment_cancel_requested_message_and_publishes_cancelled_event(): void
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
        $this->assertSame('delivery.shipment.cancelled.v1', $this->recordingPublisher->published[0]->routingKey);
    }

    public function test_dispatches_cancel_failure_event_when_shipment_is_in_transit(): void
    {
        $this->shipments->create(DeliveryTestFixtures::createShipmentCommand(
            orderId: 'ord_dispatch_001',
            shipmentId: 'shp_dispatch_001',
        ));

        $occurredAt = new \DateTimeImmutable('2026-05-31T10:05:00Z');

        foreach (range(1, 3) as $step) {
            $this->shipments->advanceStatus('shp_dispatch_001', $occurredAt->modify("+{$step} minutes"));
        }

        $this->dispatcher->dispatch($this->incoming('delivery.shipment.cancel_requested.v1', [
            'shipment_id' => 'shp_dispatch_001',
            'order_id' => 'ord_dispatch_001',
        ]));

        $this->assertSame('delivery.shipment.cancel_failed.v1', $this->recordingPublisher->published[0]->routingKey);
        $this->assertSame('shipment_already_in_transit', $this->recordingPublisher->published[0]->payload['failure_code']);
    }
}
