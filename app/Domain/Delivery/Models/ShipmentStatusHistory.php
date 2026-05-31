<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\ShipmentStatus;

final readonly class ShipmentStatusHistory
{
    public function __construct(
        public string $historyId,
        public string $shipmentId,
        public ?ShipmentStatus $fromStatus,
        public ShipmentStatus $toStatus,
        public \DateTimeImmutable $occurredAt,
        public ?string $reason = null,
    ) {
    }

    /**
     * @return array{
     *     history_id: string,
     *     shipment_id: string,
     *     from_status: string|null,
     *     to_status: string,
     *     occurred_at: string,
     *     reason: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'history_id' => $this->historyId,
            'shipment_id' => $this->shipmentId,
            'from_status' => $this->fromStatus?->value,
            'to_status' => $this->toStatus->value,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'reason' => $this->reason,
        ];
    }
}
