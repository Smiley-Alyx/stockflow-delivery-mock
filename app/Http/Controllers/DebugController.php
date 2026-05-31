<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\Services\DemoResetService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DebugController extends JsonController
{
    public function __construct(
        private readonly DemoResetService $demoResetService,
        private readonly bool $debugEnabled,
    ) {
    }

    public function reset(Request $request, Response $response): Response
    {
        $this->ensureDebugEnabled();

        $this->demoResetService->reset();

        return $this->json($response, [
            'status' => 'reset',
        ]);
    }

    private function ensureDebugEnabled(): void
    {
        if (! $this->debugEnabled) {
            throw new DebugDisabledException('Debug endpoints are disabled.');
        }
    }
}
