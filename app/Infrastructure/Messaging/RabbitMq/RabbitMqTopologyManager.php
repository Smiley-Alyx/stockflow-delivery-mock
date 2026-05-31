<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

final class RabbitMqTopologyManager
{
    public function __construct(
        private readonly RabbitMqConfig $config,
    ) {
    }

    public function declare(AMQPChannel $channel): void
    {
        $channel->exchange_declare(
            $this->config->exchange,
            'topic',
            false,
            true,
            false,
        );

        $channel->exchange_declare(
            $this->config->deadLetterExchange,
            'topic',
            false,
            true,
            false,
        );

        $channel->queue_declare(
            $this->config->requestsQueue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable([
                'x-dead-letter-exchange' => $this->config->deadLetterExchange,
                'x-dead-letter-routing-key' => $this->config->dlq,
            ]),
        );

        $channel->queue_declare(
            $this->config->retryQueue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable([
                'x-dead-letter-exchange' => $this->config->deadLetterExchange,
                'x-dead-letter-routing-key' => $this->config->dlq,
            ]),
        );

        $channel->queue_declare(
            $this->config->dlq,
            false,
            true,
            false,
            false,
        );

        foreach ($this->config->incomingRoutingKeys() as $routingKey) {
            $channel->queue_bind(
                $this->config->requestsQueue,
                $this->config->exchange,
                $routingKey,
            );
        }

        $channel->queue_bind(
            $this->config->dlq,
            $this->config->deadLetterExchange,
            $this->config->dlq,
        );
    }
}
