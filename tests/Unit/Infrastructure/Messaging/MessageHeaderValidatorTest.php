<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Messaging;

use App\Infrastructure\Messaging\RabbitMq\Exceptions\InvalidMessageException;
use App\Infrastructure\Messaging\RabbitMq\MessageHeaderValidator;
use PHPUnit\Framework\TestCase;

final class MessageHeaderValidatorTest extends TestCase
{
    private MessageHeaderValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new MessageHeaderValidator();
    }

    public function test_validates_required_headers(): void
    {
        $headers = $this->validator->validate([
            'application_headers' => [
                'message_id' => 'msg_test_001',
                'correlation_id' => 'cor_test_001',
                'causation_id' => 'msg_cause_001',
                'idempotency_key' => 'idem_test_001',
                'schema_version' => 'v1',
                'occurred_at' => '2026-05-31T10:00:00Z',
                'producer' => 'stockflow-market',
            ],
        ]);

        $this->assertSame('cor_test_001', $headers->correlationId);
        $this->assertSame('idem_test_001', $headers->idempotencyKey);
    }

    public function test_rejects_missing_header(): void
    {
        $this->expectException(InvalidMessageException::class);

        $this->validator->validate([
            'application_headers' => [
                'message_id' => 'msg_test_001',
            ],
        ]);
    }

    public function test_rejects_unsupported_schema_version(): void
    {
        $this->expectException(InvalidMessageException::class);

        $this->validator->validate([
            'application_headers' => [
                'message_id' => 'msg_test_001',
                'correlation_id' => 'cor_test_001',
                'causation_id' => 'msg_cause_001',
                'idempotency_key' => 'idem_test_001',
                'schema_version' => 'v2',
                'occurred_at' => '2026-05-31T10:00:00Z',
                'producer' => 'stockflow-market',
            ],
        ]);
    }
}
