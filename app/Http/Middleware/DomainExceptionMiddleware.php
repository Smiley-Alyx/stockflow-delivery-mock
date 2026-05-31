<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Delivery\Exceptions\InvalidShipmentStateException;
use App\Domain\Delivery\Exceptions\InvalidShipmentTransitionException;
use App\Domain\Delivery\Exceptions\ShipmentNotFoundException;
use App\Http\Controllers\DebugDisabledException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class DomainExceptionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ShipmentNotFoundException $exception) {
            return $this->error($exception->getMessage(), 404);
        } catch (InvalidShipmentStateException|InvalidShipmentTransitionException $exception) {
            return $this->error($exception->getMessage(), 409);
        } catch (DebugDisabledException $exception) {
            return $this->error($exception->getMessage(), 403);
        }
    }

    private function error(string $message, int $status): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write((string) json_encode([
            'error' => $message,
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
