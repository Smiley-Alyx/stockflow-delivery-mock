<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class DeliveryRequestRetryPublisher
{
    public function __construct(
        private readonly RabbitMqConfig $config,
    ) {
    }

    public function publish(AMQPChannel $channel, AMQPMessage $message, int $retryCount): void
    {
        $routingKey = (string) ($message->getRoutingKey() ?? '');
        $properties = $message->get_properties();
        $headers = $this->applicationHeaders($properties);
        $headers['x-retry-count'] = (string) $retryCount;
        $headers['x-original-routing-key'] = $routingKey;

        if ($this->config->retryDelayMs > 0) {
            $headers['x-retry-after'] = (string) (int) (microtime(true) * 1000 + $this->config->retryDelayMs);
        }

        $retryMessage = new AMQPMessage(
            (string) $message->getBody(),
            [
                'content_type' => $properties['content_type'] ?? 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'application_headers' => new AMQPTable($headers),
            ],
        );

        $channel->basic_publish(
            $retryMessage,
            '',
            $this->config->retryQueue,
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
