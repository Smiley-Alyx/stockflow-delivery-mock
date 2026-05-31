<?php

declare(strict_types=1);

namespace Tests\Support\Messaging;

use App\Infrastructure\Messaging\RabbitMq\RabbitMqConfig;

final class RabbitMqTestConfig
{
    public static function make(int $maxRetryAttempts = 3, int $retryDelayMs = 0): RabbitMqConfig
    {
        return new RabbitMqConfig(
            host: '127.0.0.1',
            port: 5672,
            user: 'stockflow',
            password: 'stockflow',
            vhost: '/',
            exchange: 'stockflow.delivery',
            deadLetterExchange: 'stockflow.delivery.dlx',
            requestsQueue: 'stockflow.delivery.requests',
            retryQueue: 'stockflow.delivery.requests.retry',
            dlq: 'stockflow.delivery.requests.dlq',
            prefetchCount: 1,
            consumerTimeoutSeconds: 30,
            setupTopology: true,
            publishEvents: true,
            maxRetryAttempts: $maxRetryAttempts,
            retryDelayMs: $retryDelayMs,
        );
    }
}
