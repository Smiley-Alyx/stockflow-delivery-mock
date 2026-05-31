<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;

final class MessageHeaderValidator
{
    /**
     * @param array<string, mixed> $properties
     */
    public function validate(array $properties): MessageHeaders
    {
        $headers = $this->normalizeHeaders($properties);

        foreach ([
            'message_id',
            'correlation_id',
            'causation_id',
            'idempotency_key',
            'schema_version',
            'occurred_at',
            'producer',
        ] as $field) {
            if (! isset($headers[$field]) || ! is_string($headers[$field]) || trim($headers[$field]) === '') {
                throw new InvalidMessageException(sprintf('Missing or invalid header: %s', $field));
            }
        }

        if ($headers['schema_version'] !== 'v1') {
            throw new InvalidMessageException('Unsupported schema_version. Expected v1.');
        }

        return new MessageHeaders(
            messageId: $headers['message_id'],
            correlationId: $headers['correlation_id'],
            causationId: $headers['causation_id'],
            idempotencyKey: $headers['idempotency_key'],
            schemaVersion: $headers['schema_version'],
            occurredAt: $headers['occurred_at'],
            producer: $headers['producer'],
        );
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, string>
     */
    private function normalizeHeaders(array $properties): array
    {
        $applicationHeaders = $properties['application_headers'] ?? null;

        if ($applicationHeaders instanceof \PhpAmqpLib\Wire\AMQPTable) {
            return array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : json_encode($value),
                $applicationHeaders->getNativeData(),
            );
        }

        if (is_array($applicationHeaders)) {
            return array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : json_encode($value),
                $applicationHeaders,
            );
        }

        $normalized = [];

        foreach ($properties as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        return $normalized;
    }
}
