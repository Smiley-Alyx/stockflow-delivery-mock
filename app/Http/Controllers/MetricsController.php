<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Observability\DeliveryMetricsRecorder;
use App\Infrastructure\Observability\Prometheus\PrometheusRegistry;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class MetricsController
{
    public function __construct(
        private readonly PrometheusRegistry $registry,
        private readonly DeliveryMetricsRecorder $metricsRecorder,
        private readonly bool $metricsEnabled,
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        if (! $this->metricsEnabled) {
            return $response->withStatus(404);
        }

        $this->metricsRecorder->snapshotFailureMode();

        $response->getBody()->write($this->registry->render());

        return $response->withHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
