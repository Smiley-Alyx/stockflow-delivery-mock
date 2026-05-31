<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

final readonly class RabbitMqConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $user,
        public string $password,
        public string $vhost,
        public string $exchange,
        public string $deadLetterExchange,
        public string $requestsQueue,
        public string $retryQueue,
        public string $dlq,
        public int $prefetchCount,
        public int $consumerTimeoutSeconds,
        public bool $setupTopology,
        public bool $publishEvents,
        public int $maxRetryAttempts,
        public int $retryDelayMs,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            host: (string) $config['host'],
            port: (int) $config['port'],
            user: (string) $config['user'],
            password: (string) $config['password'],
            vhost: (string) $config['vhost'],
            exchange: (string) $config['exchange'],
            deadLetterExchange: (string) $config['dead_letter_exchange'],
            requestsQueue: (string) $config['requests_queue'],
            retryQueue: (string) $config['retry_queue'],
            dlq: (string) $config['dlq'],
            prefetchCount: (int) $config['prefetch_count'],
            consumerTimeoutSeconds: (int) $config['consumer_timeout_seconds'],
            setupTopology: filter_var($config['setup_topology'], FILTER_VALIDATE_BOOL),
            publishEvents: filter_var($config['publish_events'], FILTER_VALIDATE_BOOL),
            maxRetryAttempts: (int) $config['max_retry_attempts'],
            retryDelayMs: (int) $config['retry_delay_ms'],
        );
    }

    /**
     * @return list<string>
     */
    public function incomingRoutingKeys(): array
    {
        return [
            'delivery.shipment.requested.v1',
            'delivery.shipment.cancel_requested.v1',
        ];
    }
}
