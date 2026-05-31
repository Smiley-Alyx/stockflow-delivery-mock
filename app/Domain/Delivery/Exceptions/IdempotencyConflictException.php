<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use App\Domain\Delivery\Models\IdempotencyRecord;

final class IdempotencyConflictException extends \DomainException
{
    public static function forRecord(IdempotencyRecord $record): self
    {
        return new self(sprintf(
            'Idempotency conflict for %s shipment %s key %s',
            $record->operation,
            $record->shipmentId,
            $record->idempotencyKey,
        ));
    }
}
