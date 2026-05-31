<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use PhpAmqpLib\Connection\AMQPStreamConnection;

final class RabbitMqConnectionFactory
{
    public function __construct(
        private readonly RabbitMqConfig $config,
    ) {
    }

    public function create(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            host: $this->config->host,
            port: $this->config->port,
            user: $this->config->user,
            password: $this->config->password,
            vhost: $this->config->vhost,
            heartbeat: 30,
        );
    }
}
