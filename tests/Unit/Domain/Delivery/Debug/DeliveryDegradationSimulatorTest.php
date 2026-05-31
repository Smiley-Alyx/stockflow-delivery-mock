<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Delivery\Debug;

use App\Domain\Delivery\Enums\FailureMode;
use App\Domain\Delivery\Services\Debug\DeliveryOperation;
use App\Domain\Delivery\Services\Debug\FailureModeManager;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\RetryableMessageException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Debug\TestFailureModeSupport;

final class DeliveryDegradationSimulatorTest extends TestCase
{
    private FailureModeManager $failureModeManager;

    protected function setUp(): void
    {
        parent::setUp();

        TestFailureModeSupport::resetStateFile();
        $this->failureModeManager = TestFailureModeSupport::manager();
    }

    public function test_always_reject_creation_mode_returns_creation_failure(): void
    {
        $this->failureModeManager->set(FailureMode::AlwaysRejectCreation);
        $simulator = TestFailureModeSupport::simulator($this->failureModeManager);

        $failure = $simulator->creationFailure();

        $this->assertSame('creation_failed', $failure['code'] ?? null);
        $this->assertSame('Simulated shipment creation rejection.', $failure['message'] ?? null);
    }

    public function test_invalid_address_mode_returns_address_invalid_failure(): void
    {
        $this->failureModeManager->set(FailureMode::InvalidAddress);
        $simulator = TestFailureModeSupport::simulator($this->failureModeManager);

        $failure = $simulator->creationFailure();

        $this->assertSame('address_invalid', $failure['code'] ?? null);
    }

    public function test_random_reject_creation_mode_uses_roll(): void
    {
        $this->failureModeManager->set(FailureMode::RandomRejectCreation);
        $simulator = TestFailureModeSupport::simulator($this->failureModeManager);

        $this->assertNotNull($simulator->creationFailure(0));
        $this->assertNull($simulator->creationFailure(1));
    }

    public function test_cancel_failure_mode_returns_cancel_failure(): void
    {
        $this->failureModeManager->set(FailureMode::CancelFailure);
        $simulator = TestFailureModeSupport::simulator($this->failureModeManager);

        $failure = $simulator->cancelFailure();

        $this->assertSame('delivery_failed', $failure['code'] ?? null);
    }

    public function test_timeout_mode_throws_retryable_exception(): void
    {
        $this->failureModeManager->set(FailureMode::Timeout);
        $simulator = TestFailureModeSupport::simulator($this->failureModeManager);

        $this->expectException(RetryableMessageException::class);

        $simulator->beforeProcessing(DeliveryOperation::ShipmentCreate);
    }

    public function test_publish_failure_mode_blocks_event_publish(): void
    {
        $this->failureModeManager->set(FailureMode::PublishFailure);
        $simulator = TestFailureModeSupport::simulator($this->failureModeManager);

        $this->expectException(RetryableMessageException::class);

        $simulator->assertPublishAllowed();
    }

    public function test_duplicate_response_mode_is_enabled_only_when_active(): void
    {
        $simulator = TestFailureModeSupport::simulator($this->failureModeManager);

        $this->assertFalse($simulator->shouldDuplicatePublishedResponse());

        $this->failureModeManager->set(FailureMode::DuplicateResponse);

        $this->assertTrue($simulator->shouldDuplicatePublishedResponse());
    }
}
