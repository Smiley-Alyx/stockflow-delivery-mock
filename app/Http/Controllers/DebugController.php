<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\Enums\FailureMode;
use App\Domain\Delivery\Services\Debug\FailureModeManager;
use App\Domain\Delivery\Services\DemoResetService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DebugController extends JsonController
{
    public function __construct(
        private readonly DemoResetService $demoResetService,
        private readonly FailureModeManager $failureModeManager,
        private readonly bool $debugEnabled,
    ) {
    }

    public function reset(Request $request, Response $response): Response
    {
        $this->ensureDebugEnabled();

        $this->demoResetService->reset();

        return $this->json($response, [
            'status' => 'reset',
            'failure_mode' => FailureMode::Normal->value,
        ]);
    }

    public function showFailureMode(Request $request, Response $response): Response
    {
        $this->ensureDebugEnabled();

        return $this->json($response, [
            'data' => [
                'mode' => $this->failureModeManager->current()->value,
                'available_modes' => FailureMode::values(),
            ],
        ]);
    }

    public function setFailureMode(Request $request, Response $response): Response
    {
        $this->ensureDebugEnabled();

        /** @var array<string, mixed>|null $body */
        $body = $request->getParsedBody();
        $modeValue = is_array($body) ? ($body['mode'] ?? null) : null;

        if (! is_string($modeValue) || ! in_array($modeValue, FailureMode::values(), true)) {
            return $this->json($response, [
                'error' => 'Invalid failure mode.',
                'available_modes' => FailureMode::values(),
            ], 422);
        }

        $mode = FailureMode::from($modeValue);

        return $this->json($response, [
            'data' => [
                'mode' => $this->failureModeManager->set($mode)->value,
            ],
        ]);
    }

    private function ensureDebugEnabled(): void
    {
        if (! $this->debugEnabled) {
            throw new DebugDisabledException('Debug endpoints are disabled.');
        }
    }
}
