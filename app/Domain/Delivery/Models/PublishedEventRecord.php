<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Models;

use App\Infrastructure\Messaging\RabbitMq\MessageHeaders;

final readonly class PublishedEventRecord
{
    public const OPERATION_SHIPMENT_CREATED = 'delivery.shipment.created';

    public const OPERATION_SHIPMENT_CREATION_FAILED = 'delivery.shipment.creation_failed';

    public const OPERATION_SHIPMENT_STATUS_CHANGED = 'delivery.shipment.status_changed';

    public const OPERATION_SHIPMENT_CANCELLED = 'delivery.shipment.cancelled';

    public const OPERATION_SHIPMENT_CANCEL_FAILED = 'delivery.shipment.cancel_failed';

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $recordId,
        public string $operation,
        public string $shipmentId,
        public string $idempotencyKey,
        public string $routingKey,
        public MessageHeaders $headers,
        public array $payload,
        public string $responseFingerprint,
    ) {
    }
}
