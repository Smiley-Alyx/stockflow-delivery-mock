<?php

declare(strict_types=1);

namespace App\Domain\Delivery\DTO;

final readonly class CancelShipmentCommand
{
    public function __construct(
        public string $shipmentId,
        public string $orderId,
        public \DateTimeImmutable $occurredAt,
        public ?string $reason,
        public string $idempotencyKey,
        public string $messageId,
        public string $correlationId,
    ) {
    }
}
