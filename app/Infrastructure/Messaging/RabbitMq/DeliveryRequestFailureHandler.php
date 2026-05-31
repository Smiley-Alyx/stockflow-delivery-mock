<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use App\Infrastructure\Observability\DeliveryMetricsRecorder;
use App\Support\DeliveryStructuredLogger;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class DeliveryRequestFailureHandler
{
    public function __construct(
        private readonly MessageRetryPolicy $retryPolicy,
        private readonly DeliveryRequestRetryPublisher $retryPublisher,
        private readonly DeliveryDlqPublisher $dlqPublisher,
        private readonly DeliveryMetricsRecorder $metricsRecorder,
    ) {
    }

    public function handleInvalidMessage(
        AMQPChannel $channel,
        AMQPMessage $message,
        InvalidMessageException $exception,
    ): void {
        DeliveryStructuredLogger::warning('delivery invalid message rejected', DeliveryStructuredLogger::context('delivery.request.invalid', [
            'routing_key' => (string) ($message->getRoutingKey() ?? ''),
            'error' => $exception->getMessage(),
        ]));

        $this->metricsRecorder->recordInvalidMessage((string) ($message->getRoutingKey() ?? 'unknown'));

        $channel->basic_reject($message->getDeliveryTag(), false);
    }

    public function handleProcessingFailure(
        AMQPChannel $channel,
        AMQPMessage $message,
        Throwable $exception,
    ): void {
        $deliveryTag = $message->getDeliveryTag();
        $metadata = MessageRetryMetadata::fromAmqpMessage($message);

        DeliveryStructuredLogger::error('delivery message processing failed', DeliveryStructuredLogger::context('delivery.request.failed', [
            'routing_key' => (string) ($message->getRoutingKey() ?? ''),
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
            'retry_count' => $metadata->retryCount,
        ]));

        if (! $this->retryPolicy->isRetryable($exception)) {
            $this->moveToDlq($channel, $message, $exception);
            $channel->basic_ack($deliveryTag);

            return;
        }

        if ($this->retryPolicy->shouldRetry($metadata->retryCount)) {
            $this->retryPublisher->publish($channel, $message, $metadata->retryCount + 1);
            $this->metricsRecorder->recordRetryScheduled((string) ($message->getRoutingKey() ?? 'unknown'));

            DeliveryStructuredLogger::info('delivery request scheduled for retry', DeliveryStructuredLogger::context('delivery.request.retry_scheduled', [
                'routing_key' => (string) ($message->getRoutingKey() ?? ''),
                'retry_count' => $metadata->retryCount + 1,
            ]));

            $channel->basic_ack($deliveryTag);

            return;
        }

        $this->moveToDlq($channel, $message, $exception);
        $channel->basic_ack($deliveryTag);
    }

    private function moveToDlq(AMQPChannel $channel, AMQPMessage $message, Throwable $exception): void
    {
        $this->dlqPublisher->publish($channel, $message, $exception);
        $this->metricsRecorder->recordDlq(
            (string) ($message->getRoutingKey() ?? 'unknown'),
            (new \ReflectionClass($exception))->getShortName(),
        );

        DeliveryStructuredLogger::warning('delivery request moved to dlq', DeliveryStructuredLogger::context('delivery.request.dlq', [
            'routing_key' => (string) ($message->getRoutingKey() ?? ''),
            'failure_reason' => (new \ReflectionClass($exception))->getShortName(),
        ]));
    }
}
