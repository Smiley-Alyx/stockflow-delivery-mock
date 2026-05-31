<?php

declare(strict_types=1);

namespace Tests\Integration\Messaging;

use App\Domain\Delivery\Enums\FailureMode;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\IdempotencyRecord;
use App\Domain\Delivery\Models\PublishedEventRecord;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\PublishedEventConflictException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\TestCase;
use Tests\Support\DeliveryTestFixtures;
use Tests\Support\Messaging\BuildsDeliveryMessages;
use Tests\Support\Messaging\DeliveryMessagingIntegrationHarness;

final class ShipmentMessagingIntegrationTest extends TestCase
{
    use BuildsDeliveryMessages;

    private DeliveryMessagingIntegrationHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->harness = new DeliveryMessagingIntegrationHarness();
    }

    public function test_happy_path_create_shipment_publishes_lifecycle_events_and_records_metrics(): void
    {
        $payload = $this->shipmentRequestPayload('shp_happy_001', 'ord_happy_001');

        $this->harness->processIncoming($this->incoming(
            'delivery.shipment.requested.v1',
            $payload,
            idempotencyKey: 'idem-happy-create-1',
            messageId: 'msg_happy_create_1',
        ));

        $shipment = $this->harness->shipments->get('shp_happy_001');

        $this->assertSame(ShipmentStatus::LabelGenerated, $shipment->status());
        $this->assertCount(2, $this->harness->events->published);
        $this->assertSame('delivery.shipment.created.v1', $this->harness->events->published[0]->routingKey);
        $this->assertSame('delivery.shipment.status_changed.v1', $this->harness->events->published[1]->routingKey);
        $this->assertSame('label_generated', $this->harness->events->published[1]->payload['current_status']);
        $this->assertNotNull($this->harness->idempotencyRecords->find(
            IdempotencyRecord::OPERATION_CREATE,
            'shp_happy_001',
            'idem-happy-create-1',
        ));

        $metrics = $this->harness->metricsOutput();

        $this->assertStringContainsString('delivery_requests_total', $metrics);
        $this->assertStringContainsString('outcome="created"', $metrics);
        $this->assertStringContainsString('delivery_events_published_total', $metrics);
    }

    public function test_invalid_address_payload_is_rejected_by_consumer_without_creating_shipment(): void
    {
        $payload = $this->shipmentRequestPayload(
            shipmentId: 'shp_invalid_payload_001',
            orderId: 'ord_invalid_payload_001',
            deliveryAddress: array_merge(
                DeliveryTestFixtures::deliveryAddress()->toArray(),
                ['recipient_name' => ''],
            ),
        );

        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('basic_reject')->with(1, false);
        $channel->expects($this->never())->method('basic_ack');

        $this->harness->processLikeConsumer($this->incoming(
            'delivery.shipment.requested.v1',
            $payload,
            idempotencyKey: 'idem-invalid-payload-1',
        ), $channel);

        $this->assertSame([], $this->harness->events->published);
        $this->assertStringContainsString('delivery_invalid_messages_total', $this->harness->metricsOutput());

        $this->expectException(\App\Domain\Delivery\Exceptions\ShipmentNotFoundException::class);
        $this->harness->shipments->get('shp_invalid_payload_001');
    }

    public function test_invalid_address_failure_mode_publishes_creation_failed_event(): void
    {
        $this->harness->failureModeManager->set(FailureMode::InvalidAddress);

        $payload = $this->shipmentRequestPayload('shp_invalid_mode_001', 'ord_invalid_mode_001');

        $this->harness->processIncoming($this->incoming(
            'delivery.shipment.requested.v1',
            $payload,
            idempotencyKey: 'idem-invalid-mode-1',
        ));

        $this->assertCount(1, $this->harness->events->published);
        $this->assertSame('delivery.shipment.creation_failed.v1', $this->harness->events->published[0]->routingKey);
        $this->assertSame('address_invalid', $this->harness->events->published[0]->payload['failure_code']);

        $this->expectException(\App\Domain\Delivery\Exceptions\ShipmentNotFoundException::class);
        $this->harness->shipments->get('shp_invalid_mode_001');
    }

    public function test_duplicate_create_request_replays_events_without_duplicate_state(): void
    {
        $payload = $this->shipmentRequestPayload('shp_idem_integration_001', 'ord_idem_integration_001');

        $this->harness->processIncoming($this->incoming(
            'delivery.shipment.requested.v1',
            $payload,
            idempotencyKey: 'idem-integration-create-1',
            messageId: 'msg_idem_create_first',
        ));

        $this->harness->processIncoming($this->incoming(
            'delivery.shipment.requested.v1',
            $payload,
            idempotencyKey: 'idem-integration-create-1',
            messageId: 'msg_idem_create_retry',
        ));

        $this->assertSame(ShipmentStatus::LabelGenerated, $this->harness->shipments->get('shp_idem_integration_001')->status());
        $this->assertCount(4, $this->harness->events->published);
        $this->assertSame(
            $this->harness->events->published[0]->headers->messageId,
            $this->harness->events->published[2]->headers->messageId,
        );
        $this->assertStringContainsString('delivery_idempotent_replays_total', $this->harness->metricsOutput());
    }

    public function test_cancel_request_after_create_publishes_cancelled_event(): void
    {
        $shipmentId = 'shp_cancel_integration_001';
        $orderId = 'ord_cancel_integration_001';

        $this->harness->processIncoming($this->incoming(
            'delivery.shipment.requested.v1',
            $this->shipmentRequestPayload($shipmentId, $orderId),
            idempotencyKey: 'idem-cancel-flow-create-1',
        ));

        $this->harness->processIncoming($this->incoming(
            'delivery.shipment.cancel_requested.v1',
            $this->cancelRequestPayload($shipmentId, $orderId),
            idempotencyKey: 'idem-cancel-flow-cancel-1',
        ));

        $this->assertSame(ShipmentStatus::Cancelled, $this->harness->shipments->get($shipmentId)->status());
        $this->assertSame('delivery.shipment.cancelled.v1', $this->harness->events->published[2]->routingKey);
        $this->assertSame(1, $this->harness->publishedEventRecords->countForShipment(
            $shipmentId,
            PublishedEventRecord::OPERATION_SHIPMENT_CANCELLED,
        ));
    }

    public function test_cancel_failure_mode_publishes_cancel_failed_and_keeps_shipment_active(): void
    {
        $shipmentId = 'shp_cancel_fail_integration_001';
        $orderId = 'ord_cancel_fail_integration_001';

        $this->harness->processIncoming($this->incoming(
            'delivery.shipment.requested.v1',
            $this->shipmentRequestPayload($shipmentId, $orderId),
            idempotencyKey: 'idem-cancel-fail-create-1',
        ));

        $this->harness->failureModeManager->set(FailureMode::CancelFailure);

        $this->harness->processIncoming($this->incoming(
            'delivery.shipment.cancel_requested.v1',
            $this->cancelRequestPayload($shipmentId, $orderId),
            idempotencyKey: 'idem-cancel-fail-cancel-1',
        ));

        $this->assertSame(ShipmentStatus::LabelGenerated, $this->harness->shipments->get($shipmentId)->status());
        $this->assertSame('delivery.shipment.cancel_failed.v1', $this->harness->events->published[2]->routingKey);
    }

    public function test_transient_failure_schedules_retry_and_requeue_leads_to_successful_processing(): void
    {
        $payload = $this->shipmentRequestPayload('shp_retry_flow_001', 'ord_retry_flow_001');
        $incoming = $this->incoming(
            'delivery.shipment.requested.v1',
            $payload,
            idempotencyKey: 'idem-retry-flow-1',
        );

        $this->harness->failureModeManager->set(FailureMode::ProviderUnavailable);

        $consumerChannel = $this->createMock(AMQPChannel::class);
        $consumerChannel->expects($this->once())->method('basic_ack')->with(1);

        $this->harness->processLikeConsumer($incoming, $consumerChannel);

        $this->assertCount(1, $this->harness->retryPublisher->published);
        $this->assertSame(1, $this->harness->retryPublisher->published[0]['retryCount']);
        $this->assertSame([], $this->harness->dlqPublisher->published);
        $this->assertStringContainsString('delivery_request_retries_total', $this->harness->metricsOutput());

        $originalMessage = $this->harness->retryPublisher->published[0]['message'];
        $retryMessage = new AMQPMessage((string) $originalMessage->getBody(), [
            'content_type' => 'application/json',
            'application_headers' => new AMQPTable([
                'x-retry-count' => '1',
                'x-original-routing-key' => 'delivery.shipment.requested.v1',
            ]),
        ]);
        $retryMessage->setDeliveryInfo(2, false, '', 'stockflow.delivery.retry');

        $requeueChannel = $this->createMock(AMQPChannel::class);
        $requeueChannel->expects($this->once())
            ->method('basic_publish')
            ->with(
                $this->isInstanceOf(AMQPMessage::class),
                'stockflow.delivery',
                'delivery.shipment.requested.v1',
            );
        $requeueChannel->expects($this->once())->method('basic_ack')->with(2);

        $this->harness->retryRequeueHandler->handle($requeueChannel, $retryMessage);

        $this->assertStringContainsString('delivery_request_retry_requeues_total', $this->harness->metricsOutput());

        $this->harness->failureModeManager->set(FailureMode::Normal);
        $this->harness->processIncoming($incoming);

        $this->assertSame(ShipmentStatus::LabelGenerated, $this->harness->shipments->get('shp_retry_flow_001')->status());
        $this->assertCount(2, $this->harness->events->published);
    }

    public function test_exhausted_retries_move_message_to_dlq(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('basic_ack')->with(1);

        $message = new AMQPMessage('{}', [
            'application_headers' => new AMQPTable([
                'x-retry-count' => '3',
            ]),
        ]);
        $message->setDeliveryInfo(1, false, 'stockflow.delivery', 'delivery.shipment.requested.v1');

        $this->harness->failureHandler->handleProcessingFailure(
            $channel,
            $message,
            new \RuntimeException('still failing'),
        );

        $this->assertSame([], $this->harness->retryPublisher->published);
        $this->assertCount(1, $this->harness->dlqPublisher->published);
        $this->assertStringContainsString('delivery_request_dlq_total', $this->harness->metricsOutput());
    }

    public function test_non_retryable_processing_failure_moves_message_to_dlq(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('basic_ack')->with(1);

        $message = new AMQPMessage('{}');
        $message->setDeliveryInfo(1, false, 'stockflow.delivery', 'delivery.shipment.requested.v1');

        $this->harness->failureHandler->handleProcessingFailure(
            $channel,
            $message,
            PublishedEventConflictException::forOperation('delivery.shipment.created', 'shp_conflict_001', 'idem_conflict_001'),
        );

        $this->assertSame([], $this->harness->retryPublisher->published);
        $this->assertCount(1, $this->harness->dlqPublisher->published);
    }
}
