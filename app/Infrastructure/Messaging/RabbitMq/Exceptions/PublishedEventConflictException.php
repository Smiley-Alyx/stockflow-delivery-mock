<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq\Exceptions;

final class PublishedEventConflictException extends \RuntimeException
{
    public static function forOperation(string $operation, string $shipmentId, string $idempotencyKey): self
    {
        return new self(sprintf(
            'Published event conflict for %s shipment %s key %s',
            $operation,
            $shipmentId,
            $idempotencyKey,
        ));
    }
}
