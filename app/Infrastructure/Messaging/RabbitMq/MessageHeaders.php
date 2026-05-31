<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

final readonly class MessageHeaders
{
    public function __construct(
        public string $messageId,
        public string $correlationId,
        public string $causationId,
        public string $idempotencyKey,
        public string $schemaVersion,
        public string $occurredAt,
        public string $producer,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'idempotency_key' => $this->idempotencyKey,
            'schema_version' => $this->schemaVersion,
            'occurred_at' => $this->occurredAt,
            'producer' => $this->producer,
        ];
    }
}
