<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Messaging;

use App\Application\Handlers\ShipmentCancelRequestedHandler;
use App\Application\Handlers\ShipmentMessageDispatcher;
use App\Application\Handlers\ShipmentRequestedHandler;
use App\Application\Mappers\ShipmentMessageMapper;
use App\Domain\Delivery\Services\Debug\DeliveryDegradationSimulator;
use App\Domain\Delivery\Services\Idempotency\ShipmentIdempotencyService;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Infrastructure\Messaging\RabbitMq\DeliveryRequestConsumer;
use App\Infrastructure\Messaging\RabbitMq\NullDeliveryEventPublisher;
use App\Infrastructure\Messaging\RabbitMq\PublishedEventStore;
use App\Infrastructure\Persistence\InMemoryIdempotencyRecordRepository;
use App\Infrastructure\Persistence\InMemoryPublishedEventRecordRepository;
use App\Infrastructure\Observability\DeliveryMetricsRecorder;
use Tests\Support\Debug\TestFailureModeSupport;
use Tests\Support\Messaging\RecordingRabbitMqMessagePublisher;
use Tests\Support\Observability\TestMetricsSupport;
use App\Infrastructure\Persistence\InMemoryShipmentRepository;
use App\Infrastructure\Messaging\RabbitMq\DeliveryDlqPublisher;
use App\Infrastructure\Messaging\RabbitMq\DeliveryRequestFailureHandler;
use App\Infrastructure\Messaging\RabbitMq\DeliveryRequestRetryPublisher;
use App\Infrastructure\Messaging\RabbitMq\DeliveryRetryRequeueHandler;
use App\Infrastructure\Messaging\RabbitMq\MessageRetryPolicy;
use App\Infrastructure\Messaging\RabbitMq\MessageHeaderValidator;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConfig;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqTopologyManager;
use PHPUnit\Framework\TestCase;

final class DeliveryRequestConsumerTest extends TestCase
{
    public function test_request_stop_marks_consumer_for_shutdown(): void
    {
        $repository = new InMemoryShipmentRepository();
        $shipments = new ShipmentLifecycleService($repository);
        $mapper = new ShipmentMessageMapper();
        $eventPublisher = new NullDeliveryEventPublisher();
        $idempotency = new ShipmentIdempotencyService(new InMemoryIdempotencyRecordRepository());
        $publishedEventStore = new PublishedEventStore(new InMemoryPublishedEventRecordRepository());
        $degradationSimulator = TestFailureModeSupport::simulator();
        $metricsRecorder = TestMetricsSupport::recorder();
        $config = $this->config();
        $dispatcher = new ShipmentMessageDispatcher(
            new ShipmentRequestedHandler(
                $mapper,
                $shipments,
                $eventPublisher,
                $idempotency,
                $degradationSimulator,
                $publishedEventStore,
                $metricsRecorder,
            ),
            new ShipmentCancelRequestedHandler(
                $mapper,
                $shipments,
                $eventPublisher,
                $idempotency,
                $publishedEventStore,
                $degradationSimulator,
                $metricsRecorder,
            ),
        );

        $consumer = new DeliveryRequestConsumer(
            $config,
            new RabbitMqConnectionFactory($config),
            new RabbitMqTopologyManager($config),
            new MessageHeaderValidator(),
            $dispatcher,
            new DeliveryRequestFailureHandler(
                new MessageRetryPolicy($config),
                new DeliveryRequestRetryPublisher($config),
                new DeliveryDlqPublisher($config),
                $metricsRecorder,
            ),
            new DeliveryRetryRequeueHandler($config, $metricsRecorder),
            new RecordingRabbitMqMessagePublisher(),
        );

        $this->assertFalse($consumer->shouldStop());

        $consumer->requestStop();

        $this->assertTrue($consumer->shouldStop());
    }

    private function config(): RabbitMqConfig
    {
        return RabbitMqConfig::fromArray([
            'host' => 'rabbitmq',
            'port' => 5672,
            'user' => 'stockflow',
            'password' => 'stockflow',
            'vhost' => '/',
            'exchange' => 'stockflow.delivery',
            'dead_letter_exchange' => 'stockflow.delivery.dlx',
            'requests_queue' => 'stockflow.delivery.requests',
            'retry_queue' => 'stockflow.delivery.requests.retry',
            'dlq' => 'stockflow.delivery.requests.dlq',
            'prefetch_count' => 1,
            'consumer_timeout_seconds' => 30,
            'setup_topology' => true,
            'publish_events' => true,
            'max_retry_attempts' => 3,
            'retry_delay_ms' => 5000,
        ]);
    }
}
