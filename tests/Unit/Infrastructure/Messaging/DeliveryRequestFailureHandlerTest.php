<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Messaging;

use App\Infrastructure\Messaging\RabbitMq\DeliveryRequestFailureHandler;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\PublishedEventConflictException;
use App\Infrastructure\Messaging\RabbitMq\MessageRetryPolicy;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Messaging\RabbitMqTestConfig;
use Tests\Support\Observability\TestMetricsSupport;
use Tests\Support\Messaging\RecordingDeliveryDlqPublisher;
use Tests\Support\Messaging\RecordingDeliveryRequestRetryPublisher;

final class DeliveryRequestFailureHandlerTest extends TestCase
{
    public function test_schedules_retry_for_transient_failure(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('basic_ack')->with(7);

        $retryPublisher = new RecordingDeliveryRequestRetryPublisher();
        $dlqPublisher = new RecordingDeliveryDlqPublisher();

        $handler = new DeliveryRequestFailureHandler(
            new MessageRetryPolicy(RabbitMqTestConfig::make(maxRetryAttempts: 3)),
            $retryPublisher,
            $dlqPublisher,
            TestMetricsSupport::recorder(),
        );

        $message = new AMQPMessage('{}');
        $message->setDeliveryTag(7);

        $handler->handleProcessingFailure($channel, $message, new RuntimeException('temporary'));

        $this->assertCount(1, $retryPublisher->published);
        $this->assertSame(1, $retryPublisher->published[0]['retryCount']);
        $this->assertSame([], $dlqPublisher->published);
    }

    public function test_moves_non_retryable_failure_to_dlq(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('basic_ack')->with(9);

        $retryPublisher = new RecordingDeliveryRequestRetryPublisher();
        $dlqPublisher = new RecordingDeliveryDlqPublisher();

        $handler = new DeliveryRequestFailureHandler(
            new MessageRetryPolicy(RabbitMqTestConfig::make(maxRetryAttempts: 3)),
            $retryPublisher,
            $dlqPublisher,
            TestMetricsSupport::recorder(),
        );

        $message = new AMQPMessage('{}');
        $message->setDeliveryTag(9);

        $handler->handleProcessingFailure(
            $channel,
            $message,
            PublishedEventConflictException::forOperation('delivery.shipment.created', 'shp_1', 'idem_1'),
        );

        $this->assertSame([], $retryPublisher->published);
        $this->assertCount(1, $dlqPublisher->published);
    }

    public function test_moves_exhausted_retries_to_dlq(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('basic_ack')->with(11);

        $retryPublisher = new RecordingDeliveryRequestRetryPublisher();
        $dlqPublisher = new RecordingDeliveryDlqPublisher();

        $handler = new DeliveryRequestFailureHandler(
            new MessageRetryPolicy(RabbitMqTestConfig::make(maxRetryAttempts: 3)),
            $retryPublisher,
            $dlqPublisher,
            TestMetricsSupport::recorder(),
        );

        $message = new AMQPMessage('{}', [
            'application_headers' => new AMQPTable([
                'x-retry-count' => '3',
            ]),
        ]);
        $message->setDeliveryTag(11);

        $handler->handleProcessingFailure($channel, $message, new RuntimeException('still failing'));

        $this->assertSame([], $retryPublisher->published);
        $this->assertCount(1, $dlqPublisher->published);
    }

    public function test_rejects_invalid_messages_without_retry(): void
    {
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())->method('basic_reject')->with(3, false);

        $handler = new DeliveryRequestFailureHandler(
            new MessageRetryPolicy(RabbitMqTestConfig::make(maxRetryAttempts: 3)),
            new RecordingDeliveryRequestRetryPublisher(),
            new RecordingDeliveryDlqPublisher(),
            TestMetricsSupport::recorder(),
        );

        $message = new AMQPMessage('{}');
        $message->setDeliveryTag(3);

        $handler->handleInvalidMessage($channel, $message, new InvalidMessageException('bad payload'));
    }
}
