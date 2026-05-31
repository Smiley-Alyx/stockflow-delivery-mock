<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;

final class ShipmentMessageDispatcher
{
    public function __construct(
        private readonly ShipmentRequestedHandler $shipmentRequestedHandler,
        private readonly ShipmentCancelRequestedHandler $shipmentCancelRequestedHandler,
    ) {
    }

    public function dispatch(IncomingMessage $message): void
    {
        match ($message->routingKey) {
            'delivery.shipment.requested.v1' => $this->shipmentRequestedHandler->handle($message),
            'delivery.shipment.cancel_requested.v1' => $this->shipmentCancelRequestedHandler->handle($message),
            default => throw new InvalidMessageException(sprintf('Unsupported routing key: %s', $message->routingKey)),
        };
    }
}
