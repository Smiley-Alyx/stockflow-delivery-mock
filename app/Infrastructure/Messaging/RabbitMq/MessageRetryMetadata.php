<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final readonly class MessageRetryMetadata
{
    public function __construct(
        public int $retryCount,
        public ?string $originalRoutingKey,
        public ?int $retryAfterEpochMs,
    ) {
    }

    public static function fromAmqpMessage(AMQPMessage $message): self
    {
        $headers = self::normalizeHeaders($message->get_properties());

        return new self(
            retryCount: isset($headers['x-retry-count']) ? max(0, (int) $headers['x-retry-count']) : 0,
            originalRoutingKey: $headers['x-original-routing-key'] ?? null,
            retryAfterEpochMs: isset($headers['x-retry-after']) ? (int) $headers['x-retry-after'] : null,
        );
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $properties): array
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
