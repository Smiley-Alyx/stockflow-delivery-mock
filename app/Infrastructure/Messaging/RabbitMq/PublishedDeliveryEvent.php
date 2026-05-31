<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

final readonly class PublishedDeliveryEvent
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $routingKey,
        public MessageHeaders $headers,
        public array $payload,
    ) {
    }
}
