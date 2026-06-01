<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class RabbitMqMessagePublisher
{
    private ?AbstractConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly RabbitMqConfig $config,
        private readonly RabbitMqConnectionFactory $connectionFactory,
    ) {
    }

    public function publish(PublishedDeliveryEvent $event): void
    {
        $message = new AMQPMessage(
            json_encode($event->payload, JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'application_headers' => new AMQPTable($event->headers->toArray()),
            ],
        );

        try {
            $this->publishMessage($message, $event->routingKey);
        } catch (Throwable) {
            $this->discardConnection();

            try {
                $this->publishMessage($message, $event->routingKey);
            } catch (Throwable $exception) {
                $this->discardConnection();

                throw $exception;
            }
        }
    }

    public function close(): void
    {
        $this->discardConnection();
    }

    private function channel(): AMQPChannel
    {
        if (
            $this->channel !== null
            && $this->channel->is_open()
            && $this->connection?->isConnected()
        ) {
            return $this->channel;
        }

        $this->discardConnection();
        $this->connection = $this->connectionFactory->create();
        $this->channel = $this->connection->channel();

        return $this->channel;
    }

    private function publishMessage(AMQPMessage $message, string $routingKey): void
    {
        $this->channel()->basic_publish(
            $message,
            $this->config->exchange,
            $routingKey,
        );
    }

    private function discardConnection(): void
    {
        $channel = $this->channel;
        $connection = $this->connection;
        $this->channel = null;
        $this->connection = null;

        try {
            if ($channel?->is_open()) {
                $channel->close();
            }
        } catch (Throwable) {
        }

        try {
            if ($connection?->isConnected()) {
                $connection->close();
            }
        } catch (Throwable) {
        }
    }
}
