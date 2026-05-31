<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Domain\Delivery\Support\PrefixedIdGenerator;

final class OutgoingMessageHeadersFactory
{
    public function __construct(
        private readonly string $producer,
    ) {
    }

    public function forResponse(IncomingMessage $incoming): MessageHeaders
    {
        return new MessageHeaders(
            messageId: $this->generateMessageId(),
            correlationId: $incoming->headers->correlationId,
            causationId: $incoming->headers->messageId,
            idempotencyKey: $incoming->headers->idempotencyKey,
            schemaVersion: 'v1',
            occurredAt: (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
            producer: $this->producer,
        );
    }

    public function forStatusChange(
        IncomingMessage $incoming,
        string $shipmentId,
        string $currentStatus,
    ): MessageHeaders {
        $response = $this->forResponse($incoming);

        return new MessageHeaders(
            messageId: $response->messageId,
            correlationId: $response->correlationId,
            causationId: $response->causationId,
            idempotencyKey: sprintf('idem-shp-status-%s-%s', $shipmentId, $currentStatus),
            schemaVersion: $response->schemaVersion,
            occurredAt: $response->occurredAt,
            producer: $response->producer,
        );
    }

    public function generateMessageId(): string
    {
        return PrefixedIdGenerator::generate('msg');
    }
}
