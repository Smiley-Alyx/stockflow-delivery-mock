<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Infrastructure\Observability\DeliveryMetricsRecorder;
use App\Support\DeliveryStructuredLogger;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

final class DeliveryRetryRequeueHandler
{
    public function __construct(
        private readonly RabbitMqConfig $config,
        private readonly DeliveryMetricsRecorder $metricsRecorder,
    ) {
    }

    public function handle(AMQPChannel $channel, AMQPMessage $message): void
    {
        $deliveryTag = $message->getDeliveryTag();
        $metadata = MessageRetryMetadata::fromAmqpMessage($message);

        if ($metadata->originalRoutingKey === null || $metadata->originalRoutingKey === '') {
            DeliveryStructuredLogger::warning('delivery retry message missing original routing key, rejecting to dlq', [
                'retry_count' => $metadata->retryCount,
            ]);

            $channel->basic_reject($deliveryTag, false);

            return;
        }

        if ($this->shouldWait($metadata)) {
            $channel->basic_nack($deliveryTag, false, true);

            return;
        }

        $channel->basic_publish(
            $message,
            $this->config->exchange,
            $metadata->originalRoutingKey,
        );

        $this->metricsRecorder->recordRetryRequeued($metadata->originalRoutingKey);

        DeliveryStructuredLogger::info('delivery request requeued from retry queue', DeliveryStructuredLogger::context('delivery.request.retry_requeued', [
            'routing_key' => $metadata->originalRoutingKey,
            'retry_count' => $metadata->retryCount,
        ]));

        $channel->basic_ack($deliveryTag);
    }

    private function shouldWait(MessageRetryMetadata $metadata): bool
    {
        if ($metadata->retryAfterEpochMs === null) {
            return false;
        }

        return (int) (microtime(true) * 1000) < $metadata->retryAfterEpochMs;
    }
}
