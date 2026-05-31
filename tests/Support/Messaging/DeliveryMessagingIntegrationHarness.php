<?php

declare(strict_types=1);

namespace Tests\Support\Messaging;

use App\Application\Handlers\ShipmentMessageDispatcher;
use App\Domain\Delivery\Services\Debug\DeliveryDegradationSimulator;
use App\Domain\Delivery\Services\Debug\FailureModeManager;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Infrastructure\Messaging\RabbitMq\DeliveryRequestFailureHandler;
use App\Infrastructure\Messaging\RabbitMq\DeliveryRetryRequeueHandler;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;
use App\Infrastructure\Messaging\RabbitMq\MessageRetryPolicy;
use App\Infrastructure\Observability\DeliveryMetricsRecorder;
use App\Infrastructure\Observability\Prometheus\PrometheusRegistry;
use App\Infrastructure\Persistence\InMemoryIdempotencyRecordRepository;
use App\Infrastructure\Persistence\InMemoryPublishedEventRecordRepository;
use App\Support\DeliveryLogContext;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Tests\Support\Debug\TestFailureModeSupport;
use Tests\Support\Observability\TestMetricsSupport;
use Throwable;

final class DeliveryMessagingIntegrationHarness
{
    public readonly ShipmentMessageDispatcher $dispatcher;

    public readonly ShipmentLifecycleService $shipments;

    public readonly RecordingRabbitMqMessagePublisher $events;

    public readonly InMemoryIdempotencyRecordRepository $idempotencyRecords;

    public readonly InMemoryPublishedEventRecordRepository $publishedEventRecords;

    public readonly DeliveryDegradationSimulator $degradationSimulator;

    public readonly FailureModeManager $failureModeManager;

    public readonly DeliveryMetricsRecorder $metricsRecorder;

    public readonly PrometheusRegistry $metricsRegistry;

    public readonly MessageRetryPolicy $retryPolicy;

    public readonly RecordingDeliveryRequestRetryPublisher $retryPublisher;

    public readonly RecordingDeliveryDlqPublisher $dlqPublisher;

    public readonly DeliveryRequestFailureHandler $failureHandler;

    public readonly DeliveryRetryRequeueHandler $retryRequeueHandler;

    public function __construct()
    {
        TestFailureModeSupport::resetStateFile();

        $this->failureModeManager = TestFailureModeSupport::manager();
        $this->degradationSimulator = TestFailureModeSupport::simulator($this->failureModeManager);
        $this->metricsRegistry = TestMetricsSupport::registry();
        $this->metricsRecorder = TestMetricsSupport::recorder($this->metricsRegistry);
        $this->retryPolicy = new MessageRetryPolicy(RabbitMqTestConfig::make(maxRetryAttempts: 3));
        $this->retryPublisher = new RecordingDeliveryRequestRetryPublisher();
        $this->dlqPublisher = new RecordingDeliveryDlqPublisher();
        $this->failureHandler = new DeliveryRequestFailureHandler(
            $this->retryPolicy,
            $this->retryPublisher,
            $this->dlqPublisher,
            $this->metricsRecorder,
        );
        $this->retryRequeueHandler = new DeliveryRetryRequeueHandler(
            RabbitMqTestConfig::make(retryDelayMs: 0),
            $this->metricsRecorder,
        );

        [
            $this->dispatcher,
            $this->shipments,
            $this->events,
            $this->idempotencyRecords,
            $this->publishedEventRecords,
        ] = IdempotentShipmentMessageDispatcherFactory::create(
            degradationSimulator: $this->degradationSimulator,
            metricsRecorder: $this->metricsRecorder,
        );
    }

    public function processIncoming(IncomingMessage $message): void
    {
        DeliveryLogContext::bind([
            'correlation_id' => $message->headers->correlationId,
            'message_id' => $message->headers->messageId,
        ]);

        try {
            $this->dispatcher->dispatch($message);
        } finally {
            DeliveryLogContext::clear();
        }
    }

    public function processLikeConsumer(IncomingMessage $message, AMQPChannel $channel): void
    {
        $amqpMessage = $this->toAmqpMessage($message);

        DeliveryLogContext::bind([
            'correlation_id' => $message->headers->correlationId,
            'message_id' => $message->headers->messageId,
        ]);

        try {
            $this->dispatcher->dispatch($message);
            $channel->basic_ack($amqpMessage->getDeliveryTag());
        } catch (InvalidMessageException $exception) {
            $this->failureHandler->handleInvalidMessage($channel, $amqpMessage, $exception);
        } catch (Throwable $exception) {
            $this->failureHandler->handleProcessingFailure($channel, $amqpMessage, $exception);
        } finally {
            DeliveryLogContext::clear();
        }
    }

    public function toAmqpMessage(IncomingMessage $incoming, int $retryCount = 0): AMQPMessage
    {
        $headers = [];

        if ($retryCount > 0) {
            $headers['x-retry-count'] = (string) $retryCount;
            $headers['x-original-routing-key'] = $incoming->routingKey;
        }

        $properties = [
            'content_type' => 'application/json',
        ];

        if ($headers !== []) {
            $properties['application_headers'] = new AMQPTable($headers);
        }

        $message = new AMQPMessage($incoming->body, $properties);
        $message->setDeliveryInfo(1, false, 'stockflow.delivery', $incoming->routingKey);

        return $message;
    }

    public function metricsOutput(): string
    {
        return $this->metricsRegistry->render();
    }
}
