<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Messaging;

use App\Infrastructure\Messaging\RabbitMq\IncomingMessage;
use App\Infrastructure\Messaging\RabbitMq\MessageHeaders;
use App\Infrastructure\Messaging\RabbitMq\OutgoingMessageHeadersFactory;
use PHPUnit\Framework\TestCase;

final class OutgoingMessageHeadersFactoryTest extends TestCase
{
    public function test_builds_response_headers_from_incoming_message(): void
    {
        $incoming = new IncomingMessage(
            routingKey: 'delivery.shipment.requested.v1',
            headers: new MessageHeaders(
                messageId: 'msg_shp_req_001',
                correlationId: 'cor_fulfillment_001',
                causationId: 'msg_parent_001',
                idempotencyKey: 'idem-shp-ord_001',
                schemaVersion: 'v1',
                occurredAt: '2026-05-31T10:20:00Z',
                producer: 'stockflow-market',
            ),
            payload: [],
            body: '{}',
        );

        $headers = (new OutgoingMessageHeadersFactory('stockflow-delivery-mock'))->forResponse($incoming);

        $this->assertStringStartsWith('msg_', $headers->messageId);
        $this->assertNotSame('msg_shp_req_001', $headers->messageId);
        $this->assertSame('cor_fulfillment_001', $headers->correlationId);
        $this->assertSame('msg_shp_req_001', $headers->causationId);
        $this->assertSame('idem-shp-ord_001', $headers->idempotencyKey);
        $this->assertSame('v1', $headers->schemaVersion);
        $this->assertSame('stockflow-delivery-mock', $headers->producer);
    }

    public function test_builds_status_change_headers_with_derived_idempotency_key(): void
    {
        $incoming = new IncomingMessage(
            routingKey: 'delivery.shipment.requested.v1',
            headers: new MessageHeaders(
                messageId: 'msg_shp_req_001',
                correlationId: 'cor_fulfillment_001',
                causationId: 'msg_parent_001',
                idempotencyKey: 'idem-shp-ord_001',
                schemaVersion: 'v1',
                occurredAt: '2026-05-31T10:20:00Z',
                producer: 'stockflow-market',
            ),
            payload: [],
            body: '{}',
        );

        $headers = (new OutgoingMessageHeadersFactory('stockflow-delivery-mock'))->forStatusChange(
            $incoming,
            'shp_demo_001',
            'label_generated',
        );

        $this->assertSame('cor_fulfillment_001', $headers->correlationId);
        $this->assertSame('msg_shp_req_001', $headers->causationId);
        $this->assertSame('idem-shp-status-shp_demo_001-label_generated', $headers->idempotencyKey);
    }
}
