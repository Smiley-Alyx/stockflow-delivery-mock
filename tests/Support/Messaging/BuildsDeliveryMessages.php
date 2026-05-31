<?php

declare(strict_types=1);

namespace Tests\Support\Messaging;

use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;
use App\Infrastructure\Messaging\RabbitMq\MessageHeaders;

trait BuildsDeliveryMessages
{
    /**
     * @param array<string, mixed> $payload
     */
    protected function incoming(string $routingKey, array $payload): IncomingMessage
    {
        return new IncomingMessage(
            routingKey: $routingKey,
            headers: new MessageHeaders(
                messageId: 'msg_test_001',
                correlationId: 'cor_test_001',
                causationId: 'msg_cause_001',
                idempotencyKey: 'idem_test_001',
                schemaVersion: 'v1',
                occurredAt: '2026-05-31T10:20:00Z',
                producer: 'stockflow-market',
            ),
            payload: $payload,
            body: (string) json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
