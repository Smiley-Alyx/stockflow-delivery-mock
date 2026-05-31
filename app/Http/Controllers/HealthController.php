<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class HealthController
{
    public function __construct(
        private readonly string $serviceName,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->json($response, [
            'service' => $this->serviceName,
            'status' => 'ok',
        ]);
    }

    public function health(Request $request, Response $response): Response
    {
        return $this->json($response, ['status' => 'ok']);
    }

    public function ready(Request $request, Response $response): Response
    {
        return $this->json($response, ['status' => 'ready']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
