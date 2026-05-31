<?php

declare(strict_types=1);

namespace App\Support;

final class DeliveryStructuredLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function write(string $level, string $message, array $context): void
    {
        $payload = json_encode([
            'level' => $level,
            'message' => $message,
            'service' => getenv('DELIVERY_MOCK_SERVICE_NAME') ?: 'stockflow-delivery-mock',
            'context' => $context,
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR);

        file_put_contents('php://stderr', $payload.PHP_EOL);
    }
}
