<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Services\Debug;

use App\Domain\Delivery\Enums\FailureMode;
use App\Infrastructure\Messaging\RabbitMq\Exceptions\RetryableMessageException;

final class DeliveryDegradationSimulator
{
    public function __construct(
        private readonly FailureModeManager $failureModeManager,
        private readonly int $processingDelayMs,
    ) {
    }

    public function beforeProcessing(DeliveryOperation $operation): void
    {
        match ($this->failureModeManager->current()) {
            FailureMode::ProcessingDelay => $this->applyProcessingDelay(),
            FailureMode::Timeout => throw new RetryableMessageException(sprintf(
                'Simulated processing timeout during %s.',
                $operation->value,
            )),
            FailureMode::ProviderUnavailable => throw new RetryableMessageException(sprintf(
                'Simulated provider unavailable during %s.',
                $operation->value,
            )),
            default => null,
        };
    }

    /**
     * @return array{code: string, message: string}|null
     */
    public function creationFailure(?int $randomRoll = null): ?array
    {
        return match ($this->failureModeManager->current()) {
            FailureMode::AlwaysRejectCreation => [
                'code' => 'creation_failed',
                'message' => 'Simulated shipment creation rejection.',
            ],
            FailureMode::InvalidAddress => [
                'code' => 'address_invalid',
                'message' => 'Delivery address could not be validated for carrier routing.',
            ],
            FailureMode::RandomRejectCreation => $this->shouldRandomlyFail($randomRoll)
                ? [
                    'code' => 'creation_failed',
                    'message' => 'Simulated random shipment creation rejection.',
                ]
                : null,
            default => null,
        };
    }

    /**
     * @return array{code: string, message: string}|null
     */
    public function cancelFailure(?int $randomRoll = null): ?array
    {
        return match ($this->failureModeManager->current()) {
            FailureMode::CancelFailure => [
                'code' => 'delivery_failed',
                'message' => 'Simulated shipment cancel rejection.',
            ],
            default => null,
        };
    }

    public function assertPublishAllowed(): void
    {
        if ($this->failureModeManager->current() === FailureMode::PublishFailure) {
            throw new RetryableMessageException('Simulated delivery event publish failure.');
        }
    }

    public function shouldDuplicatePublishedResponse(): bool
    {
        return $this->failureModeManager->current() === FailureMode::DuplicateResponse;
    }

    private function applyProcessingDelay(): void
    {
        if ($this->processingDelayMs <= 0) {
            return;
        }

        usleep($this->processingDelayMs * 1000);
    }

    private function shouldRandomlyFail(?int $randomRoll): bool
    {
        $roll = $randomRoll ?? random_int(0, 1);

        return $roll === 0;
    }
}
