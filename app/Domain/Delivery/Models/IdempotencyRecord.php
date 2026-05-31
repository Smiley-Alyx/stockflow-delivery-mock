<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Models;

final readonly class IdempotencyRecord
{
    public const OPERATION_CREATE = 'delivery.shipment.create';

    public const OPERATION_CANCEL = 'delivery.shipment.cancel';

    public function __construct(
        public string $recordId,
        public string $operation,
        public string $shipmentId,
        public string $idempotencyKey,
        public string $responseFingerprint,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
