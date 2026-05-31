<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq\Contracts;

use App\Domain\Delivery\DTO\CancelShipmentCommand;
use App\Domain\Delivery\DTO\CreateShipmentCommand;
use App\Domain\Delivery\Enums\ShipmentStatus;
use App\Domain\Delivery\Models\Shipment;
use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;

interface DeliveryEventPublisher
{
    public function publishShipmentCreated(IncomingMessage $incoming, Shipment $shipment): void;

    public function publishShipmentCreationFailed(
        IncomingMessage $incoming,
        CreateShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
    ): void;

    public function publishShipmentStatusChanged(
        IncomingMessage $incoming,
        Shipment $shipment,
        ShipmentStatus $previousStatus,
        ?string $reason = null,
    ): void;

    public function publishShipmentCancelled(IncomingMessage $incoming, Shipment $shipment): void;

    public function publishShipmentCancelFailed(
        IncomingMessage $incoming,
        CancelShipmentCommand $command,
        string $failureCode,
        string $failureMessage,
        ?ShipmentStatus $currentStatus = null,
    ): void;
}
