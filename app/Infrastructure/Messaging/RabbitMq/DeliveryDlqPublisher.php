<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class DeliveryDlqPublisher
{
    public function __construct(
        private readonly RabbitMqConfig $config,
    ) {
    }

    public function publish(AMQPChannel $channel, AMQPMessage $message, Throwable $exception): void
    {
        $routingKey = (string) ($message->getRoutingKey() ?? '');
        $properties = $message->get_properties();
        $headers = $this->applicationHeaders($properties);
        $metadata = MessageRetryMetadata::fromAmqpMessage($message);

        $headers['x-original-routing-key'] = $metadata->originalRoutingKey ?? $routingKey;
        $headers['x-retry-count'] = (string) $metadata->retryCount;
        $headers['x-failure-reason'] = (new \ReflectionClass($exception))->getShortName();
        $headers['x-failure-message'] = mb_substr($exception->getMessage(), 0, 255);

        $dlqMessage = new AMQPMessage(
            (string) $message->getBody(),
            [
                'content_type' => $properties['content_type'] ?? 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'application_headers' => new AMQPTable($headers),
            ],
        );

        $channel->basic_publish(
            $dlqMessage,
            $this->config->deadLetterExchange,
            $this->config->dlq,
        );
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, string>
     */
    private function applicationHeaders(array $properties): array
    {
        $applicationHeaders = $properties['application_headers'] ?? null;

        if ($applicationHeaders instanceof AMQPTable) {
            return array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : json_encode($value),
                $applicationHeaders->getNativeData(),
            );
        }

        if (! is_array($applicationHeaders)) {
            return [];
        }

        return array_map(
            static fn (mixed $value): string => is_scalar($value) ? (string) $value : json_encode($value),
            $applicationHeaders,
        );
    }
}
