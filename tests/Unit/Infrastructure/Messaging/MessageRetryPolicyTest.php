<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Messaging;

use App\Domain\Delivery\Exceptions\IdempotencyConflictException;
use App\Domain\Delivery\Models\IdempotencyRecord;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\PublishedEventConflictException;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\RetryableMessageException;
use App\Infrastructure\Messaging\RabbitMq\MessageRetryPolicy;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Messaging\RabbitMqTestConfig;

final class MessageRetryPolicyTest extends TestCase
{
    public function test_invalid_messages_are_not_retryable(): void
    {
        $policy = new MessageRetryPolicy(RabbitMqTestConfig::make());

        $this->assertFalse($policy->isRetryable(new InvalidMessageException('bad headers')));
    }

    public function test_published_event_conflicts_are_not_retryable(): void
    {
        $policy = new MessageRetryPolicy(RabbitMqTestConfig::make());

        $this->assertFalse($policy->isRetryable(
            PublishedEventConflictException::forOperation('delivery.shipment.created', 'shp_1', 'idem_1'),
        ));
    }

    public function test_idempotency_conflicts_are_not_retryable(): void
    {
        $policy = new MessageRetryPolicy(RabbitMqTestConfig::make());

        $this->assertFalse($policy->isRetryable(
            IdempotencyConflictException::forRecord(new IdempotencyRecord(
                recordId: 'idr_1',
                operation: IdempotencyRecord::OPERATION_CREATE,
                shipmentId: 'shp_1',
                idempotencyKey: 'idem_1',
                responseFingerprint: 'fp_1',
                createdAt: new \DateTimeImmutable(),
            )),
        ));
    }

    public function test_generic_failures_are_retryable(): void
    {
        $policy = new MessageRetryPolicy(RabbitMqTestConfig::make());

        $this->assertTrue($policy->isRetryable(new RuntimeException('db unavailable')));
        $this->assertTrue($policy->isRetryable(new RetryableMessageException('transient')));
    }

    public function test_should_retry_until_max_attempts(): void
    {
        $policy = new MessageRetryPolicy(RabbitMqTestConfig::make(maxRetryAttempts: 3));

        $this->assertTrue($policy->shouldRetry(0));
        $this->assertTrue($policy->shouldRetry(2));
        $this->assertFalse($policy->shouldRetry(3));
    }
}
