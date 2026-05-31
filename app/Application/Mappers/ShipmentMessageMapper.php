<?php

declare(strict_types=1);

namespace App\Application\Mappers;

use App\Domain\Delivery\DTO\CancelShipmentCommand;
use App\Domain\Delivery\DTO\CreateShipmentCommand;
use App\Domain\Delivery\Models\CarrierProfile;
use App\Domain\Delivery\Models\DeliveryAddress;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;

final class ShipmentMessageMapper
{
    public function toCreateShipmentCommand(IncomingMessage $message): CreateShipmentCommand
    {
        $payload = $message->payload;

        foreach (['shipment_id', 'order_id', 'delivery_address', 'carrier_profile'] as $field) {
            if (! array_key_exists($field, $payload)) {
                throw new InvalidMessageException(sprintf('Missing shipment request field: %s', $field));
            }
        }

        if (! is_array($payload['delivery_address'])) {
            throw new InvalidMessageException('Invalid delivery_address.');
        }

        if (! is_array($payload['carrier_profile'])) {
            throw new InvalidMessageException('Invalid carrier_profile.');
        }

        try {
            $deliveryAddress = DeliveryAddress::fromArray($payload['delivery_address']);
            $carrierProfile = CarrierProfile::fromArray($payload['carrier_profile']);
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidMessageException($exception->getMessage(), previous: $exception);
        }

        return new CreateShipmentCommand(
            orderId: (string) $payload['order_id'],
            deliveryAddress: $deliveryAddress,
            carrierProfile: $carrierProfile,
            occurredAt: new \DateTimeImmutable($message->headers->occurredAt),
            shipmentId: (string) $payload['shipment_id'],
        );
    }

    public function toCancelShipmentCommand(IncomingMessage $message): CancelShipmentCommand
    {
        $payload = $message->payload;

        foreach (['shipment_id', 'order_id'] as $field) {
            if (! array_key_exists($field, $payload)) {
                throw new InvalidMessageException(sprintf('Missing shipment cancel field: %s', $field));
            }
        }

        $reason = null;

        if (isset($payload['reason']) && is_string($payload['reason']) && trim($payload['reason']) !== '') {
            $reason = trim($payload['reason']);
        }

        return new CancelShipmentCommand(
            shipmentId: (string) $payload['shipment_id'],
            orderId: (string) $payload['order_id'],
            occurredAt: new \DateTimeImmutable($message->headers->occurredAt),
            reason: $reason,
            idempotencyKey: $message->headers->idempotencyKey,
            messageId: $message->headers->messageId,
            correlationId: $message->headers->correlationId,
        );
    }
}
