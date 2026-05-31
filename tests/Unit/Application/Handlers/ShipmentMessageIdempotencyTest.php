<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handlers;

use App\Application\Handlers\ShipmentMessageDispatcher;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\IdempotencyRecord;
use App\Domain\Delivery\Models\PublishedEventRecord;
use PHPUnit\Framework\TestCase;
use Tests\Support\DeliveryTestFixtures;
use Tests\Support\Messaging\BuildsDeliveryMessages;
use Tests\Support\Messaging\IdempotentShipmentMessageDispatcherFactory;

final class ShipmentMessageIdempotencyTest extends TestCase
{
    use BuildsDeliveryMessages;

    private ShipmentMessageDispatcher $dispatcher;

    private \App\Domain\Delivery\Services\ShipmentLifecycleService $shipments;

    private \Tests\Support\Messaging\RecordingRabbitMqMessagePublisher $recordingPublisher;

    private \App\Infrastructure\Persistence\InMemoryIdempotencyRecordRepository $idempotencyRecords;

    private \App\Infrastructure\Persistence\InMemoryPublishedEventRecordRepository $publishedEventRecords;

    protected function setUp(): void
    {
        parent::setUp();

        [
            $this->dispatcher,
            $this->shipments,
            $this->recordingPublisher,
            $this->idempotencyRecords,
            $this->publishedEventRecords,
        ] = IdempotentShipmentMessageDispatcherFactory::create();
    }

    public function test_duplicate_shipment_request_replays_same_outbound_events_without_duplicate_shipment(): void
    {
        $payload = [
            'shipment_id' => 'shp_idem_001',
            'order_id' => 'ord_idem_001',
            'delivery_address' => DeliveryTestFixtures::deliveryAddress()->toArray(),
            'carrier_profile' => DeliveryTestFixtures::carrierProfile()->toArray(),
        ];

        $this->dispatcher->dispatch($this->incoming(
            'delivery.shipment.requested.v1',
            $payload,
            idempotencyKey: 'idem-shp-create-1',
            messageId: 'msg_create_first',
        ));

        $this->dispatcher->dispatch($this->incoming(
            'delivery.shipment.requested.v1',
            $payload,
            idempotencyKey: 'idem-shp-create-1',
            messageId: 'msg_create_retry',
        ));

        $this->assertSame(ShipmentStatus::LabelGenerated, $this->shipments->get('shp_idem_001')->status());
        $this->assertCount(4, $this->recordingPublisher->published);
        $this->assertSame(
            $this->recordingPublisher->published[0]->headers->messageId,
            $this->recordingPublisher->published[2]->headers->messageId,
        );
        $this->assertSame(
            $this->recordingPublisher->published[1]->headers->messageId,
            $this->recordingPublisher->published[3]->headers->messageId,
        );
        $this->assertSame($this->recordingPublisher->published[0]->payload, $this->recordingPublisher->published[2]->payload);
        $this->assertSame($this->recordingPublisher->published[1]->payload, $this->recordingPublisher->published[3]->payload);
        $this->assertNotNull($this->idempotencyRecords->find(
            IdempotencyRecord::OPERATION_CREATE,
            'shp_idem_001',
            'idem-shp-create-1',
        ));
        $this->assertSame(2, $this->publishedEventRecords->countForShipment('shp_idem_001'));
    }

    public function test_duplicate_cancel_request_replays_same_outbound_event_without_double_cancel(): void
    {
        $this->shipments->create(DeliveryTestFixtures::createShipmentCommand(
            orderId: 'ord_idem_001',
            shipmentId: 'shp_idem_cancel_001',
        ));

        $payload = [
            'shipment_id' => 'shp_idem_cancel_001',
            'order_id' => 'ord_idem_001',
            'reason' => 'customer_cancelled',
        ];

        $this->dispatcher->dispatch($this->incoming(
            'delivery.shipment.cancel_requested.v1',
            $payload,
            idempotencyKey: 'idem-shp-cancel-1',
            messageId: 'msg_cancel_first',
        ));

        $this->dispatcher->dispatch($this->incoming(
            'delivery.shipment.cancel_requested.v1',
            $payload,
            idempotencyKey: 'idem-shp-cancel-1',
            messageId: 'msg_cancel_retry',
        ));

        $this->assertSame(ShipmentStatus::Cancelled, $this->shipments->get('shp_idem_cancel_001')->status());
        $this->assertCount(2, $this->recordingPublisher->published);
        $this->assertSame(
            $this->recordingPublisher->published[0]->headers->messageId,
            $this->recordingPublisher->published[1]->headers->messageId,
        );
        $this->assertSame('delivery.shipment.cancelled.v1', $this->recordingPublisher->published[0]->routingKey);
        $this->assertSame(1, $this->publishedEventRecords->countForShipment(
            'shp_idem_cancel_001',
            PublishedEventRecord::OPERATION_SHIPMENT_CANCELLED,
        ));
    }

    public function test_duplicate_cancel_failure_replays_same_outbound_event(): void
    {
        $this->shipments->create(DeliveryTestFixtures::createShipmentCommand(
            orderId: 'ord_idem_001',
            shipmentId: 'shp_idem_cancel_fail_001',
        ));

        $occurredAt = new \DateTimeImmutable('2026-05-31T10:05:00Z');

        foreach (range(1, 3) as $step) {
            $this->shipments->advanceStatus('shp_idem_cancel_fail_001', $occurredAt->modify("+{$step} minutes"));
        }

        $payload = [
            'shipment_id' => 'shp_idem_cancel_fail_001',
            'order_id' => 'ord_idem_001',
        ];

        $this->dispatcher->dispatch($this->incoming(
            'delivery.shipment.cancel_requested.v1',
            $payload,
            idempotencyKey: 'idem-shp-cancel-fail-1',
            messageId: 'msg_cancel_fail_first',
        ));

        $this->dispatcher->dispatch($this->incoming(
            'delivery.shipment.cancel_requested.v1',
            $payload,
            idempotencyKey: 'idem-shp-cancel-fail-1',
            messageId: 'msg_cancel_fail_retry',
        ));

        $this->assertSame(ShipmentStatus::InTransit, $this->shipments->get('shp_idem_cancel_fail_001')->status());
        $this->assertCount(2, $this->recordingPublisher->published);
        $this->assertSame(
            $this->recordingPublisher->published[0]->headers->messageId,
            $this->recordingPublisher->published[1]->headers->messageId,
        );
        $this->assertSame('delivery.shipment.cancel_failed.v1', $this->recordingPublisher->published[0]->routingKey);
        $this->assertSame(1, $this->publishedEventRecords->countForShipment(
            'shp_idem_cancel_fail_001',
            PublishedEventRecord::OPERATION_SHIPMENT_CANCEL_FAILED,
        ));
    }
}
