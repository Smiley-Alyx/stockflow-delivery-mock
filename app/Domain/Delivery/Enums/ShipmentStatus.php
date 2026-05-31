<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Enums;

enum ShipmentStatus: string
{
    case Created = 'created';
    case LabelGenerated = 'label_generated';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case ReturnRequested = 'return_requested';
    case ReturnInTransit = 'return_in_transit';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::Returned,
            self::Cancelled,
        ], true);
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return in_array($next, $this->allowedTransitions(), true);
    }

    public function nextDefaultStatus(): ?self
    {
        return match ($this) {
            self::Created => self::LabelGenerated,
            self::LabelGenerated => self::PickedUp,
            self::PickedUp => self::InTransit,
            self::InTransit => self::OutForDelivery,
            self::OutForDelivery => self::Delivered,
            default => null,
        };
    }

    /**
     * @return list<self>
     */
    private function allowedTransitions(): array
    {
        return match ($this) {
            self::Created => [self::LabelGenerated, self::Cancelled],
            self::LabelGenerated => [self::PickedUp, self::Cancelled],
            self::PickedUp => [self::InTransit, self::Cancelled],
            self::InTransit => [self::OutForDelivery, self::DeliveryFailed],
            self::OutForDelivery => [self::Delivered, self::DeliveryFailed],
            self::DeliveryFailed => [self::ReturnRequested],
            self::ReturnRequested => [self::ReturnInTransit],
            self::ReturnInTransit => [self::Returned],
            self::Delivered, self::Returned, self::Cancelled => [],
        };
    }
}
