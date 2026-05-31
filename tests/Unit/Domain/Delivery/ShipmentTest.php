<?php

declare(strict_types=1);

use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentTransitionException;
use App\Domain\Delivery\Models\CarrierProfile;
use App\Domain\Delivery\Models\DeliveryAddress;
use App\Domain\Delivery\Models\Shipment;

function makeDeliveryAddress(): DeliveryAddress
{
    return new DeliveryAddress(
        recipientName: 'Jane Doe',
        countryCode: 'RU',
        city: 'Moscow',
        postalCode: '101000',
        streetLine1: 'Red Square 1',
    );
}

function makeCarrierProfile(): CarrierProfile
{
    return new CarrierProfile(
        carrierCode: 'stockflow-express',
        serviceLevel: 'standard',
    );
}

test('shipment is created in created status with initial history', function (): void {
    $occurredAt = new DateTimeImmutable('2026-05-31T10:00:00+00:00');

    $shipment = Shipment::create(
        orderId: 'ord_123',
        deliveryAddress: makeDeliveryAddress(),
        carrierProfile: makeCarrierProfile(),
        occurredAt: $occurredAt,
        shipmentId: 'shp_test_001',
    );

    expect($shipment->shipmentId())->toBe('shp_test_001')
        ->and($shipment->orderId())->toBe('ord_123')
        ->and($shipment->status())->toBe(ShipmentStatus::Created)
        ->and($shipment->createdAt())->toBe($occurredAt)
        ->and($shipment->statusHistory())->toHaveCount(1)
        ->and($shipment->statusHistory()[0]->fromStatus)->toBeNull()
        ->and($shipment->statusHistory()[0]->toStatus)->toBe(ShipmentStatus::Created)
        ->and($shipment->statusHistory()[0]->reason)->toBe('shipment_created');
});

test('shipment transition appends status history and updates timestamps', function (): void {
    $createdAt = new DateTimeImmutable('2026-05-31T10:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-05-31T10:05:00+00:00');

    $shipment = Shipment::create(
        orderId: 'ord_123',
        deliveryAddress: makeDeliveryAddress(),
        carrierProfile: makeCarrierProfile(),
        occurredAt: $createdAt,
        shipmentId: 'shp_test_002',
    );

    $shipment->transitionTo(
        ShipmentStatus::LabelGenerated,
        $updatedAt,
        'label_generated',
    );

    expect($shipment->status())->toBe(ShipmentStatus::LabelGenerated)
        ->and($shipment->updatedAt())->toBe($updatedAt)
        ->and($shipment->statusHistory())->toHaveCount(2);

    $latestHistory = $shipment->statusHistory()[1];

    expect($latestHistory->fromStatus)->toBe(ShipmentStatus::Created)
        ->and($latestHistory->toStatus)->toBe(ShipmentStatus::LabelGenerated)
        ->and($latestHistory->reason)->toBe('label_generated');
});

test('shipment rejects invalid status transition', function (): void {
    $shipment = Shipment::create(
        orderId: 'ord_123',
        deliveryAddress: makeDeliveryAddress(),
        carrierProfile: makeCarrierProfile(),
        occurredAt: new DateTimeImmutable('2026-05-31T10:00:00+00:00'),
        shipmentId: 'shp_test_003',
    );

    $shipment->transitionTo(
        ShipmentStatus::Delivered,
        new DateTimeImmutable('2026-05-31T10:05:00+00:00'),
    );
})->throws(InvalidShipmentTransitionException::class, 'Cannot transition shipment shp_test_003 from created to delivered');

test('shipment ignores transition to the same status', function (): void {
    $occurredAt = new DateTimeImmutable('2026-05-31T10:00:00+00:00');

    $shipment = Shipment::create(
        orderId: 'ord_123',
        deliveryAddress: makeDeliveryAddress(),
        carrierProfile: makeCarrierProfile(),
        occurredAt: $occurredAt,
        shipmentId: 'shp_test_004',
    );

    $shipment->transitionTo(ShipmentStatus::Created, $occurredAt);

    expect($shipment->statusHistory())->toHaveCount(1);
});

test('shipment status history serializes to array', function (): void {
    $occurredAt = new DateTimeImmutable('2026-05-31T10:00:00+00:00');

    $shipment = Shipment::create(
        orderId: 'ord_123',
        deliveryAddress: makeDeliveryAddress(),
        carrierProfile: makeCarrierProfile(),
        occurredAt: $occurredAt,
        shipmentId: 'shp_test_005',
    );

    expect($shipment->statusHistory()[0]->toArray())->toMatchArray([
        'shipment_id' => 'shp_test_005',
        'from_status' => null,
        'to_status' => 'created',
        'reason' => 'shipment_created',
    ]);
});
