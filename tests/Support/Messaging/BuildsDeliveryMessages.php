<?php

declare(strict_types=1);

namespace Tests\Support\Messaging;

use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;
use App\Infrastructure\Messaging\RabbitMq\MessageHeaders;

trait BuildsDeliveryMessages
{
    /**
     * @return array<string, mixed>
     */
    protected function shipmentRequestPayload(
        string $shipmentId = 'shp_integration_001',
        string $orderId = 'ord_integration_001',
        ?array $deliveryAddress = null,
        ?array $carrierProfile = null,
    ): array {
        return [
            'shipment_id' => $shipmentId,
            'order_id' => $orderId,
            'delivery_address' => $deliveryAddress ?? \Tests\Support\DeliveryTestFixtures::deliveryAddress()->toArray(),
            'carrier_profile' => $carrierProfile ?? \Tests\Support\DeliveryTestFixtures::carrierProfile()->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function cancelRequestPayload(
        string $shipmentId,
        string $orderId,
        ?string $reason = 'customer_cancelled',
    ): array {
        $payload = [
            'shipment_id' => $shipmentId,
            'order_id' => $orderId,
        ];

        if ($reason !== null) {
            $payload['reason'] = $reason;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function incoming(
        string $routingKey,
        array $payload,
        string $idempotencyKey = 'idem_test_001',
        string $messageId = 'msg_test_001',
    ): IncomingMessage {
        return new IncomingMessage(
            routingKey: $routingKey,
            headers: new MessageHeaders(
                messageId: $messageId,
                correlationId: 'cor_test_001',
                causationId: 'msg_cause_001',
                idempotencyKey: $idempotencyKey,
                schemaVersion: 'v1',
                occurredAt: '2026-05-31T10:20:00Z',
                producer: 'stockflow-market',
            ),
            payload: $payload,
            body: (string) json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
