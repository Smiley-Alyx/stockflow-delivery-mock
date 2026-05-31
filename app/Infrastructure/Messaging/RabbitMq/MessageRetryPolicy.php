<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\RabbitMq;

use App\Domain\Delivery\Exceptions\IdempotencyConflictException;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\PublishedEventConflictException;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\RetryableMessageException;
use Throwable;

final class MessageRetryPolicy
{
    public function __construct(
        private readonly RabbitMqConfig $config,
    ) {
    }

    public function isRetryable(Throwable $exception): bool
    {
        if ($exception instanceof InvalidMessageException) {
            return false;
        }

        if ($exception instanceof PublishedEventConflictException) {
            return false;
        }

        if ($exception instanceof IdempotencyConflictException) {
            return false;
        }

        if ($exception instanceof RetryableMessageException) {
            return true;
        }

        return true;
    }

    public function shouldRetry(int $currentRetryCount): bool
    {
        return $currentRetryCount < $this->config->maxRetryAttempts;
    }
}
