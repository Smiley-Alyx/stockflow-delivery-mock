<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Delivery\Support;

use App\Domain\Delivery\Support\PrefixedIdGenerator;
use PHPUnit\Framework\TestCase;

final class PrefixedIdGeneratorTest extends TestCase
{
    public function test_generates_id_with_prefix(): void
    {
        $id = PrefixedIdGenerator::generate('shp');

        $this->assertStringStartsWith('shp_', $id);
        $this->assertGreaterThan(strlen('shp_'), strlen($id));
    }
}
