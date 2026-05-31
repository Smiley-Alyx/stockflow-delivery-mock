<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Support;

final class PrefixedIdGenerator
{
    public static function generate(string $prefix): string
    {
        return $prefix.'_'.bin2hex(random_bytes(12));
    }
}
