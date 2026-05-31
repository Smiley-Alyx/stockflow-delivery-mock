<?php

declare(strict_types=1);

use App\Domain\Delivery\Enums\ShipmentStatus;

dataset('allowed shipment transitions', [
    'created to label generated' => [ShipmentStatus::Created, ShipmentStatus::LabelGenerated],
    'label generated to picked up' => [ShipmentStatus::LabelGenerated, ShipmentStatus::PickedUp],
    'picked up to in transit' => [ShipmentStatus::PickedUp, ShipmentStatus::InTransit],
    'in transit to out for delivery' => [ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery],
    'out for delivery to delivered' => [ShipmentStatus::OutForDelivery, ShipmentStatus::Delivered],
    'out for delivery to delivery failed' => [ShipmentStatus::OutForDelivery, ShipmentStatus::DeliveryFailed],
    'delivery failed to return requested' => [ShipmentStatus::DeliveryFailed, ShipmentStatus::ReturnRequested],
    'return requested to return in transit' => [ShipmentStatus::ReturnRequested, ShipmentStatus::ReturnInTransit],
    'return in transit to returned' => [ShipmentStatus::ReturnInTransit, ShipmentStatus::Returned],
    'created to cancelled' => [ShipmentStatus::Created, ShipmentStatus::Cancelled],
]);

dataset('disallowed shipment transitions', [
    'created to delivered' => [ShipmentStatus::Created, ShipmentStatus::Delivered],
    'delivered to in transit' => [ShipmentStatus::Delivered, ShipmentStatus::InTransit],
    'cancelled to picked up' => [ShipmentStatus::Cancelled, ShipmentStatus::PickedUp],
    'returned to out for delivery' => [ShipmentStatus::Returned, ShipmentStatus::OutForDelivery],
]);

test('terminal statuses are marked correctly', function (): void {
    expect(ShipmentStatus::Delivered->isTerminal())->toBeTrue()
        ->and(ShipmentStatus::Returned->isTerminal())->toBeTrue()
        ->and(ShipmentStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(ShipmentStatus::InTransit->isTerminal())->toBeFalse()
        ->and(ShipmentStatus::DeliveryFailed->isTerminal())->toBeFalse();
});

test('allowed status transitions', function (ShipmentStatus $from, ShipmentStatus $to): void {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with('allowed shipment transitions');

test('disallowed status transitions', function (ShipmentStatus $from, ShipmentStatus $to): void {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with('disallowed shipment transitions');

test('status can transition to itself', function (): void {
    expect(ShipmentStatus::InTransit->canTransitionTo(ShipmentStatus::InTransit))->toBeTrue();
});
