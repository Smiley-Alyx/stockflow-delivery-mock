<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Messaging;

use App\Application\Mappers\ShipmentEventPayloadMapper;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\PublishedEventRecord;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\PublishedEventConflictException;
use App\Infrastructure\Messaging\RabbitMq\IdempotentDeliveryEventPublisher;
use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;
use App\Infrastructure\Messaging\RabbitMq\MessageHeaders;
use App\Infrastructure\Messaging\RabbitMq\OutgoingMessageHeadersFactory;
use App\Infrastructure\Messaging\RabbitMq\PublishedEventStore;
use App\Infrastructure\Persistence\InMemoryPublishedEventRecordRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Debug\TestFailureModeSupport;
use Tests\Support\DeliveryTestFixtures;
use Tests\Support\Messaging\RecordingRabbitMqMessagePublisher;

final class IdempotentDeliveryEventPublisherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestFailureModeSupport::resetStateFile();
    }

    public function test_stores_event_on_first_publish(): void
    {
        $recording = new RecordingRabbitMqMessagePublisher();
        $repository = new InMemoryPublishedEventRecordRepository();
        $publisher = $this->publisher($recording, $repository);
        $incoming = $this->incoming();
        $shipment = DeliveryTestFixtures::lifecycleService()->create(DeliveryTestFixtures::createShipmentCommand());

        $publisher->publishShipmentCreated($incoming, $shipment);

        $this->assertCount(1, $recording->published);
        $this->assertSame(1, $repository->countForShipment('shp_test_001'));
    }

    public function test_replays_identical_outbound_event_on_duplicate_request(): void
    {
        $recording = new RecordingRabbitMqMessagePublisher();
        $repository = new InMemoryPublishedEventRecordRepository();
        $publisher = $this->publisher($recording, $repository);
        $incoming = $this->incoming();
        $shipment = DeliveryTestFixtures::lifecycleService()->create(DeliveryTestFixtures::createShipmentCommand());

        $publisher->publishShipmentCreated($incoming, $shipment);
        $publisher->publishShipmentCreated($incoming, $shipment);

        $this->assertCount(2, $recording->published);
        $this->assertSame(
            $recording->published[0]->headers->messageId,
            $recording->published[1]->headers->messageId,
        );
        $this->assertSame($recording->published[0]->payload, $recording->published[1]->payload);
        $this->assertSame(1, $repository->countForShipment(
            'shp_test_001',
            PublishedEventRecord::OPERATION_SHIPMENT_CREATED,
        ));
    }

    public function test_rejects_conflicting_outbound_fingerprint(): void
    {
        $recording = new RecordingRabbitMqMessagePublisher();
        $repository = new InMemoryPublishedEventRecordRepository();
        $publisher = $this->publisher($recording, $repository);
        $incoming = $this->incoming();
        $shipments = DeliveryTestFixtures::lifecycleService();
        $created = $shipments->create(DeliveryTestFixtures::createShipmentCommand());
        $shipments->advanceStatus('shp_test_001', new \DateTimeImmutable('2026-05-31T10:01:00Z'), 'label_generated');
        $labelGenerated = $shipments->get('shp_test_001');

        $publisher->publishShipmentStatusChanged(
            $incoming,
            $labelGenerated,
            ShipmentStatus::Created,
            'label_generated',
        );

        $this->expectException(PublishedEventConflictException::class);

        $publisher->publishShipmentStatusChanged(
            $incoming,
            $labelGenerated,
            ShipmentStatus::Created,
            'manual_override',
        );
    }

    public function test_duplicate_response_mode_publishes_event_twice(): void
    {
        $manager = TestFailureModeSupport::manager();
        $manager->set(\App\Domain\Delivery\Enums\FailureMode::DuplicateResponse);

        $recording = new RecordingRabbitMqMessagePublisher();
        $repository = new InMemoryPublishedEventRecordRepository();
        $publisher = new IdempotentDeliveryEventPublisher(
            new ShipmentEventPayloadMapper(new OutgoingMessageHeadersFactory('stockflow-delivery-mock')),
            $recording,
            new PublishedEventStore($repository),
            TestFailureModeSupport::simulator($manager),
        );

        $publisher->publishShipmentCreated($this->incoming(), DeliveryTestFixtures::lifecycleService()->create(DeliveryTestFixtures::createShipmentCommand()));

        $this->assertCount(2, $recording->published);
        $this->assertSame($recording->published[0]->headers->messageId, $recording->published[1]->headers->messageId);
    }

    private function publisher(
        RecordingRabbitMqMessagePublisher $recording,
        InMemoryPublishedEventRecordRepository $repository,
    ): IdempotentDeliveryEventPublisher {
        return new IdempotentDeliveryEventPublisher(
            new ShipmentEventPayloadMapper(new OutgoingMessageHeadersFactory('stockflow-delivery-mock')),
            $recording,
            new PublishedEventStore($repository),
            TestFailureModeSupport::simulator(),
        );
    }

    private function incoming(): IncomingMessage
    {
        return new IncomingMessage(
            routingKey: 'delivery.shipment.requested.v1',
            headers: new MessageHeaders(
                messageId: 'msg_req_001',
                correlationId: 'cor_checkout_001',
                causationId: 'msg_parent_001',
                idempotencyKey: 'idem_shp_create_001',
                schemaVersion: 'v1',
                occurredAt: '2026-05-31T10:15:00Z',
                producer: 'stockflow-market',
            ),
            payload: [],
            body: '{}',
        );
    }
}
