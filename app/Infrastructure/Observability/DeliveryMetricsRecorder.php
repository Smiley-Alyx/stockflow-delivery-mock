<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use App\Domain\Delivery\Enums\FailureMode;
use App\Domain\Delivery\Services\Debug\FailureModeManager;
use App\Infrastructure\Observability\Prometheus\PrometheusRegistry;

final class DeliveryMetricsRecorder
{
    public function __construct(
        private readonly PrometheusRegistry $registry,
        private readonly FailureModeManager $failureModeManager,
    ) {
    }

    public function recordRequestProcessed(
        string $operation,
        string $routingKey,
        string $outcome,
        float $durationSeconds,
        bool $idempotentReplay = false,
    ): void {
        $this->registry->incrementCounter('delivery_requests_total', [
            'operation' => $operation,
            'routing_key' => $routingKey,
            'outcome' => $outcome,
        ]);

        $this->registry->observeHistogram('delivery_processing_duration_seconds', $durationSeconds, [
            'operation' => $operation,
            'outcome' => $outcome,
        ]);

        if ($idempotentReplay) {
            $this->registry->incrementCounter('delivery_idempotent_replays_total', [
                'operation' => $operation,
            ]);
        }
    }

    public function recordEventPublished(
        string $routingKey,
        bool $idempotentReplay,
        bool $duplicate = false,
    ): void {
        $this->registry->incrementCounter('delivery_events_published_total', [
            'routing_key' => $routingKey,
            'idempotent_replay' => $idempotentReplay ? 'true' : 'false',
        ]);

        if ($duplicate) {
            $this->registry->incrementCounter('delivery_event_duplicates_total', [
                'routing_key' => $routingKey,
            ]);
        }
    }

    public function recordRetryScheduled(string $routingKey): void
    {
        $this->registry->incrementCounter('delivery_request_retries_total', [
            'routing_key' => $routingKey,
        ]);
    }

    public function recordRetryRequeued(string $routingKey): void
    {
        $this->registry->incrementCounter('delivery_request_retry_requeues_total', [
            'routing_key' => $routingKey,
        ]);
    }

    public function recordDlq(string $routingKey, string $reason): void
    {
        $this->registry->incrementCounter('delivery_request_dlq_total', [
            'routing_key' => $routingKey,
            'reason' => $reason,
        ]);
    }

    public function recordInvalidMessage(string $routingKey): void
    {
        $this->registry->incrementCounter('delivery_invalid_messages_total', [
            'routing_key' => $routingKey,
        ]);
    }

    public function snapshotFailureMode(): void
    {
        $current = $this->failureModeManager->current();

        foreach (FailureMode::cases() as $mode) {
            $this->registry->setGauge('delivery_failure_mode_active', [
                'mode' => $mode->value,
            ], $mode === $current ? 1 : 0);
        }
    }
}
