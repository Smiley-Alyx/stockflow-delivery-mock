<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Messaging;

use App\Infrastructure\Messaging\RabbitMq\DeliveryRetryRequeueHandler;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Messaging\RabbitMqTestConfig;
use Tests\Support\Observability\TestMetricsSupport;

final class DeliveryRetryRequeueHandlerTest extends TestCase
{
    public function test_republishes_retry_message_to_main_exchange(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())
            ->method('basic_publish')
            ->with(
                $this->isInstanceOf(AMQPMessage::class),
                'stockflow.delivery',
                'delivery.shipment.requested.v1',
            );
        $channel->expects($this->once())->method('basic_ack')->with(5);

        $handler = new DeliveryRetryRequeueHandler(RabbitMqTestConfig::make(retryDelayMs: 0), TestMetricsSupport::recorder());

        $message = new AMQPMessage('{"shipment_id":"shp_1"}', [
            'application_headers' => new AMQPTable([
                'x-retry-count' => '1',
                'x-original-routing-key' => 'delivery.shipment.requested.v1',
            ]),
        ]);
        $message->setDeliveryTag(5);

        $handler->handle($channel, $message);
    }

    public function test_nacks_message_when_retry_delay_has_not_elapsed(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('basic_nack')->with(8, false, true);
        $channel->expects($this->never())->method('basic_publish');

        $handler = new DeliveryRetryRequeueHandler(RabbitMqTestConfig::make(retryDelayMs: 5000), TestMetricsSupport::recorder());

        $message = new AMQPMessage('{}', [
            'application_headers' => new AMQPTable([
                'x-retry-count' => '1',
                'x-original-routing-key' => 'delivery.shipment.requested.v1',
                'x-retry-after' => (string) ((int) (microtime(true) * 1000) + 60_000),
            ]),
        ]);
        $message->setDeliveryTag(8);

        $handler->handle($channel, $message);
    }

    public function test_rejects_retry_message_missing_original_routing_key(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('basic_reject')->with(12, false);
        $channel->expects($this->never())->method('basic_publish');

        $handler = new DeliveryRetryRequeueHandler(RabbitMqTestConfig::make(), TestMetricsSupport::recorder());

        $message = new AMQPMessage('{}', [
            'application_headers' => new AMQPTable([
                'x-retry-count' => '1',
            ]),
        ]);
        $message->setDeliveryTag(12);

        $handler->handle($channel, $message);
    }
}
