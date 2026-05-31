<?php

declare(strict_types=1);

namespace Tests\Support\Observability;

use App\Infrastructure\Observability\DeliveryMetricsRecorder;
use App\Infrastructure\Observability\Prometheus\PrometheusRegistry;
use Tests\Support\Debug\TestFailureModeSupport;

final class TestMetricsSupport
{
    public static function registry(): PrometheusRegistry
    {
        return new PrometheusRegistry();
    }

    public static function recorder(?PrometheusRegistry $registry = null): DeliveryMetricsRecorder
    {
        return new DeliveryMetricsRecorder(
            $registry ?? self::registry(),
            TestFailureModeSupport::manager(),
        );
    }
}
