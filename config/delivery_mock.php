<?php

declare(strict_types=1);

return [
    'service_name' => getenv('DELIVERY_MOCK_SERVICE_NAME') ?: 'stockflow-delivery-mock',
    'http_port' => (int) (getenv('DELIVERY_MOCK_HTTP_PORT') ?: 8080),
    'debug_enabled' => filter_var(getenv('DELIVERY_MOCK_DEBUG_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL),
    'rabbitmq' => [
        'host' => getenv('RABBITMQ_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('RABBITMQ_PORT') ?: 5672),
        'user' => getenv('RABBITMQ_USER') ?: 'stockflow',
        'password' => getenv('RABBITMQ_PASSWORD') ?: 'stockflow',
        'vhost' => getenv('RABBITMQ_VHOST') ?: '/',
        'exchange' => getenv('RABBITMQ_EXCHANGE') ?: 'stockflow.delivery',
        'dead_letter_exchange' => getenv('RABBITMQ_DLX') ?: 'stockflow.delivery.dlx',
        'requests_queue' => getenv('RABBITMQ_REQUESTS_QUEUE') ?: 'stockflow.delivery.requests',
        'retry_queue' => getenv('RABBITMQ_RETRY_QUEUE') ?: 'stockflow.delivery.requests.retry',
        'dlq' => getenv('RABBITMQ_DLQ') ?: 'stockflow.delivery.requests.dlq',
        'prefetch_count' => (int) (getenv('RABBITMQ_PREFETCH_COUNT') ?: 1),
        'consumer_timeout_seconds' => (int) (getenv('RABBITMQ_CONSUMER_TIMEOUT_SECONDS') ?: 30),
        'setup_topology' => filter_var(getenv('RABBITMQ_SETUP_TOPOLOGY') ?: 'true', FILTER_VALIDATE_BOOL),
        'publish_events' => filter_var(getenv('DELIVERY_MOCK_PUBLISH_EVENTS') ?: 'true', FILTER_VALIDATE_BOOL),
        'max_retry_attempts' => (int) (getenv('RABBITMQ_MAX_RETRY_ATTEMPTS') ?: 3),
        'retry_delay_ms' => (int) (getenv('RABBITMQ_RETRY_DELAY_MS') ?: 5000),
    ],
];
