<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Messaging;

use App\Infrastructure\Messaging\RabbitMq\MessageHeaders;
use App\Infrastructure\Messaging\RabbitMq\PublishedDeliveryEvent;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessagePublisher;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Messaging\RabbitMqTestConfig;

final class RabbitMqMessagePublisherTest extends TestCase
{
    public function test_reconnects_after_publish_failure(): void
    {
        $firstChannel = $this->createMock(AMQPChannel::class);
        $firstChannel->expects($this->once())
            ->method('basic_publish')
            ->willThrowException(new RuntimeException('closed'));
        $firstChannel->expects($this->once())->method('is_open')->willReturn(false);

        $secondChannel = $this->createMock(AMQPChannel::class);
        $secondChannel->expects($this->once())->method('basic_publish');

        $firstConnection = $this->createMock(AMQPStreamConnection::class);
        $firstConnection->expects($this->once())->method('channel')->willReturn($firstChannel);
        $firstConnection->expects($this->once())->method('isConnected')->willReturn(false);

        $secondConnection = $this->createMock(AMQPStreamConnection::class);
        $secondConnection->expects($this->once())->method('channel')->willReturn($secondChannel);

        $connectionFactory = $this->createMock(RabbitMqConnectionFactory::class);
        $connectionFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($firstConnection, $secondConnection);

        $publisher = new RabbitMqMessagePublisher(RabbitMqTestConfig::make(), $connectionFactory);

        try {
            $publisher->publish($this->event());
            $this->fail('Expected the first publish to fail.');
        } catch (RuntimeException) {
        }

        $publisher->publish($this->event());
    }

    private function event(): PublishedDeliveryEvent
    {
        return new PublishedDeliveryEvent(
            routingKey: 'delivery.shipment.created.v1',
            headers: new MessageHeaders(
                messageId: 'msg_demo_001',
                correlationId: 'corr_demo_001',
                causationId: 'msg_request_001',
                idempotencyKey: 'shipment:shp_demo_001',
                schemaVersion: 'v1',
                occurredAt: '2026-06-01T10:15:01Z',
                producer: 'stockflow-delivery-mock',
            ),
            payload: ['shipment_id' => 'shp_demo_001'],
        );
    }
}
