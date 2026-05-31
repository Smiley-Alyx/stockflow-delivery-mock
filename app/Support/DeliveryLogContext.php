<?php

declare(strict_types=1);

namespace App\Support;

final class DeliveryLogContext
{
    /** @var array<string, mixed> */
    private static array $context = [];

    /**
     * @param array<string, mixed> $context
     */
    public static function bind(array $context): void
    {
        self::$context = $context;
    }

    public static function clear(): void
    {
        self::$context = [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$context;
    }
}
