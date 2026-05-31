<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Observability;

use App\Infrastructure\Observability\Prometheus\PrometheusRegistry;
use PHPUnit\Framework\TestCase;

final class PrometheusRegistryTest extends TestCase
{
    public function test_renders_counter_and_histogram_in_prometheus_text_format(): void
    {
        $registry = new PrometheusRegistry();

        $registry->incrementCounter('delivery_requests_total', [
            'operation' => 'shipment_create',
            'routing_key' => 'delivery.shipment.requested.v1',
            'outcome' => 'created',
        ]);

        $registry->observeHistogram('delivery_processing_duration_seconds', 0.042, [
            'operation' => 'shipment_create',
            'outcome' => 'created',
        ]);

        $output = $registry->render();

        $this->assertStringContainsString('# TYPE delivery_requests_total counter', $output);
        $this->assertStringContainsString(
            'delivery_requests_total{operation="shipment_create",outcome="created",routing_key="delivery.shipment.requested.v1"} 1',
            $output,
        );
        $this->assertStringContainsString('# TYPE delivery_processing_duration_seconds histogram', $output);
        $this->assertStringContainsString('delivery_processing_duration_seconds_bucket', $output);
        $this->assertStringContainsString('delivery_processing_duration_seconds_sum', $output);
        $this->assertStringContainsString('delivery_processing_duration_seconds_count', $output);
    }
}
