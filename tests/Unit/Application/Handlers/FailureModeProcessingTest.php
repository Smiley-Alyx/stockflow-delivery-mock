<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handlers;

use App\Application\Handlers\ShipmentMessageDispatcher;
use App\Domain\Delivery\Enums\FailureMode;
use App\Domain\Delivery\Services\Debug\FailureModeManager;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\RetryableMessageException;
use App\Infrastructure\Persistence\InMemoryShipmentRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\Debug\TestFailureModeSupport;
use Tests\Support\DeliveryTestFixtures;
use Tests\Support\Messaging\BuildsDeliveryMessages;
use Tests\Support\Messaging\IdempotentShipmentMessageDispatcherFactory;

final class FailureModeProcessingTest extends TestCase
{
    use BuildsDeliveryMessages;

    private FailureModeManager $failureModeManager;

    private ShipmentMessageDispatcher $dispatcher;

    private \Tests\Support\Messaging\RecordingRabbitMqMessagePublisher $recordingPublisher;

    protected function setUp(): void
    {
        parent::setUp();

        TestFailureModeSupport::resetStateFile();
        $this->failureModeManager = TestFailureModeSupport::manager();

        [
            $this->dispatcher,
            ,
            $this->recordingPublisher,
        ] = IdempotentShipmentMessageDispatcherFactory::create(
            degradationSimulator: TestFailureModeSupport::simulator($this->failureModeManager),
        );
    }

    public function test_always_reject_creation_failure_mode_publishes_creation_failed_event(): void
    {
        $this->failureModeManager->set(FailureMode::AlwaysRejectCreation);

        $this->dispatcher->dispatch($this->incoming('delivery.shipment.requested.v1', [
            'shipment_id' => 'shp_failure_mode_001',
            'order_id' => 'ord_failure_mode_001',
            'delivery_address' => DeliveryTestFixtures::deliveryAddress()->toArray(),
            'carrier_profile' => DeliveryTestFixtures::carrierProfile()->toArray(),
        ], idempotencyKey: 'idem-failure-create-1'));

        $this->assertCount(1, $this->recordingPublisher->published);
        $this->assertSame('delivery.shipment.creation_failed.v1', $this->recordingPublisher->published[0]->routingKey);
        $this->assertSame('creation_failed', $this->recordingPublisher->published[0]->payload['failure_code']);
    }

    public function test_provider_unavailable_failure_mode_throws_retryable_exception(): void
    {
        $this->failureModeManager->set(FailureMode::ProviderUnavailable);

        $this->expectException(RetryableMessageException::class);

        $this->dispatcher->dispatch($this->incoming('delivery.shipment.requested.v1', [
            'shipment_id' => 'shp_failure_mode_002',
            'order_id' => 'ord_failure_mode_002',
            'delivery_address' => DeliveryTestFixtures::deliveryAddress()->toArray(),
            'carrier_profile' => DeliveryTestFixtures::carrierProfile()->toArray(),
        ], idempotencyKey: 'idem-failure-unavailable-1'));
    }

    public function test_cancel_failure_mode_publishes_cancel_failed_event(): void
    {
        $repository = new InMemoryShipmentRepository();
        $seedShipments = DeliveryTestFixtures::lifecycleService($repository);
        $seedShipments->create(DeliveryTestFixtures::createShipmentCommand(
            orderId: 'ord_failure_mode_003',
            shipmentId: 'shp_failure_mode_003',
        ));

        [
            $this->dispatcher,
            $shipments,
            $this->recordingPublisher,
        ] = IdempotentShipmentMessageDispatcherFactory::create(
            repository: $repository,
            degradationSimulator: TestFailureModeSupport::simulator($this->failureModeManager),
        );

        $this->failureModeManager->set(FailureMode::CancelFailure);

        $this->dispatcher->dispatch($this->incoming('delivery.shipment.cancel_requested.v1', [
            'shipment_id' => 'shp_failure_mode_003',
            'order_id' => 'ord_failure_mode_003',
            'reason' => 'customer_cancelled',
        ], idempotencyKey: 'idem-failure-cancel-1'));

        $this->assertSame('created', $shipments->get('shp_failure_mode_003')->status()->value);
        $this->assertSame('delivery.shipment.cancel_failed.v1', $this->recordingPublisher->published[0]->routingKey);
        $this->assertSame('delivery_failed', $this->recordingPublisher->published[0]->payload['failure_code']);
    }
}
