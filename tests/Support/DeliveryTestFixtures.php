<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Delivery\DTO\CreateShipmentCommand;
use App\Domain\Delivery\Models\CarrierProfile;
use App\Domain\Delivery\Models\DeliveryAddress;
use App\Domain\Delivery\Services\ShipmentLifecycleService;
use App\Infrastructure\Persistence\InMemoryShipmentRepository;

final class DeliveryTestFixtures
{
    public static function deliveryAddress(): DeliveryAddress
    {
        return new DeliveryAddress(
            recipientName: 'Jane Doe',
            countryCode: 'RU',
            city: 'Moscow',
            postalCode: '101000',
            streetLine1: 'Red Square 1',
        );
    }

    public static function carrierProfile(): CarrierProfile
    {
        return new CarrierProfile(
            carrierCode: 'stockflow-express',
            serviceLevel: 'standard',
        );
    }

    public static function createShipmentCommand(
        string $orderId = 'ord_123',
        ?string $shipmentId = 'shp_test_001',
        ?\DateTimeImmutable $occurredAt = null,
    ): CreateShipmentCommand {
        return new CreateShipmentCommand(
            orderId: $orderId,
            deliveryAddress: self::deliveryAddress(),
            carrierProfile: self::carrierProfile(),
            occurredAt: $occurredAt ?? new \DateTimeImmutable('2026-05-31T10:00:00+00:00'),
            shipmentId: $shipmentId,
        );
    }

    public static function lifecycleService(?InMemoryShipmentRepository $repository = null): ShipmentLifecycleService
    {
        return new ShipmentLifecycleService($repository ?? new InMemoryShipmentRepository());
    }
}
