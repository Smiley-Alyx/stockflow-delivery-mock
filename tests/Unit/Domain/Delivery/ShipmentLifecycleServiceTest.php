<?php

declare(strict_types=1);

use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Exceptions\InvalidShipmentStateException;
use App\Domain\Delivery\Exceptions\InvalidShipmentTransitionException;
use App\Domain\Delivery\Exceptions\ShipmentNotFoundException;
use App\Infrastructure\Persistence\InMemoryShipmentRepository;
use Tests\Support\DeliveryTestFixtures;

beforeEach(function (): void {
    $this->repository = new InMemoryShipmentRepository();
    $this->service = DeliveryTestFixtures::lifecycleService($this->repository);
});

test('create persists shipment in created status', function (): void {
    $shipment = $this->service->create(DeliveryTestFixtures::createShipmentCommand());

    expect($shipment->status())->toBe(ShipmentStatus::Created)
        ->and($this->service->get('shp_test_001')->shipmentId())->toBe('shp_test_001')
        ->and($this->service->list())->toHaveCount(1);
});

test('create rejects duplicate shipment id', function (): void {
    $this->service->create(DeliveryTestFixtures::createShipmentCommand());

    $this->service->create(DeliveryTestFixtures::createShipmentCommand());
})->throws(InvalidShipmentStateException::class, 'Shipment shp_test_001 already exists.');

test('advance status moves shipment through default happy path', function (): void {
    $this->service->create(DeliveryTestFixtures::createShipmentCommand());

    $occurredAt = new DateTimeImmutable('2026-05-31T10:05:00+00:00');

    $labelGenerated = $this->service->advanceStatus('shp_test_001', $occurredAt);
    expect($labelGenerated->status())->toBe(ShipmentStatus::LabelGenerated);

    $pickedUp = $this->service->advanceStatus('shp_test_001', $occurredAt);
    expect($pickedUp->status())->toBe(ShipmentStatus::PickedUp);

    $inTransit = $this->service->advanceStatus('shp_test_001', $occurredAt);
    expect($inTransit->status())->toBe(ShipmentStatus::InTransit);

    $outForDelivery = $this->service->advanceStatus('shp_test_001', $occurredAt);
    expect($outForDelivery->status())->toBe(ShipmentStatus::OutForDelivery);

    $delivered = $this->service->advanceStatus('shp_test_001', $occurredAt);
    expect($delivered->status())->toBe(ShipmentStatus::Delivered)
        ->and($delivered->statusHistory())->toHaveCount(6);
});

test('advance status rejects terminal shipment', function (): void {
    $this->service->create(DeliveryTestFixtures::createShipmentCommand());

    $occurredAt = new DateTimeImmutable('2026-05-31T10:05:00+00:00');

    for ($step = 0; $step < 5; $step++) {
        $this->service->advanceStatus('shp_test_001', $occurredAt);
    }

    $this->service->advanceStatus('shp_test_001', $occurredAt);
})->throws(InvalidShipmentStateException::class, 'Shipment shp_test_001 in status delivered cannot be advanced automatically.');

test('advance status rejects shipment in delivery failed status', function (): void {
    $shipment = $this->service->create(DeliveryTestFixtures::createShipmentCommand());

    $occurredAt = new DateTimeImmutable('2026-05-31T10:05:00+00:00');

    foreach ([
        ShipmentStatus::LabelGenerated,
        ShipmentStatus::PickedUp,
        ShipmentStatus::InTransit,
        ShipmentStatus::OutForDelivery,
        ShipmentStatus::DeliveryFailed,
    ] as $index => $status) {
        $shipment->transitionTo($status, $occurredAt->modify(sprintf('+%d minutes', $index + 1)));
    }

    $this->repository->save($shipment);

    $this->service->advanceStatus('shp_test_001', $occurredAt);
})->throws(InvalidShipmentStateException::class, 'Shipment shp_test_001 in status delivery_failed cannot be advanced automatically.');

test('cancel moves cancellable shipment to cancelled status', function (): void {
    $this->service->create(DeliveryTestFixtures::createShipmentCommand());

    $cancelled = $this->service->cancel(
        'shp_test_001',
        new DateTimeImmutable('2026-05-31T10:05:00+00:00'),
        'marketplace_cancel_requested',
    );

    expect($cancelled->status())->toBe(ShipmentStatus::Cancelled)
        ->and($cancelled->statusHistory()[1]->reason)->toBe('marketplace_cancel_requested');
});

test('cancel rejects shipment already in transit', function (): void {
    $this->service->create(DeliveryTestFixtures::createShipmentCommand());

    $occurredAt = new DateTimeImmutable('2026-05-31T10:05:00+00:00');

    $this->service->advanceStatus('shp_test_001', $occurredAt);
    $this->service->advanceStatus('shp_test_001', $occurredAt);
    $this->service->advanceStatus('shp_test_001', $occurredAt);

    $this->service->cancel('shp_test_001', $occurredAt);
})->throws(InvalidShipmentStateException::class, 'Shipment shp_test_001 in status in_transit cannot be cancelled.');

test('get throws when shipment does not exist', function (): void {
    $this->service->get('shp_missing');
})->throws(ShipmentNotFoundException::class, 'Shipment shp_missing was not found.');

test('advance status propagates invalid transition from domain model', function (): void {
    $shipment = $this->service->create(DeliveryTestFixtures::createShipmentCommand());

    $shipment->transitionTo(
        ShipmentStatus::Cancelled,
        new DateTimeImmutable('2026-05-31T10:01:00+00:00'),
    );
    $this->repository->save($shipment);

    expect(fn () => $shipment->transitionTo(
        ShipmentStatus::Delivered,
        new DateTimeImmutable('2026-05-31T10:02:00+00:00'),
    ))->toThrow(
        InvalidShipmentTransitionException::class,
        'Cannot transition shipment shp_test_001 from cancelled to delivered',
    );
});
