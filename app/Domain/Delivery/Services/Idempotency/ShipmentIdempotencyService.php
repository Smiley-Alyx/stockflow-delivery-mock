<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services\Idempotency;

use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\IdempotencyConflictException;
use App\Domain\Delivery\Models\IdempotencyRecord;
use App\Domain\Delivery\Models\Shipment;
use App\Domain\Delivery\Repositories\IdempotencyRecordRepository;
use App\Domain\Delivery\Support\PrefixedIdGenerator;

final class ShipmentIdempotencyService
{
    public function __construct(
        private readonly IdempotencyRecordRepository $records,
    ) {
    }

    public function findCreateRecord(string $shipmentId, string $idempotencyKey): ?IdempotencyRecord
    {
        return $this->records->find(IdempotencyRecord::OPERATION_CREATE, $shipmentId, $idempotencyKey);
    }

    public function findCancelRecord(string $shipmentId, string $idempotencyKey): ?IdempotencyRecord
    {
        return $this->records->find(IdempotencyRecord::OPERATION_CANCEL, $shipmentId, $idempotencyKey);
    }

    public function storeCreateRecord(
        string $shipmentId,
        string $idempotencyKey,
        string $responseFingerprint,
    ): IdempotencyRecord {
        return $this->records->store(new IdempotencyRecord(
            recordId: PrefixedIdGenerator::generate('idr'),
            operation: IdempotencyRecord::OPERATION_CREATE,
            shipmentId: $shipmentId,
            idempotencyKey: $idempotencyKey,
            responseFingerprint: $responseFingerprint,
            createdAt: new \DateTimeImmutable(),
        ));
    }

    public function storeCancelRecord(
        string $shipmentId,
        string $idempotencyKey,
        string $responseFingerprint,
    ): IdempotencyRecord {
        return $this->records->store(new IdempotencyRecord(
            recordId: PrefixedIdGenerator::generate('idr'),
            operation: IdempotencyRecord::OPERATION_CANCEL,
            shipmentId: $shipmentId,
            idempotencyKey: $idempotencyKey,
            responseFingerprint: $responseFingerprint,
            createdAt: new \DateTimeImmutable(),
        ));
    }

    public function createFingerprint(Shipment $shipment): string
    {
        return hash('sha256', implode('|', [
            $shipment->shipmentId(),
            $shipment->status()->value,
            $shipment->trackingNumber(),
            $shipment->updatedAt()->format(DATE_ATOM),
        ]));
    }

    public function creationFailureFingerprint(string $shipmentId, string $failureCode): string
    {
        return hash('sha256', implode('|', [
            $shipmentId,
            'creation_failed',
            $failureCode,
        ]));
    }

    public function cancelSuccessFingerprint(Shipment $shipment): string
    {
        return hash('sha256', implode('|', [
            $shipment->shipmentId(),
            'cancelled',
            $shipment->updatedAt()->format(DATE_ATOM),
        ]));
    }

    public function cancelFailureFingerprint(
        string $shipmentId,
        string $failureCode,
        ?ShipmentStatus $currentStatus,
    ): string {
        return hash('sha256', implode('|', [
            $shipmentId,
            'cancel_failed',
            $failureCode,
            $currentStatus?->value ?? 'unknown',
        ]));
    }

    public function assertMatchingFingerprint(
        IdempotencyRecord $record,
        string $responseFingerprint,
    ): void {
        if ($record->responseFingerprint !== $responseFingerprint) {
            throw IdempotencyConflictException::forRecord($record);
        }
    }
}
