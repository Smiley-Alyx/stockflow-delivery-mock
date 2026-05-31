<?php

declare(strict_types=1);

namespace Tests;

use App\Bootstrap\AppFactory;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Slim\App;

abstract class TestCase extends BaseTestCase
{
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = AppFactory::create();
    }
}
