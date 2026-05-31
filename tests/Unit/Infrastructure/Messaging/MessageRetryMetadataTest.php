<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Messaging;

use App\Infrastructure\Messaging\RabbitMq\MessageRetryMetadata;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\TestCase;

final class MessageRetryMetadataTest extends TestCase
{
    public function test_reads_defaults_when_retry_headers_are_missing(): void
    {
        $metadata = MessageRetryMetadata::fromAmqpMessage(new AMQPMessage('{}'));

        $this->assertSame(0, $metadata->retryCount);
        $this->assertNull($metadata->originalRoutingKey);
        $this->assertNull($metadata->retryAfterEpochMs);
    }

    public function test_reads_retry_headers_from_application_headers(): void
    {
        $metadata = MessageRetryMetadata::fromAmqpMessage(new AMQPMessage('{}', [
            'application_headers' => new AMQPTable([
                'x-retry-count' => '2',
                'x-original-routing-key' => 'delivery.shipment.requested.v1',
                'x-retry-after' => '1717156501000',
            ]),
        ]));

        $this->assertSame(2, $metadata->retryCount);
        $this->assertSame('delivery.shipment.requested.v1', $metadata->originalRoutingKey);
        $this->assertSame(1717156501000, $metadata->retryAfterEpochMs);
    }
}
