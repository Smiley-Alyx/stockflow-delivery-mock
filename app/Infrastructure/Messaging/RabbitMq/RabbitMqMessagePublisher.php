<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

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

        $this->channel()->basic_publish(
            $message,
            $this->config->exchange,
            $event->routingKey,
        );
    }

    public function close(): void
    {
        if ($this->channel !== null) {
            $this->channel->close();
            $this->channel = null;
        }

        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null) {
            return $this->channel;
        }

        $this->connection = $this->connectionFactory->create();
        $this->channel = $this->connection->channel();

        return $this->channel;
    }
}
