<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class JsonController
{
    /**
     * @param array<string, mixed>|list<mixed> $payload
     */
    protected function json(Response $response, array $payload, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * @return array<string, mixed>
     */
    protected function parsedBody(Request $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }

    protected function occurredAtFromBody(array $body): \DateTimeImmutable
    {
        if (! isset($body['occurred_at']) || ! is_string($body['occurred_at']) || $body['occurred_at'] === '') {
            return new \DateTimeImmutable();
        }

        return new \DateTimeImmutable($body['occurred_at']);
    }

    protected function reasonFromBody(array $body): ?string
    {
        if (! isset($body['reason']) || ! is_string($body['reason']) || trim($body['reason']) === '') {
            return null;
        }

        return trim($body['reason']);
    }
}
