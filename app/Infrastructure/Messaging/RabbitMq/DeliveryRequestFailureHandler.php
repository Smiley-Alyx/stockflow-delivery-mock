<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use App\Support\DeliveryStructuredLogger;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final class DeliveryRequestFailureHandler
{
    public function handleInvalidMessage(
        AMQPChannel $channel,
        AMQPMessage $message,
        InvalidMessageException $exception,
    ): void {
        DeliveryStructuredLogger::warning('delivery invalid message rejected', [
            'routing_key' => (string) ($message->getRoutingKey() ?? ''),
            'error' => $exception->getMessage(),
        ]);

        $channel->basic_nack($message->getDeliveryTag(), false, false);
    }

    public function handleProcessingFailure(
        AMQPChannel $channel,
        AMQPMessage $message,
        Throwable $exception,
    ): void {
        DeliveryStructuredLogger::error('delivery message processing failed', [
            'routing_key' => (string) ($message->getRoutingKey() ?? ''),
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);

        $channel->basic_nack($message->getDeliveryTag(), false, false);
    }
}
